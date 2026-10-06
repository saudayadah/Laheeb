<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'phone', 'vat_number', 'notes', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Positive = we owe the supplier. */
    public function payable(): string
    {
        $balance = LedgerEntry::balance(LedgerEntry::SUPPLIER, $this->id);

        // Purchases credit the account, payments debit it, so payable = -balance.
        return Money::subtract('0.00', $balance);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
