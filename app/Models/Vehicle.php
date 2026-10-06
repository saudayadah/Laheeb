<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['name', 'plate', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
