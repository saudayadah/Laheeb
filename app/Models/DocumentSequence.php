<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DocumentSequence extends Model
{
    protected $fillable = ['series', 'next_number'];

    /**
     * Allocate the next gapless number for a series.
     * MUST be called inside a DB transaction; takes a row lock.
     */
    public static function allocate(string $series): int
    {
        if (! DB::transactionLevel()) {
            throw new \LogicException('DocumentSequence::allocate must run inside a transaction.');
        }

        $row = static::query()->where('series', $series)->lockForUpdate()->first();

        if ($row === null) {
            // Two first-ever documents can race here; the unique index on
            // `series` makes one create fail, so fall back to the winner's row.
            try {
                static::create(['series' => $series, 'next_number' => 1]);
            } catch (UniqueConstraintViolationException) {
                // another request created it first — fine
            }

            $row = static::query()->where('series', $series)->lockForUpdate()->firstOrFail();
        }

        $number = (int) $row->next_number;
        $row->update(['next_number' => $number + 1]);

        return $number;
    }
}
