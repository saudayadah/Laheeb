<?php

namespace Database\Factories;

use App\Models\DeliveryRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRoute>
 */
class DeliveryRouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'خط '.fake()->unique()->word(),
            'city' => 'الرياض',
            'active' => true,
        ];
    }
}
