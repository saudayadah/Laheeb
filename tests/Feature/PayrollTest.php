<?php

use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\PostingService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->service = app(PayrollService::class);
    AppSettings::setMany(['advance_max_multiple' => '2']);
});

// ---------------------------------------------------------------- advances

test('an advance posts to the employee ledger and the cash box', function () {
    $employee = Employee::factory()->create(['basic_salary' => '3000.00']);

    $advance = $this->service->createAdvance([
        'employee_id' => $employee->id, 'amount' => '1000', 'paid_from' => 'counter_cash', 'plan' => 'full',
    ], $this->accountant);

    expect($advance->status)->toBe('active');
    expect($employee->outstanding())->toBe('1000.00');
    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('-1000.00');
});

test('an advance above the multiple waits for the owner', function () {
    $employee = Employee::factory()->create(['basic_salary' => '3000.00']);

    // Limit = 2 x 3000 = 6000; ask for 7000.
    $advance = $this->service->createAdvance([
        'employee_id' => $employee->id, 'amount' => '7000', 'paid_from' => 'bank', 'plan' => 'installment', 'installment_amount' => '500',
    ], $this->accountant);

    expect($advance->status)->toBe('pending');
    expect($employee->outstanding())->toBe('0.00'); // nothing posted yet

    $this->service->approveAdvance($advance, $this->owner);

    expect($advance->refresh()->status)->toBe('active');
    expect($employee->outstanding())->toBe('7000.00');
});

test('a cash-shortage charge needs the owner and moves debt off the driver custody', function () {
    $driverUser = User::factory()->create();
    $driverUser->assignRole('driver');
    $employee = Employee::factory()->create(['job' => 'driver', 'user_id' => $driverUser->id]);

    // Simulate an existing custody shortage.
    DB::transaction(function () use ($driverUser) {
        app(PostingService::class)->entry(
            LedgerEntry::DRIVER, $driverUser->id, today(), '150.00', '0.00', null, 'عجز', $this->owner->id,
        );
    });

    $charge = $this->service->createCharge([
        'employee_id' => $employee->id, 'type' => 'shortage', 'amount' => '150',
    ], $this->accountant);

    // Accountant cannot approve a shortage over HTTP.
    $this->actingAs($this->accountant)->post(route('advances.charges.approve', $charge))->assertForbidden();

    // The owner can.
    $this->actingAs($this->owner)->post(route('advances.charges.approve', $charge))->assertRedirect();

    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driverUser->id))->toBe('0.00');
    expect($employee->outstanding())->toBe('150.00');
});

// ------------------------------------------------------------------ payroll

test('a run prefills basic, advance installments and approved charges', function () {
    $empFull = Employee::factory()->create(['basic_salary' => '3000.00']);
    $empInst = Employee::factory()->create(['basic_salary' => '4000.00']);

    $this->service->createAdvance(['employee_id' => $empFull->id, 'amount' => '800', 'plan' => 'full'], $this->accountant);
    $this->service->createAdvance(['employee_id' => $empInst->id, 'amount' => '2000', 'plan' => 'installment', 'installment_amount' => '500'], $this->accountant);

    $charge = $this->service->createCharge(['employee_id' => $empInst->id, 'type' => 'fine', 'amount' => '300'], $this->accountant);
    $this->service->approveCharge($charge, $this->accountant);

    $run = $this->service->createRun('2026-10', $this->owner);

    $lineFull = $run->lines()->where('employee_id', $empFull->id)->first();
    expect((string) $lineFull->basic)->toBe('3000.00');
    expect((string) $lineFull->advance_recovery)->toBe('800.00');
    expect((string) $lineFull->net)->toBe('2200.00');

    $lineInst = $run->lines()->where('employee_id', $empInst->id)->first();
    expect((string) $lineInst->advance_recovery)->toBe('500.00');
    expect((string) $lineInst->charges_recovery)->toBe('300.00');
    expect((string) $lineInst->net)->toBe('3200.00');
});

