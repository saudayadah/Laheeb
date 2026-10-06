<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DailyCloseService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->driver = User::factory()->create();
    $this->driver->assignRole('driver');

    $this->service = app(ExpenseService::class);
    $this->category = ExpenseCategory::create(['name_ar' => 'ديزل', 'kind' => 'operating']);
    AppSettings::setMany(['vat_enabled' => false, 'expense_approval_threshold' => '500']);
});

test('each payment source hits the right ledger account', function () {
    $supplier = Supplier::create(['name' => 'مطاحن الدقيق']);

    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '100', 'paid_from' => 'counter_cash',
    ], $this->accountant);

    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '50', 'paid_from' => 'driver_cash', 'paid_by' => $this->driver->id,
    ], $this->accountant);

    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '200', 'paid_from' => 'bank',
    ], $this->accountant);

    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '5000', 'paid_from' => 'supplier_credit', 'supplier_id' => $supplier->id,
    ], $this->accountant);

    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('-100.00');
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $this->driver->id))->toBe('-50.00');
    expect(LedgerEntry::balance(LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID))->toBe('-200.00');
    expect($supplier->payable())->toBe('5000.00');
});

test('a big expense from a non-approver waits, posts nothing, then counts after approval', function () {
    $clerkish = User::factory()->create();
    $clerkish->assignRole('accountant'); // can manage; threshold still applies only to non-approvers

    $driver = $this->driver;

    // Driver submits a 600 expense (above the 500 threshold).
    $expense = $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '600', 'paid_from' => 'driver_cash', 'paid_by' => $driver->id,
    ], $driver);

    expect($expense->status)->toBe('pending');
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('0.00');

    $this->service->approve($expense, $this->accountant);

    expect($expense->refresh()->status)->toBe('approved');
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('-600.00');
});

test('an approved driver expense reduces his expected close cash', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['default_price' => '0.3000']);

    // 60 cash sales, 15 diesel from his pocket.
    app(InvoiceService::class)->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $this->driver->id],
        [['product_id' => $product->id, 'qty' => 200]],
        $this->owner,
    );
    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '15', 'paid_from' => 'driver_cash', 'paid_by' => $this->driver->id,
    ], $this->accountant);

    expect(app(DailyCloseService::class)->expectedForDriver($this->driver->id, today()))->toBe('45.00');
});

test('supplier payments reduce the payable', function () {
    $supplier = Supplier::create(['name' => 'مطاحن الدقيق']);

    $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '10000', 'paid_from' => 'supplier_credit', 'supplier_id' => $supplier->id,
    ], $this->accountant);

    $this->service->paySupplier($supplier, [
        'amount' => '4000', 'payment_date' => today()->toDateString(), 'method' => 'bank',
    ], $this->accountant);

    expect($supplier->payable())->toBe('6000.00');
    expect(LedgerEntry::balance(LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID))->toBe('-4000.00');
});

test('voiding an approved expense restores the ledger', function () {
    $expense = $this->service->create([
        'expense_category_id' => $this->category->id,
        'amount' => '100', 'paid_from' => 'counter_cash',
    ], $this->accountant);

    $this->service->void($expense, $this->accountant, 'خطأ إدخال');

    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('0.00');
    expect($expense->refresh()->status)->toBe('void');
});

test('recurring expenses generate one pending draft per month', function () {
    RecurringExpense::create([
        'name' => 'إيجار المحل',
        'expense_category_id' => $this->category->id,
        'amount' => '3000',
        'day_of_month' => 1,
        'paid_from' => 'bank',
    ]);

    expect($this->service->generateRecurring(now()))->toBe(1);
    // Same month again: nothing.
    expect($this->service->generateRecurring(now()))->toBe(0);

    $draft = Expense::where('recurring_expense_id', '!=', null)->first();
    expect($draft->status)->toBe('pending');
    expect((string) $draft->amount)->toBe('3000.00');

    // Next month: a fresh draft.
    expect($this->service->generateRecurring(now()->addMonth()))->toBe(1);
});

test('drivers can submit an expense over HTTP but cannot see the expenses office', function () {
    $this->actingAs($this->driver)->post(route('delivery.expense'), [
        'expense_category_id' => $this->category->id,
        'amount' => '25',
        'note' => 'ديزل',
    ])->assertRedirect(route('delivery.index'));

    expect(Expense::count())->toBe(1);
    expect(Expense::first()->paid_by)->toBe($this->driver->id);

    $this->actingAs($this->driver)->get('/expenses')->assertForbidden();
    $this->actingAs($this->driver)->get('/suppliers')->assertForbidden();
});
