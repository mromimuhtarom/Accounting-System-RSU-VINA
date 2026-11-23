<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use Illuminate\Support\Facades\DB;

class TransactionPerformanceTest extends ExpenseAccessTest
{
    public function test_receipt_coa_migration_moves_only_source_accounts_and_unmapped_legacy_codes(): void
    {
        $migration = require database_path('migrations/2026_11_23_000001_move_receipts_from_1_1_m_to_1_1_o.php');
        $attributes = ['name' => 'Pendapatan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT', 'is_active' => true];
        $source = ChartAccount::create($attributes + ['code' => '1.1.M']);
        $other = ChartAccount::create($attributes + ['code' => '1.1.P']);
        $records = ['transaction_date' => '2026-11-20', 'amount' => 10000, 'cashier_id' => DB::table('users')->value('id')];
        $mappedId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-1', 'chart_account_id' => $source->id]);
        $legacyId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-2', 'income_code' => '1.1.M']);
        $otherId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-3', 'chart_account_id' => $other->id, 'income_code' => '1.1.M']);

        try {
            $migration->up();
            $this->fail('The migration should stop if the destination account is missing.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('COA tujuan 1.1.O', $error->getMessage());
        }
        $this->assertDatabaseHas('receipts', ['id' => $mappedId, 'chart_account_id' => $source->id]);

        $target = ChartAccount::create($attributes + ['code' => '1.1.O']);
        $migration->up();
        $migration->up();

        foreach ([$mappedId, $legacyId] as $id) {
            $this->assertDatabaseHas('receipts', ['id' => $id, 'chart_account_id' => $target->id, 'income_code' => '1.1.O', 'amount' => 10000]);
        }
        $this->assertDatabaseHas('receipts', ['id' => $otherId, 'chart_account_id' => $other->id, 'income_code' => '1.1.M']);
    }

    public function test_coa_search_checks_delete_dependencies_in_one_query(): void
    {
        $attributes = ['type' => 'ASSET', 'normal_balance' => 'DEBIT'];
        $parent = ChartAccount::create($attributes + ['code' => 'FILTER-1', 'name' => 'Uji Inventaris']);
        $child = ChartAccount::create($attributes + ['code' => 'FILTER-2', 'name' => 'Uji Inventaris Cadangan', 'parent_id' => $parent->id]);
        ChartAccount::create($attributes + ['code' => 'FILTER-3', 'name' => 'Uji Inventaris Referal', 'is_referral' => true]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->get(route('accounting.master.index', ['accounts', 'group' => 'general', 'q' => 'Inventaris']))
                ->assertOk()
                ->assertViewHas('items', fn ($items) => $items->getCollection()->modelKeys() === [$parent->id, $child->id])
                ->assertViewHas('lockedCoaIds', [$parent->id]);
            $checks = collect(DB::getQueryLog())->filter(fn ($entry) => str_contains(strtolower($entry['query']), 'as dependency_'));
            $this->assertCount(1, $checks);
        } finally {
            DB::disableQueryLog();
        }

        $this->delete(route('accounting.master.destroy', ['accounts', $parent->id]))->assertSessionHas('error');
        $this->assertModelExists($parent);
        $this->delete(route('accounting.master.destroy', ['accounts', $child->id]))->assertSessionHas('success');
        $this->assertModelMissing($child);
    }

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
