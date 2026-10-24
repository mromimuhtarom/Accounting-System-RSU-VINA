<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_monthly_claims') && Schema::hasColumn('insurance_monthly_claims', 'reference_no')) {
            Schema::table('insurance_monthly_claims', function (Blueprint $table) {
                $table->dropColumn('reference_no');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('insurance_monthly_claims') && !Schema::hasColumn('insurance_monthly_claims', 'reference_no')) {
            Schema::table('insurance_monthly_claims', function (Blueprint $table) {
                $table->string('reference_no', 120)->nullable();
            });
        }
    }
};
