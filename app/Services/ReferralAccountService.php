<?php

namespace App\Services;

use App\Models\{ChartAccount, ExpenseCategory};
use Illuminate\Validation\ValidationException;

class ReferralAccountService
{
    public function resolve(): ChartAccount
    {
        $mapped = ExpenseCategory::where('code', 'REFERAL')->value('expense_account_id');
        $account = ChartAccount::where('is_active', true)->where('type', 'EXPENSE')->where('is_referral', true)
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$mapped ?? 0])->orderBy('code')->first();
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
