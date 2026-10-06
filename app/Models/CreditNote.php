<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'series', 'number', 'uuid', 'customer_id', 'invoice_id', 'note_date',
        'reason', 'status', 'subtotal', 'vat_amount', 'total',
        'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    public function displayNumber(): string
    {
        return sprintf('%s-%06d', $this->series, $this->number ?? 0);
    }
}
