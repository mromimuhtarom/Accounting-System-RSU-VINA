<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantProfile extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
