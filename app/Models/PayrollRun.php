<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'period', 'status', 'created_by', 'reviewed_by', 'reviewed_at',
        'approved_by', 'approved_at', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'reviewed'], true);
    }
}
