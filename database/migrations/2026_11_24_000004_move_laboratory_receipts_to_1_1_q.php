<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $accounts = DB::table('chart_accounts')->whereIn('code', ['4-1004', '1.1.Q'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn ($account) => strtoupper($account->code));
            $source = $accounts->get('4-1004');

            $receipts = DB::table('receipts')->where(function ($query) use ($source) {
                $query->where(fn ($legacy) => $legacy->whereNull('chart_account_id')->where('income_code', '4-1004'));
                if ($source !== null) {
                    $query->orWhere('chart_account_id', $source->id);
                }
            });

            if (!$receipts->exists()) {
                return;
            }

            $target = $accounts->get('1.1.Q');
            if ($target === null || $target->type !== 'REVENUE' || !$target->is_active) {
                throw new RuntimeException('COA tujuan 1.1.Q harus tersedia sebagai akun pendapatan aktif.');
            }

            $receipts->update([
                'chart_account_id' => $target->id,
                'income_code' => $target->code,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function down(): void
    {
        throw new RuntimeException('Gunakan cadangan penerimaan untuk memulihkan pemindahan COA ini.');
    }
};