test('absence is priced at basic/30 per day and net never goes below zero', function () {
    $employee = Employee::factory()->create(['basic_salary' => '3000.00']);
    $this->service->createAdvance(['employee_id' => $employee->id, 'amount' => '2900', 'plan' => 'full'], $this->accountant);

    $run = $this->service->createRun('2026-10', $this->owner);
    $line = $run->lines()->first();

    // 3 days absence = 3000/30*3 = 300.
    $this->service->updateLine($line, ['absence_days' => 3], $this->accountant);
    $line->refresh();
    expect((string) $line->absence_amount)->toBe('300.00');

    // Available = 3000-300 = 2700 < 2900 advance due -> recovery trimmed, net = 0.
    expect((string) $line->advance_recovery)->toBe('2700.00');
    expect((string) $line->net)->toBe('0.00');
});

test('the full lifecycle pays once, books one salaries expense and keeps the unrecovered balance', function () {
    $employee = Employee::factory()->create(['basic_salary' => '3000.00']);
    $this->service->createAdvance(['employee_id' => $employee->id, 'amount' => '5000', 'plan' => 'full'], $this->accountant);
    // outstanding: 5000

    $run = $this->service->createRun('2026-10', $this->owner);
    $line = $run->lines()->first();

    // Recovery capped at 3000 -> net 0; 2000 must survive to next month.
    expect((string) $line->advance_recovery)->toBe('3000.00');

    // Workflow is enforced in order.
    expect(fn () => $this->service->approve($run, $this->owner))->toThrow(ValidationException::class);
    $this->service->review($run, $this->accountant);
    expect(fn () => $this->service->pay($run, [], $this->owner))->toThrow(ValidationException::class);
    $this->service->approve($run, $this->owner);

    // Lines are locked once approved.
    expect(fn () => $this->service->updateLine($line->refresh(), ['overtime' => 100], $this->accountant))
        ->toThrow(ValidationException::class);

    $this->service->pay($run, [$line->id => 'bank'], $this->owner);

    expect($run->refresh()->status)->toBe('paid');
    expect($employee->outstanding())->toBe('2000.00');

    $advance = Advance::first();
    expect((string) $advance->recovered_total)->toBe('3000.00');
    expect($advance->status)->toBe('active'); // not settled yet

    // Exactly ONE salaries expense for the month... with net 0 there is nothing to pay.
    expect(Expense::count())->toBe(0);

    // Paying again is impossible.
    expect(fn () => $this->service->pay($run, [], $this->owner))->toThrow(ValidationException::class);

    // Next month recovers the remaining 2000 and settles the advance.
    $run2 = $this->service->createRun('2026-11', $this->owner);
    $line2 = $run2->lines()->first();
    expect((string) $line2->advance_recovery)->toBe('2000.00');
    expect((string) $line2->net)->toBe('1000.00');

    $this->service->review($run2, $this->accountant);
    $this->service->approve($run2, $this->owner);
    $this->service->pay($run2, [$line2->id => 'bank'], $this->owner);

    expect($employee->fresh()->outstanding())->toBe('0.00');
    expect(Advance::first()->status)->toBe('settled');

    // One salaries expense, from the bank, for the 1000 net.
    $expenses = Expense::all();
    expect($expenses)->toHaveCount(1);
    expect((string) $expenses[0]->amount)->toBe('1000.00');
    expect($expenses[0]->paid_from)->toBe('bank');
    expect(LedgerEntry::balance(LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID))->toBe('-1000.00');
});

test('drivers and clerks cannot reach payroll screens', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $clerk = User::factory()->create();
    $clerk->assignRole('clerk');

    $this->actingAs($driver)->get('/payroll')->assertForbidden();
    $this->actingAs($driver)->get('/employees')->assertForbidden();
    $this->actingAs($clerk)->get('/payroll')->assertForbidden();
    $this->actingAs($clerk)->get('/advances')->assertForbidden();
});

test('an accountant cannot give the owner approval to a payroll run', function () {
    Employee::factory()->create();
    $run = $this->service->createRun('2026-10', $this->accountant);
    $this->service->review($run, $this->accountant);

    $this->actingAs($this->accountant)->post(route('payroll.approve', $run))->assertForbidden();
    $this->actingAs($this->owner)->post(route('payroll.approve', $run))->assertRedirect();
});

