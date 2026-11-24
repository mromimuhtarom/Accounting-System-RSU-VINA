<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('guarantors', 'financial_group_id')) {
            Schema::table('guarantors', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_group_id'));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Pulihkan backup untuk mengembalikan pemetaan grup laporan lama pada penjamin.');
    }
};
