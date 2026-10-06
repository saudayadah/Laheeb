<?php

use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\StandingOrder;
use App\Models\User;
use App\Services\OrderGridService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->clerk = User::factory()->create();
    $this->clerk->assignRole('clerk');
    $this->service = app(OrderGridService::class);
    $this->product = Product::factory()->create();
    $this->customer = Customer::factory()->create();
});

test('saving a cell creates the order and line, zero clears it', function () {
    $this->actingAs($this->clerk)->postJson('/orders/cells', [
        'date' => today()->toDateString(),
        'cells' => [['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 150]],
    ])->assertOk();

    $order = Order::whereDate('order_date', today())->where('customer_id', $this->customer->id)->first();
    expect($order)->not->toBeNull();
    expect($order->lines()->where('product_id', $this->product->id)->value('qty'))->toBe(150);

    // Overwrite with a new quantity.
    $this->actingAs($this->clerk)->postJson('/orders/cells', [
        'date' => today()->toDateString(),
        'cells' => [['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 200]],
    ])->assertOk();

    expect(OrderLine::count())->toBe(1);
    expect($order->lines()->value('qty'))->toBe(200);

    // Zero clears the line.
    $this->actingAs($this->clerk)->postJson('/orders/cells', [
        'date' => today()->toDateString(),
        'cells' => [['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 0]],
    ])->assertOk();

    expect(OrderLine::count())->toBe(0);
});

test('a confirmed day cannot be edited', function () {
    Order::create([
        'order_date' => today(),
        'customer_id' => $this->customer->id,
        'status' => Order::STATUS_CONFIRMED,
    ]);

    $this->actingAs($this->clerk)->postJson('/orders/cells', [
        'date' => today()->toDateString(),
        'cells' => [['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 10]],
    ])->assertStatus(422);

    expect(OrderLine::count())->toBe(0);
});

test('fill from standing orders fills only the matching weekday and only empty cells', function () {
    $second = Product::factory()->create();

    StandingOrder::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'weekday' => today()->dayOfWeek,
        'qty' => 100,
    ]);
    StandingOrder::create([
        'customer_id' => $this->customer->id,
        'product_id' => $second->id,
        'weekday' => (today()->dayOfWeek + 1) % 7, // different weekday: must not apply
        'qty' => 999,
    ]);

    // One cell already has a manually entered quantity: must not be overwritten.
    $manual = Customer::factory()->create();
    StandingOrder::create([
        'customer_id' => $manual->id,
        'product_id' => $this->product->id,
        'weekday' => today()->dayOfWeek,
        'qty' => 50,
    ]);
    $this->service->saveCells(today(), [
        ['customer_id' => $manual->id, 'product_id' => $this->product->id, 'qty' => 70],
    ], $this->clerk->id);

    $filled = $this->service->fill(today(), 'standing', $this->clerk->id);

    expect($filled)->toBe(1);
    $cells = $this->service->grid(today(), withPrices: false)['cells'];
    expect($cells["{$this->customer->id}:{$this->product->id}"])->toBe(100);
    expect($cells)->not->toHaveKey("{$this->customer->id}:{$second->id}");
    expect($cells["{$manual->id}:{$this->product->id}"])->toBe(70);
});

test('fill from yesterday copies quantities', function () {
    $this->service->saveCells(today()->subDay(), [
        ['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 300],
    ], $this->clerk->id);

    $filled = $this->service->fill(today(), 'yesterday', $this->clerk->id);

    expect($filled)->toBe(1);
    $cells = $this->service->grid(today(), withPrices: false)['cells'];
    expect($cells["{$this->customer->id}:{$this->product->id}"])->toBe(300);
});

test('production summary totals per product and per route', function () {
    $route = DeliveryRoute::factory()->create();
    $onRoute = Customer::factory()->onRoute($route)->create();

    $this->service->saveCells(today(), [
        ['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 100],
        ['customer_id' => $onRoute->id, 'product_id' => $this->product->id, 'qty' => 250],
    ], $this->clerk->id);

    $summary = $this->service->productionSummary(today());

    expect($summary['totalsByProduct'][$this->product->id])->toBe(350);
    expect($summary['grandTotal'])->toBe(350);

    $routeRow = collect($summary['routes'])->firstWhere('name', $route->name);
    expect($routeRow['total'])->toBe(250);
});

test('the loading sheet lists stops in order with totals', function () {
    $route = DeliveryRoute::factory()->create();
    $stop2 = Customer::factory()->onRoute($route, 2)->create();
    $stop1 = Customer::factory()->onRoute($route, 1)->create();

    $this->service->saveCells(today(), [
        ['customer_id' => $stop2->id, 'product_id' => $this->product->id, 'qty' => 80],
        ['customer_id' => $stop1->id, 'product_id' => $this->product->id, 'qty' => 120],
    ], $this->clerk->id);

    $sheet = $this->service->loadingSheet(today(), $route->id);

    expect($sheet['rows'])->toHaveCount(2);
    expect($sheet['rows'][0]['customer']['id'])->toBe($stop1->id);
    expect($sheet['rows'][1]['customer']['id'])->toBe($stop2->id);
    expect($sheet['totals'][$this->product->id])->toBe(200);
    expect($sheet['grandTotal'])->toBe(200);
});

test('a driver cannot open or edit the grid', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');

    $this->actingAs($driver)->get('/orders')->assertForbidden();
    $this->actingAs($driver)->postJson('/orders/cells', [
        'date' => today()->toDateString(),
        'cells' => [['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'qty' => 5]],
    ])->assertForbidden();
});

test('the grid screen renders for a clerk', function () {
    $this->actingAs($this->clerk)
        ->get('/orders?date='.today()->toDateString())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('orders/grid')
            ->where('date', today()->toDateString())
        );
});
