<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function indexes(): array
    {
        return [
            ['expenses', 'perf_expense_type_date', ['expense_type', 'expense_date', 'id']],
            ['expenses', 'perf_expense_history', ['created_by', 'expense_type', 'status', 'id']],
            ['receipts', 'perf_receipt_date', ['transaction_date', 'id']],
            ['receipts', 'perf_receipt_history', ['cashier_id', 'status', 'id']],
            ['receipts', 'perf_receipt_doctor', ['doctor_name']],
            ['journal_entries', 'perf_journal_reference', ['reference', 'status']],
            ['authentication_sessions', 'perf_session_expiry', ['logged_out_at', 'expires_at', 'id']],
            ['authentication_sessions', 'perf_session_actor_activity', ['actor_id', 'logged_out_at', 'last_activity_at']],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as [$table, $name, $columns]) {
            if (!Schema::hasTable($table) || Schema::hasIndex($table, $name)) continue;
            foreach ($columns as $column) if (!Schema::hasColumn($table, $column)) continue 2;
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes()) as [$table, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
