<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name_ar', 'name_en', 'nationality', 'iqama_number', 'iqama_expiry',
        'job', 'basic_salary', 'join_date', 'active', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'iqama_expiry' => 'date',
            'join_date' => 'date',
            'basic_salary' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /** Residence-permit numbers are encrypted at rest. */
    protected function iqamaNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Crypt::decryptString($value),
            set: fn (?string $value) => $value === null || $value === '' ? null : Crypt::encryptString($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(EmployeeCharge::class);
    }

    /** Positive = the employee owes the bakery (advances + charges not yet recovered). */
    public function outstanding(): string
    {
        return LedgerEntry::balance(LedgerEntry::EMPLOYEE, $this->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['iqama_number'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
