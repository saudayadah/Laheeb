<?php

namespace App\Services\Imports;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Collection;

class ProductsImporter extends BaseImporter
{
    /**
     * Legacy slugs and common spellings mapped to canonical Arabic names.
     * Unknown names are created as new categories — the catalogue is open.
     */
    private const SYNONYMS = [
        'saj' => 'صاج',
        'milk' => 'حليب', 'حليب' => 'حليب',
        'arabic' => 'عربي',
        'wheat' => 'بر', 'whole-wheat' => 'بر',
        'red' => 'خبز أحمر', 'أحمر' => 'خبز أحمر', 'احمر' => 'خبز أحمر',
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
            ['معمول تمر', 'Date maamoul', 'معجنات', '', '1.50', '', '2'],
        ];
    }

    public function validateRows(Collection $rows, User $user): array
    {
        $seen = [];
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

            if ($data['category'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.category')]);
            }

            if ($data['default_price'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.default_price')]);
            }

            // The same (category, name_ar) pair twice in one file would collide on commit.
            if ($data['name_ar'] !== null && $data['category'] !== null) {
                $key = mb_strtolower($this->canonicalCategory($data['category'])).'|'.$data['name_ar'];

                if (isset($seen[$key])) {
                    $errors[] = __('validation.unique', ['attribute' => __('validation.attributes.name_ar')]);
                }

                $seen[$key] = true;
            }

            foreach (['name_ar' => 100, 'name_en' => 100] as $field => $max) {
                if ($data[$field] !== null && mb_strlen($data[$field]) > $max) {
                    $errors[] = __('validation.max.string', ['attribute' => __("validation.attributes.{$field}"), 'max' => $max]);
                }
            }

            if ($data['vat_rate'] !== null && ((float) $data['vat_rate'] < 0 || (float) $data['vat_rate'] > 100)) {
                $errors[] = __('validation.between.numeric', ['attribute' => __('validation.attributes.vat_rate'), 'min' => 0, 'max' => 100]);
            }

            if ($data['default_price'] !== null && (float) $data['default_price'] < 0) {
                $errors[] = __('validation.min.numeric', ['attribute' => __('validation.attributes.default_price'), 'min' => 0]);
            }

            foreach (['size_cm', 'sort_order'] as $field) {
                if ($data[$field] !== null && (! ctype_digit($data[$field]) || (float) $data[$field] > 65535)) {
                    $errors[] = __('validation.between.numeric', ['attribute' => __("validation.attributes.{$field}"), 'min' => 0, 'max' => 65535]);
                }
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

            $category = $this->resolveCategory($data['category']);

            // Upsert on the (category, name_ar) unique key — re-uploading the same
            // file updates rows instead of crashing, and revives soft-deleted ones.
            $product = Product::withTrashed()->updateOrCreate(
                ['product_category_id' => $category->id, 'name_ar' => $data['name_ar']],
                [
                    'name_en' => $data['name_en'],
                    'size_cm' => $data['size_cm'] !== null ? (int) $data['size_cm'] : null,
                    'default_price' => $data['default_price'],
                    'vat_rate' => $data['vat_rate'],
                    'sort_order' => $data['sort_order'] !== null ? (int) $data['sort_order'] : 0,
                ],
            );

            if ($product->trashed()) {
                $product->restore();
            }

            $count++;
        }

        return $count;
    }

    private function canonicalCategory(string $name): string
    {
        return self::SYNONYMS[mb_strtolower(trim($name))] ?? trim($name);
    }

    private function resolveCategory(string $name): ProductCategory
    {
        $canonical = $this->canonicalCategory($name);

        $existing = ProductCategory::query()->where('name_ar', $canonical)->first()
            ?? ProductCategory::query()->whereRaw('LOWER(name_en) = ?', [mb_strtolower($canonical)])->first();

        return $existing ?? ProductCategory::create([
            'name_ar' => $canonical,
            'sort_order' => (int) ProductCategory::max('sort_order') + 1,
        ]);
    }
}
