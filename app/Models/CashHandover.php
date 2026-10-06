<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CashHandover extends Model
{
    protected $fillable = [
        'driver_id', 'handover_date', 'amount', 'received_by', 'daily_close_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }
}
