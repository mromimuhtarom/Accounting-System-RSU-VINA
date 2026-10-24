<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model { protected $fillable=['application_id','code','name']; public function application(){return $this->belongsTo(Application::class);} public function users(){return $this->belongsToMany(User::class,'user_roles');} }
