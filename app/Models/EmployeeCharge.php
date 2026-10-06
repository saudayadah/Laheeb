<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EmployeeCharge extends Model
{
    protected $fillable = [
        'employee_id', 'charge_date', 'type', 'amount', 'recovered_total',
        'status', 'notes', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'charge_date' => 'date',
            'amount' => 'decimal:2',
            'recovered_total' => 'decimal:2',
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
        return $this->status === 'approved'
            ? Money::subtract((string) $this->amount, (string) $this->recovered_total)
            : '0.00';
    }
}
