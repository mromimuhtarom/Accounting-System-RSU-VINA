<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $t) {
            $t->id();
            $t->string('scope')->unique()->default('INITIAL');
            $t->date('balance_date');
            $t->text('evidence');
            $t->string('status')->default('DRAFT');
            $t->unsignedInteger('version')->default(0);
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries');
            $t->timestamps();
        });
        Schema::create('opening_balance_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opening_balance_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chart_account_id')->constrained('chart_accounts');
            $t->decimal('debit', 18, 2)->default(0);
            $t->decimal('credit', 18, 2)->default(0);
            $t->unique(['opening_balance_id', 'chart_account_id']);
            $t->timestamps();
        });
        Schema::create('opening_balance_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opening_balance_id')->constrained();
            $t->string('action');
            $t->foreignId('actor_id')->constrained('users');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_audits');
        Schema::dropIfExists('opening_balance_lines');
        Schema::dropIfExists('opening_balances');
    }
};
