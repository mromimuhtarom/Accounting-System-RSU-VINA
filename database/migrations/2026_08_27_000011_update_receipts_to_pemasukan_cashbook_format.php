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
            $table->string('income_code', 30)->nullable()->after('transaction_date')->index();
            $table->string('doctor_name', 150)->nullable()->after('notes');
            $table->string('hd', 100)->nullable()->after('doctor_name');
            $table->string('rs', 100)->nullable()->after('hd');
        });

        // Field lama dipertahankan untuk histori transaksi existing,
        // tetapi tidak lagi wajib untuk format pemasukan buku kas yang baru.
        DB::statement('ALTER TABLE receipts MODIFY patient_name VARCHAR(150) NULL');
        DB::statement('ALTER TABLE receipts MODIFY service_unit_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE receipts MODIFY guarantor_id BIGINT UNSIGNED NULL');
        DB::statement("ALTER TABLE receipts MODIFY payment_method ENUM('CASH','TRANSFER','QRIS','CARD','GUARANTOR') NULL");
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['income_code']);
            $table->dropColumn(['income_code', 'doctor_name', 'hd', 'rs']);
        });

        // Kolom legacy sengaja tidak dipaksa kembali NOT NULL pada rollback,
        // karena transaksi format baru dapat memiliki nilai NULL pada kolom tersebut.
    }
};
