<?php

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Price;
use App\Models\Product;
use App\Services\PriceResolver;

beforeEach(function () {
    $this->resolver = new PriceResolver;
    $this->group = CustomerGroup::factory()->create();
    $this->customer = Customer::factory()->inGroup($this->group)->create();
    $this->product = Product::factory()->create(['default_price' => '0.2000']);
});

test('falls back to the product default price when nothing else is set', function () {
    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.2000');
});

test('a dated product price beats the product default', function () {
    Price::create(['product_id' => $this->product->id, 'price' => '0.2200', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.2200');
});

test('a group default price beats the product price', function () {
    Price::create(['product_id' => $this->product->id, 'price' => '0.2200', 'effective_from' => today()->subDay()]);
    Price::create(['customer_group_id' => $this->group->id, 'price' => '0.2500', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.2500');
});

test('a group+product price beats the group default', function () {
    Price::create(['customer_group_id' => $this->group->id, 'price' => '0.2500', 'effective_from' => today()->subDay()]);
    Price::create(['customer_group_id' => $this->group->id, 'product_id' => $this->product->id, 'price' => '0.2700', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.2700');
});

test('a customer default price beats all group prices', function () {
    Price::create(['customer_group_id' => $this->group->id, 'product_id' => $this->product->id, 'price' => '0.2700', 'effective_from' => today()->subDay()]);
    Price::create(['customer_id' => $this->customer->id, 'price' => '0.3000', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.3000');
});

test('a customer+product price beats everything', function () {
    Price::create(['customer_id' => $this->customer->id, 'price' => '0.3000', 'effective_from' => today()->subDay()]);
    Price::create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'price' => '0.3500', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.3500');
});

test('within a level the latest effective_from on or before the date wins', function () {
    Price::create(['customer_id' => $this->customer->id, 'price' => '0.2500', 'effective_from' => today()->subMonths(2)]);
    Price::create(['customer_id' => $this->customer->id, 'price' => '0.3000', 'effective_from' => today()->subDays(5)]);
    Price::create(['customer_id' => $this->customer->id, 'price' => '0.4000', 'effective_from' => today()->addDays(5)]);

    // Today: the 0.30 row applies; the future 0.40 row does not.
    expect($this->resolver->resolve($this->customer, $this->product))->toBe('0.3000');

    // Historical date: the oldest row applies.
    expect($this->resolver->resolve($this->customer, $this->product, today()->subMonth()))->toBe('0.2500');

    // Future date: the 0.40 row applies.
    expect($this->resolver->resolve($this->customer, $this->product, today()->addWeek()))->toBe('0.4000');
});

test('a customer without a group never sees group prices', function () {
    $loner = Customer::factory()->create();
    Price::create(['customer_group_id' => $this->group->id, 'price' => '0.9000', 'effective_from' => today()->subDay()]);

    expect($this->resolver->resolve($loner, $this->product))->toBe('0.2000');
});

test('resolveMany matches resolve for each product', function () {
    $second = Product::factory()->create(['default_price' => '0.5000']);
    Price::create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'price' => '0.3500', 'effective_from' => today()->subDay()]);

    $prices = $this->resolver->resolveMany($this->customer, collect([$this->product, $second]));

    expect($prices[$this->product->id])->toBe('0.3500');
    expect($prices[$second->id])->toBe('0.5000');
});
