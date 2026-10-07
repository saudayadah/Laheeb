<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
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
            'product_category_id' => ProductCategory::firstOrCreate(
                ['name_ar' => 'صاج'],
                ['name_en' => 'Saj', 'sort_order' => 1],
            )->id,
            'size_cm' => $size,
            'unit' => 'loaf',
            'default_price' => '0.3000',
            'active' => true,
        ];
    }
}
