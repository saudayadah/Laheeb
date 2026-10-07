<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catSort = 0;
        $categories = [];

        foreach (['صاج' => 'Saj', 'حليب' => 'Milk', 'عربي' => 'Arabic', 'بر' => 'Whole-wheat', 'خبز أحمر' => 'Red bread'] as $ar => $en) {
            $categories[$ar] = ProductCategory::firstOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'sort_order' => ++$catSort],
            )->id;
        }

        $sort = 0;

        $saj = [20 => '0.2500', 23 => '0.2500', 27 => '0.2700', 30 => '0.3000',
            32 => '0.3000', 34 => '0.3000', 35 => '0.3500', 40 => '0.3500', 50 => '0.4000'];

        foreach ($saj as $size => $price) {
            Product::firstOrCreate(
                ['product_category_id' => $categories['صاج'], 'name_ar' => "صاج {$size}"],
                ['name_en' => "Saj {$size}", 'size_cm' => $size, 'default_price' => $price, 'sort_order' => ++$sort],
            );
        }

        $sized = [
            'حليب' => 'Milk',
            'عربي' => 'Arabic',
            'بر' => 'Wheat',
        ];

        $sizedPrices = [18 => '0.2500', 27 => '0.3000', 34 => '0.3500'];

        foreach ($sized as $ar => $en) {
            foreach ($sizedPrices as $size => $price) {
                Product::firstOrCreate(
                    ['product_category_id' => $categories[$ar], 'name_ar' => "{$ar} {$size}"],
                    ['name_en' => "{$en} {$size}", 'size_cm' => $size, 'default_price' => $price, 'sort_order' => ++$sort],
                );
            }
        }

        Product::firstOrCreate(
            ['product_category_id' => $categories['خبز أحمر'], 'name_ar' => 'خبز أحمر'],
            ['name_en' => 'Red bread', 'size_cm' => null, 'default_price' => '0.3000', 'sort_order' => ++$sort],
        );
    }
}
