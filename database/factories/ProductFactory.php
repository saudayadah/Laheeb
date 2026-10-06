<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $size = fake()->unique()->numberBetween(10, 199);

        return [
            'name_ar' => "صاج {$size}",
            'name_en' => "Saj {$size}",
            'category' => 'saj',
            'size_cm' => $size,
            'unit' => 'loaf',
            'default_price' => '0.3000',
            'active' => true,
        ];
    }
}
