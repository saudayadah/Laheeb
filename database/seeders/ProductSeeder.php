<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        $saj = [20 => '0.2500', 23 => '0.2500', 27 => '0.2700', 30 => '0.3000',
            32 => '0.3000', 34 => '0.3000', 35 => '0.3500', 40 => '0.3500', 50 => '0.4000'];

        foreach ($saj as $size => $price) {
            Product::firstOrCreate(
                ['category' => 'saj', 'size_cm' => $size],
                ['name_ar' => "صاج {$size}", 'name_en' => "Saj {$size}", 'default_price' => $price, 'sort_order' => ++$sort],
            );
        }

        $sized = [
            'milk' => ['حليب', 'Milk'],
            'arabic' => ['عربي', 'Arabic'],
            'wheat' => ['بر', 'Wheat'],
        ];

        $sizedPrices = [18 => '0.2500', 27 => '0.3000', 34 => '0.3500'];

        foreach ($sized as $category => [$ar, $en]) {
            foreach ($sizedPrices as $size => $price) {
                Product::firstOrCreate(
                    ['category' => $category, 'size_cm' => $size],
                    ['name_ar' => "{$ar} {$size}", 'name_en' => "{$en} {$size}", 'default_price' => $price, 'sort_order' => ++$sort],
                );
            }
        }

        Product::firstOrCreate(
            ['category' => 'red', 'size_cm' => null],
            ['name_ar' => 'خبز أحمر', 'name_en' => 'Red bread', 'default_price' => '0.3000', 'sort_order' => ++$sort],
        );
    }
}
