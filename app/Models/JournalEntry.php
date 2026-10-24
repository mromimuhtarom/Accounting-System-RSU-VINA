<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model {
    protected $fillable=['journal_no','journal_date','reference','source_type','source_id','description','status','created_by'];
    protected function casts():array{return ['journal_date'=>'date'];}
    public function lines(){return $this->hasMany(JournalLine::class);}
    public function source(){return $this->morphTo();}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}
