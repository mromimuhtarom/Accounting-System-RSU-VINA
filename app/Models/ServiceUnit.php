<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceUnit extends Model
{
    protected $fillable = ['code', 'name', 'revenue_account_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function revenueAccount() { return $this->belongsTo(ChartAccount::class, 'revenue_account_id'); }
    public function receipts() { return $this->hasMany(Receipt::class); }
}
