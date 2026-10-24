<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['code', 'name', 'financial_group_id', 'expense_account_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function expenseAccount() { return $this->belongsTo(ChartAccount::class, 'expense_account_id'); }
    public function financialGroup() { return $this->belongsTo(FinancialGroup::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
}
