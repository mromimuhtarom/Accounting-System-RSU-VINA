<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','last_login_at'=>'datetime','password'=>'hashed','is_active'=>'boolean']; }

    public function roles() { return $this->belongsToMany(Role::class, 'user_roles'); }

    public function hasRole(string $roleCode, ?string $applicationCode = null): bool
    {
        return $this->roles()
            ->where('roles.code', $roleCode)
            ->when($applicationCode, fn ($q) => $q->whereHas('application', fn ($a) => $a->where('code', $applicationCode)))
            ->exists();
    }

    public function accountingRole(): ?string
    {
        $applicationCode = config('accounting.application_code') ?: 'ACCOUNTING';

        if ($this->hasRole('IT', $applicationCode)) {
            return 'IT';
        }

        $role = $this->roles()
            ->whereHas('application', fn ($q) => $q->where('code', $applicationCode))
            ->value('roles.code');

        return $role ? strtoupper(trim((string) $role)) : null;
    }
}
