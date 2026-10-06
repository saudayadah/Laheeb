<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable sub-ledger rows. Never update or delete a posted entry;
 * corrections are new reversal entries.
 */
class LedgerEntry extends Model
{
    public const CUSTOMER = 'customer';

    public const DRIVER = 'driver';

    public const CASH_BOX = 'cash_box';

    public const BANK = 'bank';

    public const SUPPLIER = 'supplier';

    public const EMPLOYEE = 'employee';

    /** Reserved ids for the single main cash box / bank account. */
    public const MAIN_CASH_BOX_ID = 1;

    public const MAIN_BANK_ID = 1;

    protected $fillable = [
        'account_type', 'account_id', 'entry_date', 'debit', 'credit',
        'source_type', 'source_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Balance of one account: debits minus credits, as a decimal string. */
    public static function balance(string $accountType, int $accountId): string
    {
        $row = static::query()
            ->where('account_type', $accountType)
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return Money::subtract((string) $row->d, (string) $row->c);
    }
}
