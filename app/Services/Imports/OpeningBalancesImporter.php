<?php

namespace App\Services\Imports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PostingService;
use App\Support\Money;
use DateTime;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class OpeningBalancesImporter extends BaseImporter
{
    /** Locale-independent marker used to block double imports. */
    public const MARKER = 'رصيد افتتاحي';

    private const TYPES = [
        'customer' => 'customer', 'عميل' => 'customer',
        'supplier' => 'supplier', 'مورد' => 'supplier', 'مورّد' => 'supplier',
        'employee' => 'employee', 'موظف' => 'employee',
        'driver' => 'driver', 'سائق' => 'driver',
    ];

    public function type(): string
    {
        return 'opening_balances';
    }

    public function headings(): array
    {
        return ['type', 'code_or_name', 'amount', 'date', 'notes'];
    }

    public function exampleRows(): array
    {
        return [
            ['عميل', '101', '4500', today()->startOfYear()->toDateString(), 'رصيد من الدفتر القديم'],
            ['مورد', 'مطاحن الدقيق الأولى', '12000', '', 'دين طحين'],
            ['موظف', 'خباز 1', '800', '', 'سلفة قديمة'],
        ];
    }

    public function validateRows(Collection $rows, User $user): array
    {
        $seenAccounts = [];
        $result = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $errors = [];

            $rawDate = $this->str($row['date'] ?? null);

            $data = [
                'type' => $this->str($row['type'] ?? null),
                'code_or_name' => $this->str($row['code_or_name'] ?? null),
                'amount' => $this->num($row['amount'] ?? null),
                'date' => $rawDate !== null ? $this->normalizeDate($rawDate) : null,
                'notes' => $this->str($row['notes'] ?? null),
            ];

            $type = self::TYPES[mb_strtolower($data['type'] ?? '')] ?? null;

            if ($type === null) {
                $errors[] = __('validation.in', ['attribute' => __('validation.attributes.type')]);
            }

            // Amounts that round to zero at 2 decimals would post an empty entry.
            if ($data['amount'] === null || Money::isZero(Money::add($data['amount'], '0.00'))) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.price')]);
            }

            if ($rawDate !== null && $data['date'] === null) {
                $errors[] = __('validation.date', ['attribute' => __('validation.attributes.effective_from')]);
            }

            if ($data['code_or_name'] !== null && mb_strlen($data['code_or_name']) > 150) {
                $errors[] = __('validation.max.string', ['attribute' => __('validation.attributes.name'), 'max' => 150]);
            }

            // The marker prefix shares the 255-char description column with the notes.
            if ($data['notes'] !== null && mb_strlen($data['notes']) > 242) {
                $errors[] = __('validation.max.string', ['attribute' => __('validation.attributes.notes'), 'max' => 242]);
            }

            [$accountType, $accountId, $resolveError] = $type !== null && $data['code_or_name'] !== null
                ? $this->resolve($type, $data['code_or_name'])
                : [null, null, null];

            if ($data['code_or_name'] === null) {
                $errors[] = __('validation.required', ['attribute' => __('validation.attributes.name')]);
            } elseif ($resolveError !== null) {
                $errors[] = $resolveError;
            } elseif ($accountId !== null && $this->alreadyImported($accountType, $accountId)) {
                $errors[] = __('imports.opening_exists');
            } elseif ($accountType !== null) {
                // Two rows in the same file must not hit the same account.
                $accountKey = $accountType.':'.($accountId ?? mb_strtolower($data['code_or_name']));

                if (isset($seenAccounts[$accountKey])) {
                    $errors[] = __('imports.opening_exists');
                }

                $seenAccounts[$accountKey] = true;
            }

            $result[] = ['row' => $index + 1, 'data' => $data, 'errors' => $errors];
        }

        return $result;
    }

    /** Normalize an Excel serial, Y-m-d or d/m/Y value to Y-m-d, or null when invalid. */
    private function normalizeDate(string $raw): ?string
    {
        // Excel stores dates as day serials; 25569 = 1970-01-01.
        if (preg_match('/^\d{4,5}(\.0+)?$/', $raw) && (float) $raw > 25569) {
            return Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $raw);
            $issues = DateTime::getLastErrors();

            if ($date !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function commit(array $validRows, User $user): int
    {
        $posting = app(PostingService::class);
        $count = 0;

        foreach ($validRows as $rowData) {
            $data = $rowData['data'];
            $type = self::TYPES[mb_strtolower($data['type'])];
            $amount = Money::add($data['amount'], '0.00');

            if (Money::isZero($amount)) {
                continue; // rounds to zero at 2 decimals — nothing to post
            }

            // Suppliers may be created on the fly, like customer groups.
            if ($type === 'supplier') {
                $supplier = Supplier::withTrashed()->firstOrCreate(['name' => $data['code_or_name']]);

                if ($supplier->trashed()) {
                    $supplier->restore();
                }
            }

            [$accountType, $accountId] = $this->resolve($type, $data['code_or_name']);

            if ($accountId === null || $this->alreadyImported($accountType, $accountId)) {
                continue; // re-validated defensively inside the transaction
            }

            $isDebit = Money::compare($amount, '0.00') > 0;
            $abs = $isDebit ? $amount : Money::subtract('0.00', $amount);

            // Customer/employee/driver: positive = they owe us (debit).
            // Supplier: positive = we owe them (credit).
            if ($accountType === LedgerEntry::SUPPLIER) {
                $isDebit = ! $isDebit;
            }

            $posting->entry(
                $accountType,
                $accountId,
                $data['date'] ?: today()->toDateString(),
                debit: $isDebit ? $abs : '0.00',
                credit: $isDebit ? '0.00' : $abs,
                source: null,
                description: trim(self::MARKER.' '.($data['notes'] ?? '')),
                userId: $user->id,
            );

            $count++;
        }

        return $count;
    }

    /** @return array{0: ?string, 1: ?int, 2: ?string} [accountType, accountId, error] */
    private function resolve(string $type, string $key): array
    {
        switch ($type) {
            case 'customer':
                $customer = Customer::where('code', $key)->first() ?? Customer::where('name', $key)->first();

                return $customer
                    ? [LedgerEntry::CUSTOMER, $customer->id, null]
                    : [null, null, __('imports.customer_not_found', ['key' => $key])];

            case 'supplier':
                $supplier = Supplier::where('name', $key)->first();

                // Created at commit time if missing — not an error.
                return [LedgerEntry::SUPPLIER, $supplier?->id, null];

            case 'employee':
                $employee = Employee::where('name_ar', $key)->orWhere('name_en', $key)->first();

                return $employee
                    ? [LedgerEntry::EMPLOYEE, $employee->id, null]
                    : [null, null, __('imports.employee_not_found', ['key' => $key])];

            case 'driver':
                $driver = User::role('driver')->where(fn ($q) => $q->where('email', $key)->orWhere('name', $key))->first();

                return $driver
                    ? [LedgerEntry::DRIVER, $driver->id, null]
                    : [null, null, __('imports.driver_not_found', ['key' => $key])];
        }

        return [null, null, null];
    }

    private function alreadyImported(?string $accountType, ?int $accountId): bool
    {
        if ($accountType === null || $accountId === null) {
            return false;
        }

        return LedgerEntry::query()
            ->where('account_type', $accountType)
            ->where('account_id', $accountId)
            ->where('description', 'like', self::MARKER.'%')
            ->exists();
    }
}
