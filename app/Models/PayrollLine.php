<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    /** In-memory defaults so a fresh line computes before it is saved. */
    protected $attributes = [
        'basic' => 0,
        'overtime' => 0,
        'leave_allowance' => 0,
        'additions' => 0,
        'absence_days' => 0,
        'absence_amount' => 0,
        'deductions' => 0,
        'advance_recovery' => 0,
        'charges_recovery' => 0,
        'net' => 0,
    ];

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'basic', 'overtime', 'leave_allowance',
        'additions', 'absence_days', 'absence_amount', 'deductions',
        'advance_recovery', 'charges_recovery', 'net', 'payment_method', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'basic' => 'decimal:2',
            'overtime' => 'decimal:2',
            'leave_allowance' => 'decimal:2',
            'additions' => 'decimal:2',
            'absence_days' => 'decimal:2',
            'absence_amount' => 'decimal:2',
            'deductions' => 'decimal:2',
            'advance_recovery' => 'decimal:2',
            'charges_recovery' => 'decimal:2',
            'net' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