test('a reviewed or approved run can be reopened for editing, a paid one cannot', function () {
    Employee::factory()->create(['basic_salary' => '3000.00']);
    $run = $this->service->createRun('2026-10', $this->owner);

    $this->service->review($run, $this->owner);
    $this->service->approve($run, $this->owner);
    expect($run->refresh()->isEditable())->toBeFalse();

    $this->service->reopen($run, $this->owner);
    expect($run->refresh()->status)->toBe('draft')
        ->and($run->approved_by)->toBeNull()
        ->and($run->isEditable())->toBeTrue();

    $this->service->review($run, $this->owner);
    $this->service->approve($run, $this->owner);
    $this->service->pay($run, [$run->lines()->first()->id => 'bank'], $this->owner);

    expect(fn () => $this->service->reopen($run->refresh(), $this->owner))->toThrow(ValidationException::class);
});

test('an unpaid run can be deleted, a paid one cannot', function () {
    Employee::factory()->create(['basic_salary' => '3000.00']);

    $run = $this->service->createRun('2026-10', $this->owner);
    $this->service->deleteRun($run, $this->owner);
    expect(PayrollRun::count())->toBe(0);

    $run2 = $this->service->createRun('2026-10', $this->owner);
    $this->service->review($run2, $this->owner);
    $this->service->approve($run2, $this->owner);
    $this->service->pay($run2, [$run2->lines()->first()->id => 'bank'], $this->owner);

    expect(fn () => $this->service->deleteRun($run2->refresh(), $this->owner))->toThrow(ValidationException::class);
});

test('employees hired after the run was created can be pulled in', function () {
    Employee::factory()->create(['basic_salary' => '3000.00']);
    $run = $this->service->createRun('2026-10', $this->owner);
    expect($run->lines()->count())->toBe(1);

    $late = Employee::factory()->create(['basic_salary' => '2500.00']);

    expect($this->service->syncEmployees($run, $this->owner))->toBe(1)
        ->and($run->lines()->count())->toBe(2)
        ->and((string) $run->lines()->where('employee_id', $late->id)->first()->net)->toBe('2500.00');

    // Running it again adds nothing.
    expect($this->service->syncEmployees($run, $this->owner))->toBe(0);

    // A paid run refuses.
    $this->service->review($run, $this->owner);
    $this->service->approve($run, $this->owner);
    $methods = $run->lines()->pluck('id')->mapWithKeys(fn ($id) => [$id => 'bank'])->all();
    $this->service->pay($run, $methods, $this->owner);

    expect(fn () => $this->service->syncEmployees($run->refresh(), $this->owner))->toThrow(ValidationException::class);
});

test('a stale prefill from a second open run never over-recovers a settled advance', function () {
    $employee = Employee::factory()->create(['basic_salary' => '3000.00']);

    $this->service->createAdvance([
        'employee_id' => $employee->id, 'amount' => '500', 'paid_from' => 'bank', 'plan' => 'full',
    ], $this->owner);

    // Two runs open at once: both prefill the SAME 500.00 due.
    $october = $this->service->createRun('2026-10', $this->owner);
    $november = $this->service->createRun('2026-11', $this->owner);

    $payAll = function ($run) {
        $this->service->review($run, $this->owner);
        $this->service->approve($run, $this->owner);
        $methods = $run->lines()->pluck('id')->mapWithKeys(fn ($id) => [$id => 'bank'])->all();
        $this->service->pay($run, $methods, $this->owner);
    };

    $payAll($october); // settles the advance

    $payAll($november); // stale 500.00 prefill must clamp to 0.00

    $novLine = $november->lines()->first()->refresh();

    expect((string) $novLine->advance_recovery)->toBe('0.00')
        ->and((string) $novLine->net)->toBe('3000.00')
        ->and($employee->outstanding())->toBe('0.00')
        ->and(LedgerEntry::balance(LedgerEntry::EMPLOYEE, $employee->id))->toBe('0.00');
});

test('adding employees to a reviewed run drops it back to draft for a fresh review', function () {
    Employee::factory()->create(['basic_salary' => '3000.00']);
    $run = $this->service->createRun('2026-10', $this->owner);
    $this->service->review($run, $this->owner);

    Employee::factory()->create(['basic_salary' => '2000.00']);
    $this->service->syncEmployees($run, $this->owner);

    expect($run->refresh()->status)->toBe('draft')
        ->and($run->reviewed_by)->toBeNull();
});
