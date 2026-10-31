<?php

namespace App\Policies;

use App\Models\User;

class AssistantProfilePolicy
{
    public function manage(User $user): bool
    {
        return $user->is_active !== false && $user->hasRole('IT', config('accounting.application_code') ?: 'ACCOUNTING');
    }
}
