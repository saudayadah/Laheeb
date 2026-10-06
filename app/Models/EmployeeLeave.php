<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id', 'start_date', 'expected_return', 'actual_return', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_return' => 'date',
            'actual_return' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Still away past the promised return date. */
    public function isOverdue(): bool
    {
        return $this->actual_return === null && $this->expected_return->lt(today());
    }
}
