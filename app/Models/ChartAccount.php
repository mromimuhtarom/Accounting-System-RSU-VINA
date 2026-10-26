<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'parent_id', 'normal_balance', 'is_active', 'is_referral'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_referral' => 'boolean'];
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function serviceUnits() { return $this->hasMany(ServiceUnit::class, 'revenue_account_id'); }
    public function cashBankAccounts() { return $this->hasMany(CashBankAccount::class, 'chart_account_id'); }
    public function expenseCategories() { return $this->hasMany(ExpenseCategory::class, 'expense_account_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'chart_account_id'); }
    public function journalLines() { return $this->hasMany(JournalLine::class, 'chart_account_id'); }
}
