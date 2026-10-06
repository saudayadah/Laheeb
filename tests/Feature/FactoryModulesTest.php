<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    AppSettings::setMany(['vat_enabled' => false]);
});

test('raw material stock: in minus out, low-stock flag and consumption per 1000 loaves', function () {
    $flour = RawMaterial::create(['name' => 'طحين أبيض', 'unit' => 'كيس', 'reorder_level' => '50']);

    $this->actingAs($this->accountant)->post(route('materials.movements.store'), [
        'raw_material_id' => $flour->id, 'movement_date' => today()->toDateString(),
        'direction' => 'in', 'qty' => '100',
    ])->assertRedirect();

    $this->actingAs($this->accountant)->post(route('materials.movements.store'), [
        'raw_material_id' => $flour->id, 'movement_date' => today()->toDateString(),
        'direction' => 'out', 'qty' => '60',
    ])->assertRedirect();

    expect($flour->onHand())->toBe('40.00');

    // 20,000 loaves sold this month, 60 bags used -> 3 bags per 1000 loaves.
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $product = Product::factory()->create(['default_price' => '0.3000']);
    $customer = Customer::factory()->create();
    app(InvoiceService::class)->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'cash'],
        [['product_id' => $product->id, 'qty' => 20000]],
        $owner,
    );

    $this->actingAs($this->accountant)
        ->get(route('materials.index'))
        ->assertInertia(fn ($page) => $page
            ->component('materials/index')
            ->where('materials.0.on_hand', '40.00')
            ->where('materials.0.low', true) // 40 <= 50 reorder level
            ->where('materials.0.per_1000_loaves', '3.00')
        );
});

test('vehicle costs come from expenses and the due flag fires within a week', function () {
    $vehicle = Vehicle::create(['name' => 'باص التوزيع 1', 'plate' => 'LHB 101']);
    $category = ExpenseCategory::create(['name_ar' => 'مصاريف السيارات', 'kind' => 'operating']);

    app(ExpenseService::class)->create([
        'expense_category_id' => $category->id,
        'amount' => '350', 'paid_from' => 'counter_cash', 'vehicle_id' => $vehicle->id,
    ], $this->accountant);

    VehicleMaintenance::create([
        'vehicle_id' => $vehicle->id,
        'service_date' => today()->subMonth(),
        'task' => 'تغيير زيت',
        'next_due_date' => today()->addDays(3),
    ]);

    $this->actingAs($this->accountant)
        ->get(route('vehicles.index'))
        ->assertInertia(fn ($page) => $page
            ->component('vehicles/index')
            ->where('vehicles.0.total_cost', '350.00')
            ->where('vehicles.0.due_soon', true)
        );
});

test('leave lifecycle: away, overdue, then returned', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->accountant)->post(route('leaves.store'), [
        'employee_id' => $employee->id,
        'start_date' => today()->subDays(40)->toDateString(),
        'expected_return' => today()->subDays(5)->toDateString(),
    ])->assertRedirect();

    $leave = EmployeeLeave::first();
    expect($leave->isOverdue())->toBeTrue();

    $this->actingAs($this->accountant)->post(route('leaves.return', $leave), [
        'actual_return' => today()->toDateString(),
    ])->assertRedirect();

    expect($leave->refresh()->isOverdue())->toBeFalse();
    expect($leave->actual_return->toDateString())->toBe(today()->toDateString());
});

test('drivers cannot reach the factory screens', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');

    $this->actingAs($driver)->get('/materials')->assertForbidden();
    $this->actingAs($driver)->get('/vehicles')->assertForbidden();
    $this->actingAs($driver)->get('/leaves')->assertForbidden();
});

test('an expense recorded against a vehicle still posts normally', function () {
    $vehicle = Vehicle::create(['name' => 'باص 2']);
    $category = ExpenseCategory::create(['name_ar' => 'ديزل', 'kind' => 'operating']);

    $this->actingAs($this->accountant)->post(route('expenses.store'), [
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $category->id,
        'amount' => '120',
        'paid_from' => 'counter_cash',
        'vehicle_id' => $vehicle->id,
    ])->assertRedirect();

    expect(Expense::first()->vehicle_id)->toBe($vehicle->id);
});
