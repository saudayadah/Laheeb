<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCloseDenomination extends Model
{
    protected $fillable = ['daily_close_id', 'denomination', 'count'];

    public function close(): BelongsTo
    {
        return $this->belongsTo(DailyClose::class, 'daily_close_id');
    }
}
