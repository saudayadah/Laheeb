<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'name_en', 'customer_group_id', 'type', 'payment_term',
        'credit_limit', 'credit_days', 'delivery_route_id', 'stop_sequence',
        'city', 'phone', 'whatsapp', 'map_url', 'vat_number', 'cr_number',
        'national_address', 'active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function standingOrders(): HasMany
    {
        return $this->hasMany(StandingOrder::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public static function nextCode(): string
    {
        // Kept portable across MySQL/MariaDB and the SQLite test database.
        $max = static::withTrashed()
            ->pluck('code')
            ->filter(fn ($code) => ctype_digit((string) $code))
            ->map(fn ($code) => (int) $code)
            ->max() ?? 0;

        return (string) ($max + 1);
    }
}
