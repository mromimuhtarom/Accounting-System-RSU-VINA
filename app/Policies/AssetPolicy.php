<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    private function role(User $user, array $roles): bool
    {
        if ($user->is_active === false) {
            return false;
        }

        return collect($roles)->contains(fn ($r) => $user->hasRole($r, config('accounting.application_code') ?: 'ACCOUNTING'));
    }

    public function viewAny(User $u): bool
    {
        return $this->role($u, ['PETUGAS_ASET', 'ADMIN_ASET', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'DIREKTUR', 'OWNER']);
    }

    public function import(User $u): bool
    {
        return $this->role($u, ['PETUGAS_ASET', 'ADMIN_ASET', 'ADMIN_ACCOUNTING']);
    }

    public function create(User $u): bool
    {
        return $this->role($u, ['PETUGAS_ASET', 'ADMIN_ASET', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }

    public function approve(User $u): bool
    {
        return $this->role($u, ['ADMIN_ASET', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }

    public function update(User $u, Asset $asset): bool
    {
        return $this->create($u);
    }

    public function delete(User $u, Asset $asset): bool
    {
        return $this->role($u, ['ACCOUNTING', 'ADMIN_ASET', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }

    public function account(User $u): bool
    {
        return $this->role($u, ['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }

    public function operate(User $u): bool
    {
        return $this->role($u, ['PETUGAS_ASET', 'ADMIN_ASET', 'ADMIN_ACCOUNTING']);
    }
}
