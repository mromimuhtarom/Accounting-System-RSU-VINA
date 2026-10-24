<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Application extends Model { protected $fillable=['code','name','is_active']; public function roles(){return $this->hasMany(Role::class);} }
