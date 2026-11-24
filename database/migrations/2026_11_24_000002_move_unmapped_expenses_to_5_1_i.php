<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $accounts = DB::table('chart_accounts')->whereIn('code', ['IMPORT-EXPENSE-UNMAPPED', '5.1.I'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn ($account) => strtoupper($account->code));
            $source = $accounts->get('IMPORT-EXPENSE-UNMAPPED');
            if ($source === null) {
                return;
            }

            $expenses = DB::table('expenses')->where('chart_account_id', $source->id);
            if (!$expenses->exists()) {
                return;
            }

            $target = $accounts->get('5.1.I');
            if ($target === null || $target->type !== 'EXPENSE' || !$target->is_active) {
                throw new RuntimeException('COA tujuan 5.1.I harus tersedia sebagai akun beban aktif.');
            }

            $category = DB::table('expense_categories')->where('code', 'XLS_5_1_I')
                ->where('expense_account_id', $target->id)->where('is_active', true)->lockForUpdate()->first();
            if ($category === null) {
                throw new RuntimeException('Kategori XLS_5_1_I harus aktif dan terhubung ke COA 5.1.I.');
            }

            $expenses->update([
                'expense_category_id' => $category->id,
                'chart_account_id' => $target->id,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function down(): void
    {
        throw new RuntimeException('Gunakan cadangan database untuk memulihkan pemindahan pengeluaran impor.');
    }
};
