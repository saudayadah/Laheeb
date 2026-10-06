<?php

namespace App\Services\Imports;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\Price;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomersImporter extends BaseImporter
{
    private const TYPES = [
        'wholesale' => 'wholesale', 'جملة' => 'wholesale',
        'retail' => 'retail', 'تجزئة' => 'retail',
        'walkin' => 'walkin', 'كاونتر' => 'walkin',
    ];

    private const TERMS = [
        'cash' => 'cash', 'نقدي' => 'cash', 'نقد' => 'cash', 'كاش' => 'cash',
        'credit' => 'credit', 'آجل' => 'credit', 'اجل' => 'credit',
    ];

    public function type(): string
    {
        return 'customers';
    }

    public function headings(): array
    {
        return [
            'code', 'name', 'name_en', 'group', 'route', 'stop_sequence', 'type',
            'payment_term', 'credit_limit', 'credit_days', 'price', 'phone',
            'whatsapp', 'city', 'vat_number', 'cr_number', 'national_address', 'notes',
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['101', 'مطعم الريان', 'Al Rayyan Restaurant', 'مجموعة الريان', 'وسط الرياض', '1', 'جملة', 'آجل', '5000', '30', '0.30', '0501234567', '0501234567', 'الرياض', '', '', '', ''],
            ['', 'بقالة النور', '', '', 'شمال الرياض', '2', 'جملة', 'نقدي', '', '', '0.27', '0557654321', '', 'الرياض', '', '', '', ''],
        ];
    }

    public function validateRows(Collection $rows, User $user): array
    {
        $existingCodes = Customer::withTrashed()->pluck('code')->flip();
        $seenCodes = [];
        $result = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $errors = [];

            $data = [
                'code' => $this->str($row['code'] ?? null),
                'name' => $this->str($row['name'] ?? null),
                'name_en' => $this->str($row['name_en'] ?? null),
                'group' => $this->str($row['group'] ?? null),
                'route' => $this->str($row['route'] ?? null),
                'stop_sequence' => $this->num($row['stop_sequence'] ?? null),
                'type' => $this->str($row['type'] ?? null),
                'payment_term' => $this->str($row['payment_term'] ?? null),
                'credit_limit' => $this->num($row['credit_limit'] ?? null),
                'credit_days' => $this->num($row['credit_days'] ?? null),
                'price' => $this->num($row['price'] ?? null),
                'phone' => $this->str($row['phone'] ?? null),
                'whatsapp' => $this->str($row['whatsapp'] ?? null),
                'city' => $this->str($row['city'] ?? null),
                'vat_number' => $this->str($row['vat_number'] ?? null),
                'cr_number' => $this->str($row['cr_number'] ?? null),
                'national_address' => $this->str($row['national_address'] ?? null),
                'notes' => $this->str($row['notes'] ?? null),
            ];

            if ($data['name'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.name')]);
            }

            if ($data['code'] !== null) {
                if (isset($existingCodes[$data['code']]) || isset($seenCodes[$data['code']])) {
                    $errors[] = __('validation.unique', ['attribute' => __('validation.attributes.code')]);
                }
                $seenCodes[$data['code']] = true;
            }

            if ($data['type'] !== null && ! isset(self::TYPES[mb_strtolower($data['type'])])) {
                $errors[] = __('validation.in', ['attribute' => __('validation.attributes.type')]);
            }

            if ($data['payment_term'] !== null && ! isset(self::TERMS[mb_strtolower($data['payment_term'])])) {
                $errors[] = __('validation.in', ['attribute' => __('validation.attributes.payment_term')]);
            }

            if ($this->str($row['price'] ?? null) !== null && $data['price'] === null) {
                $errors[] = __('validation.numeric', ['attribute' => __('validation.attributes.price')]);
            }

            if ($this->str($row['credit_limit'] ?? null) !== null && $data['credit_limit'] === null) {
                $errors[] = __('validation.numeric', ['attribute' => __('validation.attributes.credit_limit')]);
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
        $nextCode = (int) Customer::nextCode();

        foreach ($validRows as $rowData) {
            $data = $rowData['data'];

            $group = $data['group'] !== null
                ? CustomerGroup::firstOrCreate(['name' => $data['group']])
                : null;

            $route = $data['route'] !== null
                ? DeliveryRoute::firstOrCreate(['name' => $data['route']])
                : null;

            $code = $data['code'] ?? (string) $nextCode++;

            $customer = Customer::create([
                'code' => $code,
                'name' => $data['name'],
                'name_en' => $data['name_en'],
                'customer_group_id' => $group?->id,
                'type' => self::TYPES[mb_strtolower($data['type'] ?? '')] ?? 'wholesale',
                'payment_term' => self::TERMS[mb_strtolower($data['payment_term'] ?? '')] ?? 'cash',
                'credit_limit' => $data['credit_limit'],
                'credit_days' => $data['credit_days'] !== null ? (int) $data['credit_days'] : null,
                'delivery_route_id' => $route?->id,
                'stop_sequence' => $data['stop_sequence'] !== null ? (int) $data['stop_sequence'] : 0,
                'city' => $data['city'],
                'phone' => $data['phone'],
                'whatsapp' => $data['whatsapp'],
                'vat_number' => $data['vat_number'],
                'cr_number' => $data['cr_number'],
                'national_address' => $data['national_address'],
                'notes' => $data['notes'],
            ]);

            if ($data['price'] !== null) {
                Price::create([
                    'customer_id' => $customer->id,
                    'price' => $data['price'],
                    'effective_from' => today(),
                    'created_by' => $user->id,
                ]);
            }

            $count++;
        }

        return $count;
    }
}
