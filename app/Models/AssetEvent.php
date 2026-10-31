<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'event_date' => 'date'];
    }
}
