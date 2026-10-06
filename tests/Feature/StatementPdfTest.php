<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the accountant downloads a real PDF statement', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $customer = Customer::factory()->create();

    $response = $this->actingAs($accountant)->get(route('statements.pdf', $customer));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('drivers cannot download statements', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $customer = Customer::factory()->create();

    $this->actingAs($driver)->get(route('statements.pdf', $customer))->assertForbidden();
});
