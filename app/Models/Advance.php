<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Advance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'advance_date', 'amount', 'recovered_total', 'paid_from',
        'plan', 'installment_amount', 'status', 'notes', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => 'decimal:2',
            'recovered_total' => 'decimal:2',
            'installment_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    public function remaining(): string
    {
        return Money::subtract((string) $this->amount, (string) $this->recovered_total);
    }

    /** What this advance asks to recover in one payroll run. */
    public function dueThisMonth(): string
    {
        if ($this->status !== 'active') {
            return '0.00';
        }

        $remaining = $this->remaining();

        if ($this->plan === 'installment' && $this->installment_amount !== null) {
            return Money::compare((string) $this->installment_amount, $remaining) <= 0
                ? (string) $this->installment_amount
                : $remaining;
        }

        return $remaining;
    }
}
