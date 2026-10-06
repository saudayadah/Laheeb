<?php

namespace App\Services\Imports;

use App\Models\Customer;
use App\Models\Product;
use App\Models\StandingOrder;
use App\Models\User;
use Illuminate\Support\Collection;

class StandingOrdersImporter extends BaseImporter
{
    private const WEEKDAYS = [
        'الأحد' => 0, 'الاحد' => 0, 'sunday' => 0, '0' => 0,
        'الاثنين' => 1, 'الإثنين' => 1, 'monday' => 1, '1' => 1,
        'الثلاثاء' => 2, 'tuesday' => 2, '2' => 2,
        'الأربعاء' => 3, 'الاربعاء' => 3, 'wednesday' => 3, '3' => 3,
        'الخميس' => 4, 'thursday' => 4, '4' => 4,
        'الجمعة' => 5, 'friday' => 5, '5' => 5,
        'السبت' => 6, 'saturday' => 6, '6' => 6,
    ];

    public function type(): string
    {
        return 'standing_orders';
    }

    public function headings(): array
    {
        return ['customer_code', 'product', 'weekday', 'qty'];
    }

    public function exampleRows(): array
    {
        return [
            ['101', 'صاج 30', 'السبت', '200'],
            ['101', 'صاج 30', 'الأحد', '150'],
        ];
    }

    public function validateRows(Collection $rows, User $user): array
    {
        $customers = Customer::pluck('id', 'code');
        $products = Product::query()->get(['id', 'name_ar', 'name_en']);
        $byName = [];
        foreach ($products as $product) {
            $byName[mb_strtolower(trim($product->name_ar))] = $product->id;
            if ($product->name_en) {
                $byName[mb_strtolower(trim($product->name_en))] = $product->id;
            }
        }

        $result = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $errors = [];

            $data = [
                'customer_code' => $this->str($row['customer_code'] ?? null),
                'product' => $this->str($row['product'] ?? null),
                'weekday' => $this->str($row['weekday'] ?? null),
                'qty' => $this->num($row['qty'] ?? null),
            ];

            if ($data['customer_code'] === null || ! isset($customers[$data['customer_code']])) {
                $errors[] = __('validation.exists', ['attribute' => __('validation.attributes.customer_id')]);
            }

            if ($data['product'] === null || ! isset($byName[mb_strtolower($data['product'])])) {
                $errors[] = __('validation.exists', ['attribute' => __('validation.attributes.product_id')]);
            }

            if ($data['weekday'] === null || ! isset(self::WEEKDAYS[mb_strtolower($data['weekday'])])) {
                $errors[] = __('validation.in', ['attribute' => __('validation.attributes.weekday')]);
            }

            if ($data['qty'] === null || (int) $data['qty'] < 1) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.qty')]);
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
        $customers = Customer::pluck('id', 'code');
        $products = Product::query()->get(['id', 'name_ar', 'name_en']);
        $byName = [];
        foreach ($products as $product) {
            $byName[mb_strtolower(trim($product->name_ar))] = $product->id;
            if ($product->name_en) {
                $byName[mb_strtolower(trim($product->name_en))] = $product->id;
            }
        }

        $count = 0;

        foreach ($validRows as $rowData) {
            $data = $rowData['data'];

            StandingOrder::updateOrCreate(
                [
                    'customer_id' => $customers[$data['customer_code']],
                    'product_id' => $byName[mb_strtolower($data['product'])],
                    'weekday' => self::WEEKDAYS[mb_strtolower($data['weekday'])],
                ],
                [
                    'qty' => (int) $data['qty'],
                    'active' => true,
                ],
            );

            $count++;
        }

        return $count;
    }
}
