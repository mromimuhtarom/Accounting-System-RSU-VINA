<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('medical_record_no', 50)->unique();
            $table->string('name', 150);
            $table->enum('gender', ['MALE', 'FEMALE'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('transaction_date')->constrained('patients')->restrictOnDelete();
            $table->index(['patient_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropIndex(['patient_id', 'transaction_date']);
            $table->dropColumn('patient_id');
        });

        Schema::dropIfExists('patients');
    }
};
