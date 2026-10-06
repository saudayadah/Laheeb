<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use HasFactory;

    public const PAID_FROM = ['driver_cash', 'counter_cash', 'bank', 'supplier_credit'];

    protected $fillable = [
        'expense_date', 'expense_category_id', 'amount', 'vat_amount', 'paid_from',
        'paid_by', 'supplier_id', 'vehicle_id', 'status', 'note', 'receipt_photo',
        'from_sheet', 'recurring_expense_id', 'created_by', 'approved_by',
        'approved_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'from_sheet' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
