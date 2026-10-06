<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => 'مطعم '.fake()->unique()->word(),
            'type' => 'wholesale',
            'payment_term' => 'cash',
            'active' => true,
        ];
    }

    public function credit(?string $limit = '5000', ?int $days = 30): static
    {
        return $this->state(fn () => [
            'payment_term' => 'credit',
            'credit_limit' => $limit,
            'credit_days' => $days,
        ]);
    }

    public function inGroup(?CustomerGroup $group = null): static
    {
        return $this->state(fn () => [
            'customer_group_id' => $group?->id ?? CustomerGroup::factory(),
        ]);
    }

    public function onRoute(?DeliveryRoute $route = null, int $stop = 1): static
    {
        return $this->state(fn () => [
            'delivery_route_id' => $route?->id ?? DeliveryRoute::factory(),
            'stop_sequence' => $stop,
        ]);
    }
}
