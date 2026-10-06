<?php

use App\Models\Customer;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('a driver cannot open the customers screen', function () {
    $this->actingAs(makeUser('driver'))
        ->get('/customers')
        ->assertForbidden();
});

test('a driver cannot open users, products, imports or settings', function () {
    $driver = makeUser('driver');

    $this->actingAs($driver)->get('/users')->assertForbidden();
    $this->actingAs($driver)->get('/products')->assertForbidden();
    $this->actingAs($driver)->get('/imports')->assertForbidden();
    $this->actingAs($driver)->get('/settings/bakery')->assertForbidden();
});

test('a clerk sees customers but never prices', function () {
    $customer = Customer::factory()->create();
    Price::create(['customer_id' => $customer->id, 'price' => '0.3000', 'effective_from' => today()]);

    $this->actingAs(makeUser('clerk'))
        ->get('/customers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/index')
            ->where('canViewPrices', false)
            ->where('customers.data.0.default_price', null)
        );
});

test('a clerk cannot create a price', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(makeUser('clerk'))
        ->post('/prices', [
            'customer_id' => $customer->id,
            'price' => '0.5000',
            'effective_from' => today()->toDateString(),
        ])
        ->assertForbidden();

    expect(Price::count())->toBe(0);
});

test('an accountant cannot change system settings', function () {
    $this->actingAs(makeUser('accountant'))
        ->get('/settings/bakery')
        ->assertForbidden();
});

test('an accountant cannot manage products', function () {
    $this->actingAs(makeUser('accountant'))
        ->post('/products', [
            'name_ar' => 'صاج 99',
            'category' => 'saj',
            'size_cm' => 99,
            'unit' => 'loaf',
            'default_price' => '0.30',
        ])
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

test('the owner can do all of the above', function () {
    $owner = makeUser('owner');
    $customer = Customer::factory()->create();

    $this->actingAs($owner)->get('/customers')->assertOk();
    $this->actingAs($owner)->get('/settings/bakery')->assertOk();

    $this->actingAs($owner)->post('/prices', [
        'customer_id' => $customer->id,
        'price' => '0.5000',
        'effective_from' => today()->toDateString(),
    ])->assertRedirect();

    expect(Price::count())->toBe(1);
});

test('a deactivated user is logged out on the next request', function () {
    $user = makeUser('owner');
    $user->update(['active' => false]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/login');
});
