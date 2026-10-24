<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Receipt extends Model {
    protected $fillable=['transaction_no','transaction_date','chart_account_id','income_code','doctor_name','hd','rs','patient_id','medical_record_no','patient_name','service_unit_id','guarantor_id','service_type','amount','payment_method','cash_bank_account_id','notes','cashier_id','status'];
    protected function casts():array{return ['transaction_date'=>'date','amount'=>'decimal:2'];}
    public function account(){return $this->belongsTo(ChartAccount::class,'chart_account_id');}
    public function patient(){return $this->belongsTo(Patient::class)->withTrashed();}
    public function serviceUnit(){return $this->belongsTo(ServiceUnit::class);}
    public function guarantor(){return $this->belongsTo(Guarantor::class);}
    public function cashBankAccount(){return $this->belongsTo(CashBankAccount::class);}
    public function cashier(){return $this->belongsTo(User::class,'cashier_id');}
    public function journal(){return $this->morphOne(JournalEntry::class,'source');}
    public function referralExpense(){return $this->hasOne(Expense::class)->where('expense_type','REFERRAL');}
}
