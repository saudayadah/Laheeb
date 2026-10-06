<?php

use App\Models\Customer;
use App\Models\DailyClose;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyCloseService;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->driver = User::factory()->create();
    $this->driver->assignRole('driver');

    $this->invoices = app(InvoiceService::class);
    $this->receipts = app(ReceiptService::class);
    $this->closes = app(DailyCloseService::class);
    $this->product = Product::factory()->create(['default_price' => '0.3000']);
    AppSettings::setMany(['vat_enabled' => false]);
});

function denoms(array $pairs): array
{
    // [denomination => count]
    return $pairs;
}

test('the full driver close: expected, variance, handover, shortage and day lock', function () {
    $customer = Customer::factory()->credit(null)->create();

    // Cash sale of 60 by the driver.
    $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $this->driver->id],
        [['product_id' => $this->product->id, 'qty' => 200]],
        $this->owner,
    );

    // Old credit then a 40 cash collection by the driver today.
    $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit'],
        [['product_id' => $this->product->id, 'qty' => 500]],
        $this->owner,
    );
    $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '40.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash', 'received_by' => $this->driver->id],
        null,
        $this->owner,
    );

    expect($this->closes->expectedForDriver($this->driver->id, today()))->toBe('100.00');

    // The driver counts only 90 (one 50, two 20s).
    $close = $this->closes->submit(
        DailyClose::TYPE_DRIVER,
        $this->driver->id,
        today(),
        denoms([50 => 1, 20 => 2]),
        null,
        $this->driver,
    );

    expect((string) $close->counted)->toBe('90.00');
    expect((string) $close->variance)->toBe('-10.00');
    expect($close->status)->toBe('pending');

    $this->closes->approve($close, $this->accountant);

    // 90 moved to the box; the 10 shortage stays on the driver.
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $this->driver->id))->toBe('10.00');
    expect(LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID))->toBe('90.00');

    // The day is now locked for this driver: no new invoices...
    expect(fn () => $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $this->driver->id],
        [['product_id' => $this->product->id, 'qty' => 10]],
        $this->owner,
    ))->toThrow(ValidationException::class);

    // ...and no new cash receipts.
    expect(fn () => $this->receipts->create(
        ['customer_id' => $customer->id, 'amount' => '5.00', 'receipt_date' => today()->toDateString(), 'method' => 'cash', 'received_by' => $this->driver->id],
        null,
        $this->owner,
    ))->toThrow(ValidationException::class);

    // And it cannot be re-submitted.
    expect(fn () => $this->closes->submit(
        DailyClose::TYPE_DRIVER, $this->driver->id, today(), denoms([100 => 1]), null, $this->driver,
    ))->toThrow(ValidationException::class);
});

test('the counter close includes counter sales and driver handovers', function () {
    // Counter cash sale: 30.
    $this->invoices->postDirect(
        ['payment_method' => 'cash', 'source' => 'counter'],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );

    // Driver hands over 50 via an approved close.
    $customer = Customer::factory()->create();
    $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash', 'driver_id' => $this->driver->id],
        [['product_id' => $this->product->id, 'qty' => 200]],
        $this->owner,
    );
    $driverClose = $this->closes->submit(DailyClose::TYPE_DRIVER, $this->driver->id, today(), denoms([50 => 1, 10 => 1]), null, $this->driver);
    $this->closes->approve($driverClose, $this->accountant);

    // Box expected = 30 (counter) + 60 (handover) = 90.
    expect($this->closes->expectedForCounter(today()))->toBe('90.00');
});

test('drivers cannot approve closes over HTTP, accountants can', function () {
    $close = $this->closes->submit(DailyClose::TYPE_DRIVER, $this->driver->id, today(), denoms([]), null, $this->driver);

    $this->actingAs($this->driver)->post(route('closes.approve', $close))->assertForbidden();
    $this->actingAs($this->accountant)->post(route('closes.approve', $close))->assertRedirect();

    expect($close->refresh()->status)->toBe('approved');
});

test('a driver can only open his own close screen', function () {
    $other = User::factory()->create();
    $other->assignRole('driver');

    $this->actingAs($this->driver)
        ->get(route('closes.create', ['type' => 'driver', 'id' => $other->id, 'date' => today()->toDateString()]))
        ->assertForbidden();

    $this->actingAs($this->driver)
        ->get(route('closes.create', ['type' => 'driver', 'id' => $this->driver->id, 'date' => today()->toDateString()]))
        ->assertOk();

    $this->actingAs($this->driver)
        ->get(route('closes.create', ['type' => 'counter', 'date' => today()->toDateString()]))
        ->assertForbidden();
});
