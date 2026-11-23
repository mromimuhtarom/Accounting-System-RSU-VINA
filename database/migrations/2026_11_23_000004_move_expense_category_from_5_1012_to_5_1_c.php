<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $categories = DB::table('expense_categories')->whereIn('code', ['5-1012', '5.1.C'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn ($category) => strtoupper($category->code));
            $source = $categories->get('5-1012');
            if ($source === null) {
                return;
            }

            $expenses = DB::table('expenses')->where('expense_category_id', $source->id);
            if (!$expenses->exists()) {
                return;
            }

            $target = $categories->get('5.1.C');
            if ($target === null || !$target->is_active) {
                throw new RuntimeException('Kategori pengeluaran tujuan 5.1.C harus tersedia dan aktif.');
            }

            $account = DB::table('chart_accounts')->where('id', $target->expense_account_id)
                ->where('type', 'EXPENSE')->where('is_active', true)->lockForUpdate()->first();
            if ($account === null) {
                throw new RuntimeException('Kategori 5.1.C harus terhubung ke COA Beban aktif.');
            }

            $expenses->update([
                'expense_category_id' => $target->id,
                'chart_account_id' => $account->id,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function down(): void
    {
        throw new RuntimeException('Gunakan cadangan database untuk memulihkan pemindahan kategori pengeluaran.');
    }
};
