<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $accounts = DB::table('chart_accounts')->whereIn('code', ['5-1012', '5.1.C'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn ($account) => strtoupper($account->code));
            $source = $accounts->get('5-1012');
            if ($source === null) {
                return;
            }

            $expenses = DB::table('expenses')->where('chart_account_id', $source->id);
            if (!$expenses->exists()) {
                return;
            }

            $target = $accounts->get('5.1.C');
            if ($target === null || $target->type !== 'EXPENSE' || !$target->is_active) {
                throw new RuntimeException('COA tujuan 5.1.C harus tersedia sebagai akun beban aktif.');
            }

            $expenses->update([
                'chart_account_id' => $target->id,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function down(): void
    {
        throw new RuntimeException('Gunakan cadangan database untuk memulihkan pemindahan COA pengeluaran.');
    }
};
