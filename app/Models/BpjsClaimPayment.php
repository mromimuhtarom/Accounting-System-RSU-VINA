<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BpjsClaimPayment extends Model {
 protected $fillable=['bpjs_claim_id','receipt_id','amount'];
 public function receipt(){return $this->belongsTo(Receipt::class);}
 public function claim(){return $this->belongsTo(BpjsClaim::class,'bpjs_claim_id');}
}
