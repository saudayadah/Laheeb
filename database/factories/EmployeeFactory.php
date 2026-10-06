<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_ar' => 'موظف '.fake()->unique()->numberBetween(1, 9999),
            'job' => 'worker',
            'basic_salary' => '3000.00',
            'active' => true,
        ];
    }
}
