<?php

use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
});

test('the dashboard surfaces pending work in the attention panel', function () {
    PayrollRun::create(['period' => '2026-10', 'created_by' => $this->owner->id]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention', fn ($items) => collect($items)->pluck('key')->contains('unpaid_payroll')));
});

test('an empty business shows an all-clear attention panel', function () {
    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('attention', []));
});

test('a driver gets no attention panel at all', function () {
    $driver = User::factory()->create();
    $driver->assignRole('driver');

    $this->actingAs($driver)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('attention', null));
});
