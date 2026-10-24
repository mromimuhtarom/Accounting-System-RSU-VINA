<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('chart_account_id')
                ->nullable()
                ->after('transaction_date')
                ->constrained('chart_accounts')
                ->restrictOnDelete();
        });

        // Backfill transaksi format lama jika kode teks sama dengan kode COA.
        DB::statement("
            UPDATE receipts r
            INNER JOIN chart_accounts c ON c.code = r.income_code
            SET r.chart_account_id = c.id
            WHERE r.chart_account_id IS NULL
              AND r.income_code IS NOT NULL
              AND r.income_code <> ''
        ");
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_account_id');
        });
    }
};
