<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('chart_accounts', function (Blueprint $table) {
            $table->boolean('is_referral')->default(false)->index();
        });
        DB::table('chart_accounts')->whereIn('id', DB::table('expense_categories')->where('code', 'REFERAL')->select('expense_account_id'))->update(['is_referral'=>true]);
    }
    public function down(): void {
        Schema::table('chart_accounts', fn (Blueprint $table) => $table->dropColumn('is_referral'));
    }
};
