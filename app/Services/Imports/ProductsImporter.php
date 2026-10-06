<?php

namespace App\Services\Imports;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductsImporter extends BaseImporter
{
    private const CATEGORIES = [
        'saj' => 'saj', 'صاج' => 'saj',
        'milk' => 'milk', 'حليب' => 'milk',
        'arabic' => 'arabic', 'عربي' => 'arabic',
        'wheat' => 'wheat', 'بر' => 'wheat', 'whole-wheat' => 'wheat',
        'red' => 'red', 'أحمر' => 'red', 'خبز أحمر' => 'red', 'احمر' => 'red',
    ];

    public function type(): string
    {
        return 'products';
    }

    public function headings(): array
    {
        return ['name_ar', 'name_en', 'category', 'size_cm', 'default_price', 'vat_rate', 'sort_order'];
    }

    public function exampleRows(): array
    {
        return [
            ['صاج 30', 'Saj 30', 'صاج', '30', '0.30', '', '1'],
            ['حليب 27', 'Milk 27', 'حليب', '27', '0.35', '', '2'],
        ];
    }

    public function validateRows(Collection $rows, User $user): array
    {
        $result = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $errors = [];

            $data = [
                'name_ar' => $this->str($row['name_ar'] ?? null),
                'name_en' => $this->str($row['name_en'] ?? null),
                'category' => $this->str($row['category'] ?? null),
                'size_cm' => $this->num($row['size_cm'] ?? null),
                'default_price' => $this->num($row['default_price'] ?? null),
                'vat_rate' => $this->num($row['vat_rate'] ?? null),
                'sort_order' => $this->num($row['sort_order'] ?? null),
            ];

            if ($data['name_ar'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.name_ar')]);
            }

            if ($data['category'] === null || ! isset(self::CATEGORIES[mb_strtolower($data['category'])])) {
                $errors[] = __('validation.in', ['attribute' => __('validation.attributes.category')]);
            }

            if ($data['default_price'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.default_price')]);
            }

            $result[] = [
                'row' => $index + 1,
                'data' => $data,
                'errors' => $errors,
            ];
        }

        return $result;
    }

    public function commit(array $validRows, User $user): int
    {
        $count = 0;

        foreach ($validRows as $rowData) {
            $data = $rowData['data'];

            Product::create([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'category' => self::CATEGORIES[mb_strtolower($data['category'])],
                'size_cm' => $data['size_cm'] !== null ? (int) $data['size_cm'] : null,
                'default_price' => $data['default_price'],
                'vat_rate' => $data['vat_rate'],
                'sort_order' => $data['sort_order'] !== null ? (int) $data['sort_order'] : 0,
            ]);

            $count++;
        }

        return $count;
    }
}
