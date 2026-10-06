<?php

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderGridService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->service = app(InvoiceService::class);
    $this->product = Product::factory()->create(['default_price' => '0.3000']);

    AppSettings::setMany(['vat_enabled' => true, 'vat_rate' => '15.00', 'prices_include_vat' => true]);
});

// ---------------------------------------------------------------- VAT math

test('vat-inclusive totals: 1000 loaves at 0.30 incl = 300.00 total, 39.13 vat', function () {
    $customer = Customer::factory()->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 1000]],
        $this->owner,
    );

    expect((string) $invoice->total)->toBe('300.00');
    expect((string) $invoice->vat_amount)->toBe('39.13');
    expect((string) $invoice->subtotal)->toBe('260.87');
});

test('vat-exclusive totals: 1000 loaves at 0.30 excl = 300 + 45 vat = 345', function () {
    AppSettings::setMany(['prices_include_vat' => false]);
    $customer = Customer::factory()->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 1000]],
        $this->owner,
    );

    expect((string) $invoice->subtotal)->toBe('300.00');
    expect((string) $invoice->vat_amount)->toBe('45.00');
    expect((string) $invoice->total)->toBe('345.00');
});

test('vat disabled: no vat at all', function () {
    AppSettings::setMany(['vat_enabled' => false]);
    $customer = Customer::factory()->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );

    expect((string) $invoice->vat_amount)->toBe('0.00');
    expect((string) $invoice->total)->toBe('30.00');
});

// ------------------------------------------------------- numbering & hash

test('invoice numbers are sequential and a void never frees its number', function () {
    $customer = Customer::factory()->create();

    $first = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
    );
    $second = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
    );

    expect($first->number)->toBe(1);
    expect($second->number)->toBe(2);

    // Hash chain links backwards.
    expect($second->prev_hash)->toBe($first->hash);

    $this->service->void($second, $this->owner, 'خطأ');

    $third = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
    );

    expect($third->number)->toBe(3);
});

test('a failed post consumes no number', function () {
    $customer = Customer::factory()->create();

    try {
        $this->service->postDirect(
            ['customer_id' => $customer->id, 'payment_method' => 'cash'],
            [['product_id' => $this->product->id, 'qty' => 0]], // empty invoice -> rejected
            $this->owner,
        );
    } catch (ValidationException) {
        // expected
    }

    $next = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 5]],
        $this->owner,
    );

    expect($next->number)->toBe(1);
});

// ------------------------------------------------------------- idempotency

test('the same idempotency key never creates a second invoice', function () {
    $customer = Customer::factory()->create();
    $key = 'test-key-123';

    $first = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
        $key,
    );
    $retry = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
        $key,
    );

    expect($retry->id)->toBe($first->id);
    expect(Invoice::count())->toBe(1);
    expect(LedgerEntry::count())->toBe(1);
});

// ------------------------------------------------------------- the ledger

test('posting routes money to the right account for each payment method', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $customer = Customer::factory()->credit()->create();

    // Credit -> the customer owes it.
    $credit = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('30.00');

    // Cash by a driver -> driver custody.
    $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $driver->id],
        [['product_id' => $this->product->id, 'qty' => 200]],
        $this->owner,
    );
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('60.00');

    // Mada -> the bank.
    $this->service->postDirect(
        ['payment_method' => 'mada', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 50]],
        $this->owner,
    );
    expect(LedgerEntry::balance(LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID))->toBe('15.00');

    // Cash at the counter (no driver) -> the cash box.
    $this->service->postDirect(
        ['payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 50]],
        $this->owner,
    );
    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('15.00');

    // Voiding the credit invoice returns the customer to zero.
    $this->service->void($credit, $this->owner, 'اختبار');
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('0.00');
});

test('a credit note can never exceed what remains on the invoice', function () {
    $customer = Customer::factory()->credit(null)->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]], // 30.00
        $this->owner,
    );

    // First return of 20 loaves (6.00) is fine.
    $this->service->createCreditNote($invoice, $customer, [['product_id' => $this->product->id, 'qty' => 20]], 'مرتجع', $this->owner);

    // A second return of 90 loaves (27.00) would exceed the remaining 24.00.
    expect(fn () => $this->service->createCreditNote(
        $invoice, $customer, [['product_id' => $this->product->id, 'qty' => 90]], 'مرتجع', $this->owner,
    ))->toThrow(ValidationException::class);

    expect(CreditNote::count())->toBe(1);
});

test('a credit note reduces the customer balance', function () {
    $customer = Customer::factory()->credit()->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );

    $this->service->createCreditNote(
        $invoice,
        $customer,
        [['product_id' => $this->product->id, 'qty' => 20, 'condition' => 'good']],
        'مرتجع',
        $this->owner,
    );

    // 30.00 - 6.00 = 24.00
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('24.00');
});

test('cash-to-credit reclassification moves the amount between accounts', function () {
    $driver = User::factory()->create();
    $customer = Customer::factory()->credit()->create();

    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $driver->id],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );

    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('30.00');
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('0.00');

    $this->service->reclassify($invoice, 'credit', $this->owner, 'العميل لم يدفع');

    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('0.00');
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('30.00');
});

// ---------------------------------------------------------- credit control

test('credit sales are blocked over the limit unless overridden', function () {
    $customer = Customer::factory()->credit('50')->create();

    $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]], // 30.00
        $this->owner,
    );

    // 30 + 30 > 50 -> blocked.
    expect(fn () => $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    ))->toThrow(ValidationException::class);

    // With an explicit override it goes through.
    $invoice = $this->service->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit', 'override_credit' => true, 'override_reason' => 'موافقة المدير'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );

    expect($invoice->isPosted())->toBeTrue();
});

test('the group credit limit covers all branches together', function () {
    $group = CustomerGroup::factory()->create(['credit_limit' => '50', 'credit_scope' => 'group']);
    $branch1 = Customer::factory()->credit(null)->inGroup($group)->create();
    $branch2 = Customer::factory()->credit(null)->inGroup($group)->create();

    $this->service->postDirect(
        ['customer_id' => $branch1->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]], // 30
        $this->owner,
    );

    // Branch 2 pushes the GROUP over its 50 limit.
    expect(fn () => $this->service->postDirect(
        ['customer_id' => $branch2->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    ))->toThrow(ValidationException::class);
});

// ------------------------------------------------------------- confirm day

test('confirming a day turns orders into one draft invoice per customer', function () {
    $route = DeliveryRoute::factory()->create();
    $customerA = Customer::factory()->onRoute($route)->create();
    $customerB = Customer::factory()->credit()->create();

    $grid = app(OrderGridService::class);
    $grid->saveCells(today(), [
        ['customer_id' => $customerA->id, 'product_id' => $this->product->id, 'qty' => 100],
        ['customer_id' => $customerB->id, 'product_id' => $this->product->id, 'qty' => 50],
    ], $this->owner->id);

    $count = $this->service->confirmDay(today(), $this->owner);

    expect($count)->toBe(2);
    expect(Invoice::where('status', Invoice::STATUS_DRAFT)->count())->toBe(2);
    expect(Order::where('status', Order::STATUS_CONFIRMED)->count())->toBe(2);

    // The credit customer's draft defaults to his payment term.
    $draftB = Invoice::where('customer_id', $customerB->id)->first();
    expect($draftB->payment_method)->toBe('credit');
    expect((string) $draftB->total)->toBe('15.00');

    // Running it again creates nothing new.
    expect($this->service->confirmDay(today(), $this->owner))->toBe(0);
});
