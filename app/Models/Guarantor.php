<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guarantor extends Model
{
    protected $fillable = ['code', 'name', 'category', 'financial_group_id', 'is_bpjs', 'is_active'];

    protected function casts(): array
    {
        return ['is_bpjs' => 'boolean', 'is_active' => 'boolean'];
    }

    public function financialGroup() { return $this->belongsTo(FinancialGroup::class); }
    public function receipts() { return $this->hasMany(Receipt::class); }
}
