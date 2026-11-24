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
            if ($source === null) {
                return;
            }

            $units = DB::table('service_units')->where('revenue_account_id', $source->id);
            if (!$units->exists()) {
                return;
            }

            $target = $accounts->get('1.1.Q');
            if ($target === null || $target->type !== 'REVENUE' || !$target->is_active) {
                throw new RuntimeException('COA tujuan 1.1.Q harus tersedia sebagai akun pendapatan aktif.');
            }

            $units->update(['revenue_account_id' => $target->id, 'updated_at' => now()]);
        }, 3);
    }

    public function down(): void
    {
        throw new RuntimeException('Gunakan cadangan database untuk memulihkan pemetaan unit pelayanan.');
    }
};
