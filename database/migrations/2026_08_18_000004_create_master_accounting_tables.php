<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_units', function (Blueprint $table) {
            $table->id();
            $table->string('code',30)->unique();
            $table->string('name',150);
            $table->foreignId('revenue_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->string('code',30)->unique();
            $table->string('name',150);
            $table->enum('category',['SELF_PAY','BPJS','PRIVATE_INSURANCE','CORPORATE','OTHER']);
            $table->boolean('is_bpjs')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_bpjs','is_active']);
        });

        Schema::create('cash_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code',30)->unique();
            $table->string('name',150);
            $table->enum('type',['CASH','BANK']);
            $table->string('bank_name',150)->nullable();
            $table->string('account_number',100)->nullable();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code',30)->unique();
            $table->string('name',150);
            $table->foreignId('expense_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('cash_bank_accounts');
        Schema::dropIfExists('guarantors');
        Schema::dropIfExists('service_units');
    }
};
