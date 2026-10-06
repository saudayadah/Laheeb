<?php

namespace App\Services\Imports;

use App\Models\User;
use Illuminate\Support\Collection;

abstract class BaseImporter
{
    abstract public function type(): string;

    /** Column keys, in template order. */
    abstract public function headings(): array;

    /** Example rows shown in the downloadable template. */
    abstract public function exampleRows(): array;

    /**
     * Validate all rows. Returns a list of:
     * ['row' => int (1-based, excluding heading), 'data' => array, 'errors' => string[]]
     */
    abstract public function validateRows(Collection $rows, User $user): array;

    /**
     * Insert the valid rows. Runs inside a DB transaction. Returns created count.
     */
    abstract public function commit(array $validRows, User $user): int;

    /** Normalize one raw cell to a trimmed string or null. */
    protected function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Excel returns numbers for numeric-looking cells (phone numbers, codes).
        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function num(mixed $value): ?string
    {
        $value = $this->str($value);

        if ($value === null) {
            return null;
        }

        // Accept Arabic decimal separator and Arabic-Indic digits.
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', ',' => '',
        ]);

        return is_numeric($value) ? $value : null;
    }

    /** True when the whole row is empty. */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->str($value) !== null) {
                return false;
            }
        }

        return true;
    }
}
