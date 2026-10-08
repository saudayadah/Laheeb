<?php

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Services\ReceivablesService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->invoices = app(InvoiceService::class);
    $this->receipts = app(ReceiptService::class);
    $this->product = Product::factory()->create(['default_price' => '0.3000']);
    AppSettings::setMany(['vat_enabled' => false]);
});

function creditInvoice($service, Customer $customer, Product $product, int $qty, User $user, ?string $date = null)
{
    // override_credit: test fixtures build overdue history on purpose.
    $invoice = $service->postDirect(
        [
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'invoice_date' => $date ?? today()->toDateString(),
            'override_credit' => true,
            'override_reason' => 'fixture',
        ],
        [['product_id' => $product->id, 'qty' => $qty]],
        $user,
    );

    if ($date !== null) {
        $invoice->forceFill(['invoice_date' => $date])->save();
    }

    return $invoice;
}

test('a payment covers the oldest invoices first and leaves the rest open', function () {
    $customer = Customer::factory()->credit(null)->create();

    $inv1 = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(10)->toDateString());
    $inv2 = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(5)->toDateString());
    $inv3 = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner); // 30 each

    $receipt = $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '70.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        null,
        $this->owner,
    );

    $allocations = $receipt->allocations()->orderBy('id')->get();
    expect($allocations)->toHaveCount(3);
    expect($allocations[0]->invoice_id)->toBe($inv1->id);
    expect((string) $allocations[0]->amount)->toBe('30.00');
    expect($allocations[1]->invoice_id)->toBe($inv2->id);
    expect((string) $allocations[1]->amount)->toBe('30.00');
    expect($allocations[2]->invoice_id)->toBe($inv3->id);
    expect((string) $allocations[2]->amount)->toBe('10.00');

    $open = $this->receipts->openInvoices($customer);
    expect($open)->toHaveCount(1);
    expect($open[0]['invoice']->id)->toBe($inv3->id);
    expect($open[0]['open'])->toBe('20.00');

    // Balance: 90 - 70 = 20.
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('20.00');
});

test('an overpayment stays as on-account credit and auto-applies via FIFO next time', function () {
    $customer = Customer::factory()->credit(null)->create();
    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner); // 30

    $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '50.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        null,
        $this->owner,
    );

    // Negative balance = we owe the customer 20.
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('-20.00');
});

test('manual allocation is respected and over-allocation is rejected', function () {
    $customer = Customer::factory()->credit(null)->create();
    $inv1 = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(5)->toDateString());
    $inv2 = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner);

    // Pay the NEWER invoice on purpose.
    $receipt = $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '30.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        [$inv2->id => '30.00'],
        $this->owner,
    );

    expect($receipt->allocations()->count())->toBe(1);
    expect($receipt->allocations()->first()->invoice_id)->toBe($inv2->id);

    // Allocating more than the invoice's open amount fails.
    expect(fn () => $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '100.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        [$inv1->id => '99.00'],
        $this->owner,
    ))->toThrow(ValidationException::class);
});

test('a group payment spreads across branch invoices oldest-first', function () {
    $group = CustomerGroup::factory()->create();
    $branch1 = Customer::factory()->credit(null)->inGroup($group)->create();
    $branch2 = Customer::factory()->credit(null)->inGroup($group)->create();

    creditInvoice($this->invoices, $branch1, $this->product, 100, $this->owner, today()->subDays(9)->toDateString()); // 30
    creditInvoice($this->invoices, $branch2, $this->product, 100, $this->owner, today()->subDays(3)->toDateString()); // 30

    $this->receipts->create(
        ['customer_group_id' => $group->id, 'amount' => '45.00', 'receipt_date' => today()->toDateString(), 'method' => 'transfer'],
        null,
        $this->owner,
    );

    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $branch1->id))->toBe('0.00');
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $branch2->id))->toBe('15.00');
    expect(LedgerEntry::balance(LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID))->toBe('45.00');
});

test('cash received by a driver lands in his custody', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $customer = Customer::factory()->credit(null)->create();
    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner);

    $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '30.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash', 'received_by' => $driver->id],
        null,
        $this->owner,
    );

    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('30.00');
    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('0.00');
});

test('voiding a receipt restores the balance and frees the invoices', function () {
    $customer = Customer::factory()->credit(null)->create();
    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner);

    $receipt = $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '30.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        null,
        $this->owner,
    );

    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('0.00');

    $this->receipts->void($receipt, $this->owner, 'خطأ');

    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('30.00');
    expect($this->receipts->openInvoices($customer)[0]['open'])->toBe('30.00');
});

test('aging buckets split the balance by invoice age, FIFO', function () {
    $customer = Customer::factory()->credit(null)->create();

    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(100)->toDateString()); // 30 -> 90+
    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(45)->toDateString());  // 30 -> 31-60
    creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner, today()->subDays(10)->toDateString());  // 30 -> 0-30

    // Pay 20: FIFO eats into the OLDEST bucket.
    $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '20.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash'],
        null,
        $this->owner,
    );

    $aging = app(ReceivablesService::class)->aging();

    expect($aging['rows'])->toHaveCount(1);
    $row = $aging['rows'][0];
    expect($row['balance'])->toBe('70.00');
    expect($row['buckets']['b90p'])->toBe('10.00');
    expect($row['buckets']['b60'])->toBe('30.00');
    expect($row['buckets']['b30'])->toBe('30.00');
    expect($row['overdue'])->toBeTrue();

    expect($aging['totals']['balance'])->toBe('70.00');
});

test('a paid-against invoice refuses reclassification until its receipts are voided', function () {
    $customer = Customer::factory()->create(['payment_term' => 'credit']);
    $invoice = creditInvoice($this->invoices, $customer, $this->product, 100, $this->owner);

    $this->receipts->create([
        'customer_id' => $customer->id,
        'amount' => '10.00',
        'method' => 'cash',
    ], null, $this->owner);

    expect(fn () => $this->invoices->reclassify($invoice->refresh(), 'cash', $this->owner, 'test'))
        ->toThrow(ValidationException::class);
});
