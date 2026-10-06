<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $operating = [
            ['طحين', 'Flour'],
            ['غاز', 'Gas'],
            ['ديزل', 'Diesel'],
            ['مصاريف السيارات', 'Vehicle costs'],
            ['أكياس مطبوعة', 'Printed bags'],
            ['ملح', 'Salt'],
            ['ماء', 'Water'],
            ['جلافز', 'Gloves'],
            ['ثلاجة', 'Cold storage'],
            ['أخرى', 'Other'],
        ];

        $fixed = [
            ['إيجارات', 'Rent'],
            ['كهرباء وإنترنت', 'Electricity & internet'],
            ['تأمينات اجتماعية', 'Social insurance'],
            ['تجديد إقامات', 'Residence permits'],
            ['مخالفات مرورية', 'Traffic fines'],
            ['رواتب', 'Salaries'],
        ];

        $sort = 0;

        foreach ($operating as [$ar, $en]) {
            ExpenseCategory::firstOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'kind' => 'operating', 'sort_order' => ++$sort],
            );
        }

        foreach ($fixed as [$ar, $en]) {
            ExpenseCategory::firstOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'kind' => 'fixed', 'sort_order' => ++$sort],
            );
        }
    }
}
