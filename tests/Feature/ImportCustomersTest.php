<?php

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\Price;
use App\Models\User;
use App\Services\Imports\CustomersImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('owner');
    $this->importer = new CustomersImporter;
});

function rows(array ...$rows): Collection
{
    return collect($rows)->map(fn ($row) => collect($row));
}

test('validation flags missing names and duplicate codes', function () {
    Customer::factory()->create(['code' => '500']);

    $result = $this->importer->validateRows(rows(
        ['code' => '101', 'name' => 'مطعم الاختبار'],
        ['code' => '102', 'name' => null],          // missing name
        ['code' => '500', 'name' => 'مكرر قديم'],   // exists in DB
        ['code' => '101', 'name' => 'مكرر بالملف'], // duplicate within the file
    ), $this->user);

    expect($result)->toHaveCount(4);
    expect($result[0]['errors'])->toBeEmpty();
    expect($result[1]['errors'])->not->toBeEmpty();
    expect($result[2]['errors'])->not->toBeEmpty();
    expect($result[3]['errors'])->not->toBeEmpty();
});

test('commit creates customers with groups, routes and the default price', function () {
    $result = $this->importer->validateRows(rows(
        [
            'code' => '101', 'name' => 'مطعم الريان', 'group' => 'مجموعة الريان',
            'route' => 'وسط الرياض', 'type' => 'جملة', 'payment_term' => 'آجل',
            'credit_limit' => '5000', 'credit_days' => '30', 'price' => '0.30',
            'phone' => '0501234567',
        ],
        ['name' => 'بقالة النور', 'payment_term' => 'نقدي', 'price' => '0.27'],
    ), $this->user);

    $valid = array_values(array_filter($result, fn ($r) => $r['errors'] === []));
    $count = $this->importer->commit($valid, $this->user);

    expect($count)->toBe(2);
    expect(CustomerGroup::where('name', 'مجموعة الريان')->exists())->toBeTrue();
    expect(DeliveryRoute::where('name', 'وسط الرياض')->exists())->toBeTrue();

    $rayyan = Customer::where('code', '101')->first();
    expect($rayyan->payment_term)->toBe('credit');
    expect((string) $rayyan->credit_limit)->toBe('5000.00');
    expect(Price::where('customer_id', $rayyan->id)->whereNull('product_id')->value('price'))->toBe('0.3000');

    // The second row had no code: it gets an auto-generated one.
    $noor = Customer::where('name', 'بقالة النور')->first();
    expect($noor->code)->not->toBeNull();
    expect($noor->payment_term)->toBe('cash');
});

test('validation rejects a non-numeric price without blocking other rows', function () {
    $result = $this->importer->validateRows(rows(
        ['name' => 'عميل سليم', 'price' => '0.35'],
        ['name' => 'عميل خاطئ', 'price' => 'abc'],
    ), $this->user);

    expect($result[0]['errors'])->toBeEmpty();
    expect($result[1]['errors'])->not->toBeEmpty();
});
