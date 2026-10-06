<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyClose extends Model
{
    public const TYPE_DRIVER = 'driver';

    public const TYPE_COUNTER = 'counter';

    public const DENOMINATIONS = [500, 200, 100, 50, 20, 10, 5, 1];

    protected $fillable = [
        'close_date', 'closeable_type', 'closeable_id', 'expected', 'counted',
        'variance', 'status', 'submitted_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'close_date' => 'date',
            'expected' => 'decimal:2',
            'counted' => 'decimal:2',
            'variance' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(DailyCloseDenomination::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closeable_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
