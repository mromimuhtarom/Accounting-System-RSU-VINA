<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class TransactionPerformanceTest extends ExpenseAccessTest
{
    public function test_batch_query_cost_is_bounded(): void
    {
        $row = ['expense_date'=>'2026-11-13', 'expense_category_id'=>DB::table('expense_categories')->value('id'),
            'description'=>'Pengukuran input', 'amount'=>100000, 'payment_method'=>'CASH',
            'cash_bank_account_id'=>DB::table('cash_bank_accounts')->value('id')];
        DB::enableQueryLog();
        $this->post(route('cashier.expenses.store'), ['rows'=>array_fill(0,20,$row)])->assertSessionHasNoErrors();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $schema = count(array_filter($queries, fn ($query) => str_contains(strtolower($query['query']), 'sqlite_master')));
        // Baseline before request-local audit lookups: 1,084 queries / 322 schema probes.
        $this->assertLessThanOrEqual(600, count($queries), 'Batch audit/journal queries regressed.');
        $this->assertLessThanOrEqual(15, $schema, 'Metadata should be reused within a request.');
        $this->assertDatabaseCount('expenses',20);
    }

    public function test_performance_indexes_can_be_added_and_removed_without_changing_data(): void
    {
        $migration = require database_path('migrations/2026_11_13_000002_add_transaction_performance_indexes.php');
        $migration->up();
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('expenses', 'perf_expense_type_date'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('journal_entries', 'perf_journal_reference'));
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasIndex('expenses', 'perf_expense_type_date'));
        $this->assertDatabaseCount('expense_categories', 1);
    }
}
