<?php

namespace App\Policies;

use App\Models\User;

class OpeningBalancePolicy
{
    private function role(User $u, array $roles): bool
    {
        return $u->is_active !== false && collect($roles)->contains(fn ($r) => $u->hasRole($r, config('accounting.application_code') ?: 'ACCOUNTING'));
    }

    public function viewAny(User $u): bool
    {
        return $this->role($u, ['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'ATASAN', 'FINANCE', 'DIREKTUR', 'OWNER']);
    }

    public function manage(User $u): bool
    {
        return $this->role($u, ['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }

    public function approve(User $u): bool
    {
        return $this->role($u, ['ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']);
    }
}
