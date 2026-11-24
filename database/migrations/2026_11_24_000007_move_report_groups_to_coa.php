<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('chart_accounts', 'financial_group_id')) {
            Schema::table('chart_accounts', function (Blueprint $table) {
                $table->foreignId('financial_group_id')->nullable()->constrained('financial_groups')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('expense_categories')) {
            return;
        }

        if (Schema::hasColumn('expenses', 'expense_category_id')) {
            $used = DB::table('expenses as e')->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
                ->join('financial_groups as g', 'g.id', '=', 'c.financial_group_id')->where('g.type', 'EXPENSE')
                ->select('e.chart_account_id as account_id', 'g.id as group_id')->distinct()->get()->groupBy('account_id');

            $conflicts = $used->filter(fn ($rows) => $rows->count() > 1);
            if ($conflicts->isNotEmpty()) {
                $codes = DB::table('chart_accounts')->whereIn('id', $conflicts->keys())->pluck('code')->implode(', ');
                throw new RuntimeException('COA dengan beberapa grup laporan historis: '.$codes);
            }

            $defaults = DB::table('expense_categories as c')->join('financial_groups as g', 'g.id', '=', 'c.financial_group_id')
                ->where('g.type', 'EXPENSE')->select('c.expense_account_id as account_id', 'g.id as group_id')
                ->distinct()->get()->groupBy('account_id')->filter(fn ($rows) => $rows->count() === 1);

            DB::transaction(function () use ($used, $defaults) {
                foreach ($used->union($defaults) as $accountId => $rows) {
                    DB::table('chart_accounts')->where('id', $accountId)->where('type', 'EXPENSE')
                        ->whereNull('financial_group_id')->update(['financial_group_id' => $rows->first()->group_id]);
                }
            });

            Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('expense_category_id'));
        }

        Schema::drop('expense_categories');
    }

    public function down(): void
    {
        throw new RuntimeException('Pulihkan backup database untuk mengembalikan kategori pengeluaran.');
    }
};
