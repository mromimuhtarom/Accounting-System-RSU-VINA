<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBankAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'bank_name', 'account_number', 'chart_account_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function chartAccount() { return $this->belongsTo(ChartAccount::class); }
    public function receipts() { return $this->hasMany(Receipt::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
}
