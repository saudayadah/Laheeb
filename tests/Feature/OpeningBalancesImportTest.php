<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Imports\OpeningBalancesImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->importer = new OpeningBalancesImporter;
});

function obRows(array ...$rows): Collection
{
    return collect($rows)->map(fn ($row) => collect($row));
}

test('validation catches unknown types, missing entities and bad amounts', function () {
    $result = $this->importer->validateRows(obRows(
        ['type' => 'عميل', 'code_or_name' => '999', 'amount' => '100'],   // no such customer
        ['type' => 'كذا', 'code_or_name' => 'x', 'amount' => '100'],      // bad type
        ['type' => 'مورد', 'code_or_name' => 'مورد جديد', 'amount' => ''], // missing amount
    ), $this->owner);

    expect($result[0]['errors'])->not->toBeEmpty();
    expect($result[1]['errors'])->not->toBeEmpty();
    expect($result[2]['errors'])->not->toBeEmpty();
});

test('commit posts each account on its correct side', function () {
    $customer = Customer::factory()->create(['code' => '101']);
    $employee = Employee::factory()->create(['name_ar' => 'خباز 1']);
    $driver = User::factory()->create(['name' => 'سائق أحمد']);
    $driver->assignRole('driver');

    $rows = obRows(
        ['type' => 'عميل', 'code_or_name' => '101', 'amount' => '4500', 'date' => '2026-01-01', 'notes' => 'قديم'],
        ['type' => 'مورد', 'code_or_name' => 'مطاحن الدقيق', 'amount' => '12000'],
        ['type' => 'موظف', 'code_or_name' => 'خباز 1', 'amount' => '800'],
        ['type' => 'سائق', 'code_or_name' => 'سائق أحمد', 'amount' => '150'],
    );

    $result = $this->importer->validateRows($rows, $this->owner);
    expect(collect($result)->every(fn ($r) => $r['errors'] === []))->toBeTrue();

    $count = DB::transaction(fn () => $this->importer->commit($result, $this->owner));
    expect($count)->toBe(4);

    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('4500.00');
    expect(Supplier::where('name', 'مطاحن الدقيق')->first()->payable())->toBe('12000.00');
    expect($employee->outstanding())->toBe('800.00');
    expect(LedgerEntry::balance(LedgerEntry::DRIVER, $driver->id))->toBe('150.00');

    // A second attempt for the same accounts is rejected row-by-row.
    $again = $this->importer->validateRows($rows, $this->owner);
    expect(collect($again)->every(fn ($r) => $r['errors'] !== []))->toBeTrue();
});

test('a negative customer amount books a credit (advance payment)', function () {
    $customer = Customer::factory()->create(['code' => '102']);

    $result = $this->importer->validateRows(obRows(
        ['type' => 'customer', 'code_or_name' => '102', 'amount' => '-300'],
    ), $this->owner);

    DB::transaction(fn () => $this->importer->commit($result, $this->owner));

    expect(LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id))->toBe('-300.00');
});
