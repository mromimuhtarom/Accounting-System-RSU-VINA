<?php

namespace App\Services;

use App\Models\ChartAccount;
use Illuminate\Validation\ValidationException;

class ReferralAccountService
{
    public function resolve(): ChartAccount
    {
        $account = ChartAccount::where('is_active', true)->where('type', 'EXPENSE')->where('is_referral', true)
            ->orderBy('code')->first();
        if ($account) return $account;

        $account = ChartAccount::firstOrCreate(['code'=>'BEBAN-REFERAL'], [
            'name'=>'Beban Referal', 'type'=>'EXPENSE', 'normal_balance'=>'DEBIT',
            'is_referral'=>true, 'is_active'=>true,
        ]);
        if (!$account->is_active || !$account->is_referral || $account->type !== 'EXPENSE') {
            throw ValidationException::withMessages(['referral'=>'COA BEBAN-REFERAL tidak aktif atau pemetaannya tidak sesuai. Periksa Master COA.']);
        }
        return $account;
    }
}
