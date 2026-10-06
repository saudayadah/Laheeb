<?php

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->driver = User::factory()->create();
    $this->driver->assignRole('driver');

    $this->route = DeliveryRoute::factory()->create(['default_driver_id' => $this->driver->id]);
    $this->customer = Customer::factory()->onRoute($this->route)->create();
    $this->product = Product::factory()->create(['default_price' => '0.3000']);
});

test('a driver sees his stops and posts a delivery with cash', function () {
    $this->actingAs($this->driver)->get('/delivery')->assertOk();

    $this->actingAs($this->driver)
        ->post(route('delivery.post-stop', $this->customer), [
            'items' => [['product_id' => $this->product->id, 'qty' => 100]],
            'returns' => [],
            'payment_method' => 'cash',
            'idempotency_key' => 'drv-1',
        ])
        ->assertRedirect();

    $invoice = Invoice::first();
    expect($invoice->status)->toBe(Invoice::STATUS_POSTED);
    expect($invoice->driver_id)->toBe($this->driver->id);
    expect($invoice->source)->toBe('van');

    // Cash lands in the driver's custody.
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $this->driver->id))->toBe('30.00');

    // The driver can open his own thermal receipt.
    $this->actingAs($this->driver)->get(route('invoices.thermal', $invoice))->assertOk();
});

test('a retry with the same idempotency key does not duplicate anything', function () {
    foreach ([1, 2] as $attempt) {
        $this->actingAs($this->driver)->post(route('delivery.post-stop', $this->customer), [
            'items' => [['product_id' => $this->product->id, 'qty' => 100]],
            'returns' => [['product_id' => $this->product->id, 'qty' => 10, 'condition' => 'good']],
            'payment_method' => 'cash',
            'idempotency_key' => 'weak-connection',
        ]);
    }

    expect(Invoice::count())->toBe(1);
    expect(CreditNote::count())->toBe(1);
});

test('a driver cannot touch a customer on another route', function () {
    $otherDriver = User::factory()->create();
    $otherDriver->assignRole('driver');
    $otherRoute = DeliveryRoute::factory()->create(['default_driver_id' => $otherDriver->id]);
    $otherCustomer = Customer::factory()->onRoute($otherRoute)->create();

    $this->actingAs($this->driver)->get(route('delivery.stop', $otherCustomer))->assertForbidden();

    $this->actingAs($this->driver)->post(route('delivery.post-stop', $otherCustomer), [
        'items' => [['product_id' => $this->product->id, 'qty' => 10]],
        'payment_method' => 'cash',
        'idempotency_key' => 'x',
    ])->assertForbidden();
});

test('a driver cannot see other office screens or another driver receipt', function () {
    $this->actingAs($this->driver)->get('/invoices')->assertForbidden();
    $this->actingAs($this->driver)->get('/pos')->assertForbidden();

    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $invoice = app(InvoiceService::class)->postDirect(
        ['payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 5]],
        $owner,
    );

    $this->actingAs($this->driver)->get(route('invoices.thermal', $invoice))->assertForbidden();
});

test('counter sale posts to the cash box and prints', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->post(route('pos.sale'), [
        'items' => [['product_id' => $this->product->id, 'qty' => 20]],
        'payment_method' => 'cash',
        'idempotency_key' => 'pos-1',
    ])->assertRedirect();

    $invoice = Invoice::where('source', 'counter')->first();
    expect($invoice->status)->toBe(Invoice::STATUS_POSTED);
    expect($invoice->customer_id)->toBeNull();
    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('6.00');
});

test('the daily retail total books one invoice for the day', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->post(route('pos.daily-retail'), [
        'amount' => '850.00',
        'payment_method' => 'cash',
        'date' => today()->toDateString(),
        'idempotency_key' => 'daily-1',
    ])->assertRedirect();

    $invoice = Invoice::where('source', 'daily_retail')->first();
    expect((string) $invoice->total)->toBe('850.00');
    expect($invoice->lines()->count())->toBe(1);
});
