<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    protected $fillable = [
        'vehicle_id', 'service_date', 'task', 'odometer',
        'next_due_date', 'next_due_odometer', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isDue(): bool
    {
        return $this->next_due_date !== null && $this->next_due_date->lte(today()->addDays(7));
    }
}
