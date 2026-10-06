<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'series', 'number', 'uuid', 'customer_id', 'order_id', 'invoice_date',
        'type', 'source', 'payment_method', 'status', 'subtotal', 'vat_amount',
        'total', 'prices_include_vat', 'hash', 'prev_hash', 'qr_payload',
        'idempotency_key', 'driver_id', 'posted_at', 'posted_by',
        'voided_at', 'voided_by', 'void_reason', 'notes',
        'reclass_reason', 'reclassified_at', 'reclassified_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'prices_include_vat' => 'boolean',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'reclassified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function displayNumber(): string
    {
        return $this->number !== null
            ? sprintf('%s-%06d', $this->series, $this->number)
            : __('invoices.draft_number');
    }
}
