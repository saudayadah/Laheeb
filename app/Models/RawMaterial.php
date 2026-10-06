<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit', 'reorder_level', 'active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RawMaterialMovement::class);
    }

    /** Remaining stock = everything in minus everything out. */
    public function onHand(): string
    {
        $in = (string) $this->movements()->where('direction', 'in')->sum('qty');
        $out = (string) $this->movements()->where('direction', 'out')->sum('qty');

        return Money::subtract($in, $out);
    }
}
