<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model {
    protected $fillable=['expense_no','expense_type','expense_date','patient_id','receipt_id','expense_category_id','chart_account_id','description','amount','payment_method','cash_bank_account_id','reference_no','referral_recipient','attachment_path','created_by','status'];
    protected function casts():array{return ['expense_date'=>'date','amount'=>'decimal:2'];}
    public function patient(){return $this->belongsTo(Patient::class)->withTrashed();}
    public function receipt(){return $this->belongsTo(Receipt::class);}
    public function category(){return $this->belongsTo(ExpenseCategory::class,'expense_category_id');}
    public function account(){return $this->belongsTo(ChartAccount::class,'chart_account_id');}
    public function cashBankAccount(){return $this->belongsTo(CashBankAccount::class);}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function journal(){return $this->morphOne(JournalEntry::class,'source');}
}
