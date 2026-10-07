<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Employee;
use App\Models\EmployeeCharge;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private PostingService $posting) {}

    // ---------------------------------------------------------- advances

    public function createAdvance(array $attrs, User $user): Advance
    {
        return DB::transaction(function () use ($attrs, $user) {
            $employee = Employee::findOrFail($attrs['employee_id']);
            $amount = Money::add((string) $attrs['amount'], '0.00');

            // Over the allowed multiple of basic salary -> Owner approval.
            $multiple = (string) AppSettings::get('advance_max_multiple', '2');
            $limit = (string) BigDecimal::of((string) $employee->basic_salary)
                ->multipliedBy(BigDecimal::of($multiple))
                ->toScale(2, RoundingMode::HALF_UP);

            $needsApproval = Money::compare($amount, $limit) > 0 && ! $user->can('advances.approve');

            $advance = Advance::create([
                'employee_id' => $employee->id,
                'advance_date' => $attrs['advance_date'] ?? today()->toDateString(),
                'amount' => $amount,
                'paid_from' => $attrs['paid_from'] ?? 'counter_cash',
                'plan' => $attrs['plan'] ?? 'full',
                'installment_amount' => ($attrs['plan'] ?? 'full') === 'installment' ? ($attrs['installment_amount'] ?? null) : null,
                'status' => $needsApproval ? 'pending' : 'active',
                'notes' => $attrs['notes'] ?? null,
                'created_by' => $user->id,
                'approved_by' => $needsApproval ? null : $user->id,
            ]);

            if ($advance->status === 'active') {
                $this->postAdvance($advance, $user);
            }

            activity()->causedBy($user)->performedOn($advance)->log('advance.created');

            return $advance;
        });
    }

    public function approveAdvance(Advance $advance, User $approver): Advance
    {
        return DB::transaction(function () use ($advance, $approver) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();

            if ($advance->status !== 'pending') {
                return $advance;
            }

            $advance->update(['status' => 'active', 'approved_by' => $approver->id]);
            $this->postAdvance($advance, $approver);

            activity()->causedBy($approver)->performedOn($advance)->log('advance.approved');

            return $advance;
        });
    }

    private function postAdvance(Advance $advance, User $user): void
    {
        // The employee owes it...
        $this->posting->entry(
            LedgerEntry::EMPLOYEE, $advance->employee_id, $advance->advance_date,
            debit: (string) $advance->amount, credit: '0.00',
            source: $advance,
            description: __('ledger.advance', ['date' => $advance->advance_date->toDateString()]),
            userId: $user->id,
        );

        // ...and the money left the box or the bank.
        [$type, $id] = $advance->paid_from === 'bank'
            ? [LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID]
            : [LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID];

        $this->posting->entry(
            $type, $id, $advance->advance_date,
            debit: '0.00', credit: (string) $advance->amount,
            source: $advance,
            description: __('ledger.advance', ['date' => $advance->advance_date->toDateString()]),
            userId: $user->id,
        );
    }

    // ----------------------------------------------------------- charges

    public function createCharge(array $attrs, User $user): EmployeeCharge
    {
        $charge = EmployeeCharge::create([
            'employee_id' => $attrs['employee_id'],
            'charge_date' => $attrs['charge_date'] ?? today()->toDateString(),
            'type' => $attrs['type'],
            'amount' => $attrs['amount'],
            'status' => 'pending',
            'notes' => $attrs['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        activity()->causedBy($user)->performedOn($charge)->log('charge.created');

        return $charge;
    }

    /**
     * Approving a charge puts it on the employee's account. A cash-shortage
     * charge also clears the same amount off the driver's custody.
     */
    public function approveCharge(EmployeeCharge $charge, User $approver): EmployeeCharge
    {
        return DB::transaction(function () use ($charge, $approver) {
            $charge = EmployeeCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($charge->status !== 'pending') {
                return $charge;
            }

            $charge->update(['status' => 'approved', 'approved_by' => $approver->id]);

            $this->posting->entry(
                LedgerEntry::EMPLOYEE, $charge->employee_id, $charge->charge_date,
                debit: (string) $charge->amount, credit: '0.00',
                source: $charge,
                description: __("ledger.charge.{$charge->type}"),
                userId: $approver->id,
            );

            if ($charge->type === 'shortage') {
                $driverUserId = $charge->employee->user_id;
                if ($driverUserId !== null) {
                    $this->posting->entry(
                        LedgerEntry::DRIVER, $driverUserId, $charge->charge_date,
                        debit: '0.00', credit: (string) $charge->amount,
                        source: $charge,
                        description: __('ledger.charge.shortage'),
                        userId: $approver->id,
                    );
                }
            }

            activity()->causedBy($approver)->performedOn($charge)->log('charge.approved');

            return $charge;
        });
    }

    // ------------------------------------------------------------ payroll

    public function createRun(string $period, User $user): PayrollRun
    {
        return DB::transaction(function () use ($period, $user) {
            if (PayrollRun::where('period', $period)->exists()) {
                throw ValidationException::withMessages(['period' => __('payroll.period_exists')]);
            }

            $run = PayrollRun::create(['period' => $period, 'created_by' => $user->id]);

            foreach (Employee::where('active', true)->orderBy('name_ar')->get() as $employee) {
                $advanceDue = Money::sum(
                    $employee->advances()->where('status', 'active')->get()->map->dueThisMonth(),
                );
                $chargesDue = Money::sum(
                    $employee->charges()->where('status', 'approved')->get()->map->remaining(),
                );

                $line = new PayrollLine([
                    'employee_id' => $employee->id,
                    'basic' => (string) $employee->basic_salary,
                    'advance_recovery' => $advanceDue,
                    'charges_recovery' => $chargesDue,
                ]);
                $this->recalculate($line);
                $run->lines()->save($line);
            }

            activity()->causedBy($user)->performedOn($run)->log('payroll.created');

            return $run;
        });
    }

    /** Net = basic + overtime + allowance + additions - absence - deductions - recoveries, floored at zero. */
    public function recalculate(PayrollLine $line): void
    {
        $gross = Money::sum([
            (string) $line->basic, (string) $line->overtime,
            (string) $line->leave_allowance, (string) $line->additions,
        ]);
        $nonRecovery = Money::add((string) $line->absence_amount, (string) $line->deductions);
        $available = Money::subtract($gross, $nonRecovery);

        if (Money::compare($available, '0.00') < 0) {
            $available = '0.00';
        }

        // The floor: recoveries can never push net below zero.
        $advance = (string) $line->advance_recovery;
        $charges = (string) $line->charges_recovery;
        $totalRecovery = Money::add($advance, $charges);

        if (Money::compare($totalRecovery, $available) > 0) {
            // Trim charges first, then the advance recovery.
            $charges = Money::compare($available, $advance) >= 0
                ? Money::subtract($available, $advance)
                : '0.00';
            $advance = Money::compare($available, $advance) >= 0 ? $advance : $available;
            $totalRecovery = Money::add($advance, $charges);
        }

        $line->advance_recovery = $advance;
        $line->charges_recovery = $charges;
        $line->net = Money::subtract($available, $totalRecovery);
    }

    public function updateLine(PayrollLine $line, array $attrs, User $user): PayrollLine
    {
        if (! $line->run->isEditable()) {
            throw ValidationException::withMessages(['line' => __('payroll.locked')]);
        }

        $line->fill([
            'overtime' => $attrs['overtime'] ?? $line->overtime,
            'leave_allowance' => $attrs['leave_allowance'] ?? $line->leave_allowance,
            'additions' => $attrs['additions'] ?? $line->additions,
            'absence_days' => $attrs['absence_days'] ?? $line->absence_days,
            'deductions' => $attrs['deductions'] ?? $line->deductions,
            'advance_recovery' => $attrs['advance_recovery'] ?? $line->advance_recovery,
            'charges_recovery' => $attrs['charges_recovery'] ?? $line->charges_recovery,
            'payment_method' => $attrs['payment_method'] ?? $line->payment_method,
        ]);

        // Absence: days x basic/30 by default, unless an explicit amount is sent.
        if (isset($attrs['absence_days']) && ! isset($attrs['absence_amount'])) {
            $line->absence_amount = (string) BigDecimal::of((string) $line->basic)
                ->dividedBy(30, 4, RoundingMode::HALF_UP)
                ->multipliedBy(BigDecimal::of((string) $line->absence_days))
                ->toScale(2, RoundingMode::HALF_UP);
        } elseif (isset($attrs['absence_amount'])) {
            $line->absence_amount = $attrs['absence_amount'];
        }

        $this->recalculate($line);
        $line->save();

        return $line;
    }

    public function review(PayrollRun $run, User $user): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['run' => __('payroll.bad_transition')]);
        }

        $run->update(['status' => 'reviewed', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        activity()->causedBy($user)->performedOn($run)->log('payroll.reviewed');

        return $run;
    }

    public function approve(PayrollRun $run, User $user): PayrollRun
    {
        if ($run->status !== 'reviewed') {
            throw ValidationException::withMessages(['run' => __('payroll.bad_transition')]);
        }

        $run->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        activity()->causedBy($user)->performedOn($run)->log('payroll.approved');

        return $run;
    }

    /**
     * Unlock a reviewed/approved run so the owner can keep editing it.
     * Nothing is posted to the ledger before pay(), so this is always safe.
     */
    public function reopen(PayrollRun $run, User $user): PayrollRun
    {
        if (! in_array($run->status, ['reviewed', 'approved'], true)) {
            throw ValidationException::withMessages(['run' => __('payroll.bad_transition')]);
        }

        $run->update([
            'status' => 'draft',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        activity()->causedBy($user)->performedOn($run)->log('payroll.reopened');

        return $run;
    }

    /**
     * Remove an unpaid run created by mistake (wrong month, duplicate).
     */
    public function deleteRun(PayrollRun $run, User $user): void
    {
        if ($run->status === 'paid') {
            throw ValidationException::withMessages(['run' => __('payroll.locked')]);
        }

        DB::transaction(function () use ($run, $user) {
            activity()->causedBy($user)->performedOn($run)->log('payroll.deleted');
            $run->lines()->delete();
            $run->delete();
        });
    }

    /**
     * Pay the whole approved run: recover advances/charges on each employee's
     * ledger, then book the salaries as ONE expense per payment source.
     */
    public function pay(PayrollRun $run, array $methods, User $user): PayrollRun
    {
        return DB::transaction(function () use ($run, $methods, $user) {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== 'approved') {
                throw ValidationException::withMessages(['run' => __('payroll.bad_transition')]);
            }

            $salaryCategory = ExpenseCategory::firstOrCreate(
                ['name_ar' => 'رواتب'],
                ['name_en' => 'Salaries', 'kind' => 'fixed'],
            );

            $totals = ['cash' => '0.00', 'bank' => '0.00'];

            foreach ($run->lines()->with('employee')->get() as $line) {
                $method = $methods[$line->id] ?? $line->payment_method ?? 'bank';
                $line->update(['payment_method' => $method, 'paid_at' => now()]);
                $totals[$method === 'cash' ? 'cash' : 'bank'] = Money::add($totals[$method === 'cash' ? 'cash' : 'bank'], (string) $line->net);

                // Recoveries come off the employee's account, oldest first.
                $this->allocateRecovery($line, $user);
            }

            foreach ($totals as $method => $total) {
                if (Money::isZero($total)) {
                    continue;
                }

                Expense::create([
                    'expense_date' => now()->toDateString(),
                    'expense_category_id' => $salaryCategory->id,
                    'amount' => $total,
                    'paid_from' => $method === 'cash' ? 'counter_cash' : 'bank',
                    'status' => 'approved',
                    'note' => __('payroll.expense_note', ['period' => $run->period]),
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ])->ledgerEntries()->create([
                    'account_type' => $method === 'cash' ? LedgerEntry::CASH_BOX : LedgerEntry::BANK,
                    'account_id' => $method === 'cash' ? LedgerEntry::MAIN_CASH_BOX_ID : LedgerEntry::MAIN_BANK_ID,
                    'entry_date' => now()->toDateString(),
                    'debit' => '0.00',
                    'credit' => $total,
                    'description' => __('payroll.expense_note', ['period' => $run->period]),
                    'created_by' => $user->id,
                ]);
            }

            $run->update(['status' => 'paid', 'paid_at' => now()]);

            activity()->causedBy($user)->performedOn($run)->log('payroll.paid');

            return $run;
        });
    }

    private function allocateRecovery(PayrollLine $line, User $user): void
    {
        $employee = $line->employee;

        // Advances, oldest first.
        $remaining = (string) $line->advance_recovery;
        foreach ($employee->advances()->where('status', 'active')->orderBy('advance_date')->orderBy('id')->get() as $advance) {
            if (Money::compare($remaining, '0.00') <= 0) {
                break;
            }
            $take = Money::compare($advance->remaining(), $remaining) <= 0 ? $advance->remaining() : $remaining;
            $advance->recovered_total = Money::add((string) $advance->recovered_total, $take);
            if (Money::compare($advance->remaining(), '0.00') <= 0) {
                $advance->status = 'settled';
            }
            $advance->save();
            $remaining = Money::subtract($remaining, $take);
        }

        // Charges, oldest first.
        $remaining = (string) $line->charges_recovery;
        foreach ($employee->charges()->where('status', 'approved')->orderBy('charge_date')->orderBy('id')->get() as $charge) {
            if (Money::compare($remaining, '0.00') <= 0) {
                break;
            }
            $take = Money::compare($charge->remaining(), $remaining) <= 0 ? $charge->remaining() : $remaining;
            $charge->recovered_total = Money::add((string) $charge->recovered_total, $take);
            if (Money::compare(Money::subtract((string) $charge->amount, (string) $charge->recovered_total), '0.00') <= 0) {
                $charge->status = 'settled';
            }
            $charge->save();
            $remaining = Money::subtract($remaining, $take);
        }

        $totalRecovered = Money::add((string) $line->advance_recovery, (string) $line->charges_recovery);
        if (! Money::isZero($totalRecovered)) {
            $this->posting->entry(
                LedgerEntry::EMPLOYEE, $employee->id, now()->toDateString(),
                debit: '0.00', credit: $totalRecovered,
                source: $line,
                description: __('ledger.payroll_recovery', ['period' => $line->run->period]),
                userId: $user->id,
            );
        }
    }
}
