<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_type', 20)->default('GENERAL')->after('expense_no');
            $table->foreignId('patient_id')->nullable()->after('expense_date')->constrained('patients')->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->after('patient_id')->constrained('receipts')->restrictOnDelete();
            $table->string('referral_recipient', 150)->nullable()->after('reference_no');
            $table->unique(['receipt_id', 'expense_type'], 'expenses_receipt_type_unique');
            $table->index(['expense_type', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_receipt_type_unique');
            $table->dropIndex(['expense_type', 'expense_date']);
            $table->dropForeign(['receipt_id']);
            $table->dropForeign(['patient_id']);
            $table->dropColumn(['expense_type', 'patient_id', 'receipt_id', 'referral_recipient']);
        });
    }
};
