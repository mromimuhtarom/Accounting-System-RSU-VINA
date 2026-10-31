<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPeriod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }
}
