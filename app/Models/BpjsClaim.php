<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BpjsClaim extends Model {
 protected $fillable=['sep_no','patient_name','medical_record_no','service_date','service_type','batch_no','submitted_at','hospital_tariff','claimed_amount','approved_amount','rejected_amount','status','notes','created_by'];
 protected function casts():array {return ['service_date'=>'date','submitted_at'=>'date','claimed_amount'=>'decimal:2','approved_amount'=>'decimal:2','rejected_amount'=>'decimal:2','hospital_tariff'=>'decimal:2'];}
 public function payments(){return $this->hasMany(BpjsClaimPayment::class);}
 public function events(){return $this->hasMany(BpjsClaimEvent::class)->latest();}
}
