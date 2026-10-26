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
    public function authenticationSessions() { return $this->hasMany(AuthenticationSession::class, 'actor_id'); }
    public function latestAuthenticationSession() { return $this->hasOne(AuthenticationSession::class, 'actor_id')->ofMany(['last_activity_at'=>'max','id'=>'max']); }

    public function canManageTransaction(?int $creatorId): bool
    {
        $application = config('accounting.application_code') ?: 'ACCOUNTING';
        if (collect(['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING'])
            ->contains(fn ($role) => $this->hasRole($role, $application))) {
            return true;
        }

        return $creatorId !== null && (int) $this->id === $creatorId
            && $this->hasRole('KASIR', $application);
    }

    public function hasRole(string $roleCode, ?string $applicationCode = null): bool
    {
        $this->loadMissing('roles.application');
        return $this->roles->contains(fn ($role) => $role->code === $roleCode
            && (!$applicationCode || $role->application?->code === $applicationCode));
    }

    public function accountingRole(): ?string
    {
        $applicationCode = config('accounting.application_code') ?: 'ACCOUNTING';

        if ($this->hasRole('IT', $applicationCode)) {
            return 'IT';
        }

        $role = $this->roles->first(fn ($role) => $role->application?->code === $applicationCode)?->code;

        return $role ? strtoupper(trim((string) $role)) : null;
    }
}
