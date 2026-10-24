<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceMonthlyClaim extends Model {
 protected $fillable=['guarantor_id','year','month','service_type','claimed_amount','approved_amount','paid_amount','status','submitted_at','settled_at','notes','created_by','updated_by'];
 protected $casts=['claimed_amount'=>'decimal:2','approved_amount'=>'decimal:2','paid_amount'=>'decimal:2','submitted_at'=>'date','settled_at'=>'date'];
 public function guarantor(){return $this->belongsTo(Guarantor::class);}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
