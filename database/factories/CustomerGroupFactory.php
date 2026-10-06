<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مجموعة '.fake()->unique()->word(),
            'credit_scope' => 'group',
            'active' => true,
        ];
    }
}
