<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthenticationSession extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['logged_in_at'=>'datetime', 'last_activity_at'=>'datetime', 'expires_at'=>'datetime', 'logged_out_at'=>'datetime'];
    }
}
