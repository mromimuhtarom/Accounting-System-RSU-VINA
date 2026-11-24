<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;

class TransactionPerformanceTest extends ExpenseAccessTest
{
    #[TestWith(['2026_11_24_000001_move_office_supply_expenses_to_5_1_h.php', '5-1008', '5.1.H', 'XLS_5_1_H'])]
    #[TestWith(['2026_11_24_000002_move_unmapped_expenses_to_5_1_i.php', 'IMPORT-EXPENSE-UNMAPPED', '5.1.I', 'XLS_5_1_I'])]
    #[TestWith(['2026_11_24_000003_move_medicine_expenses_to_5_2_a.php', '5-1005', '5.2.A', 'XLS_5_2_A'])]
    public function test_expense_account_migration_updates_coa_and_category(string $file, string $from, string $to, string $destinationCode): void
    {
        $migration = require database_path('migrations/'.$file);
        $attributes = ['name' => 'ATK', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true];
        $source = ChartAccount::create($attributes + ['code' => $from]);
        $target = ChartAccount::create($attributes + ['code' => $to]);
        $category = ExpenseCategory::create(['code' => 'ATK', 'name' => 'ATK', 'expense_account_id' => $source->id]);
        $fields = [
            'expense_date' => '2026-11-20', 'expense_category_id' => $category->id, 'description' => 'Pembayaran ATK',
            'amount' => 10000, 'payment_method' => 'CASH', 'cash_bank_account_id' => DB::table('cash_bank_accounts')->value('id'),
            'created_by' => DB::table('users')->value('id'),
        ];
        $moved = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'ATK-1', 'chart_account_id' => $source->id]);
        $unchanged = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'ATK-2', 'chart_account_id' => $target->id]);

        try {
            $migration->up();
            $this->fail('The migration should stop if the destination category is missing.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString($destinationCode, $error->getMessage());
        }
        $this->assertDatabaseHas('expenses', ['id' => $moved, 'chart_account_id' => $source->id]);

        $destination = ExpenseCategory::create(['code' => $destinationCode, 'name' => 'Kategori Tujuan', 'expense_account_id' => $target->id, 'is_active' => true]);
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expenses', ['id' => $moved, 'chart_account_id' => $target->id, 'expense_category_id' => $destination->id, 'amount' => 10000]);
        $this->assertDatabaseHas('expenses', ['id' => $unchanged, 'chart_account_id' => $target->id, 'expense_category_id' => $category->id]);
    }

    public function test_expense_coa_migration_moves_only_expenses_linked_to_the_source_account(): void
    {
        $migration = require database_path('migrations/2026_11_23_000003_move_expense_coa_from_5_1012_to_5_1_c.php');
        $attributes = ['name' => 'Beban', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true];
        $source = ChartAccount::create($attributes + ['code' => '5-1012']);
        $other = ChartAccount::where('code', '5001')->firstOrFail();
        $category = ExpenseCategory::where('code', 'UMUM')->firstOrFail();
        $fields = [
            'expense_date' => '2026-11-20', 'expense_category_id' => $category->id, 'description' => 'Pembayaran',
            'amount' => 10000, 'payment_method' => 'CASH', 'cash_bank_account_id' => DB::table('cash_bank_accounts')->value('id'),
            'created_by' => DB::table('users')->value('id'),
        ];
        $moved = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'COA-BEBAN-1', 'chart_account_id' => $source->id]);
        $unchanged = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'COA-BEBAN-2', 'chart_account_id' => $other->id]);

        try {
            $migration->up();
            $this->fail('The migration should stop if the destination account is missing.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('COA tujuan 5.1.C', $error->getMessage());
        }
        $this->assertDatabaseHas('expenses', ['id' => $moved, 'chart_account_id' => $source->id]);

        $target = ChartAccount::create($attributes + ['code' => '5.1.C']);
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expenses', ['id' => $moved, 'chart_account_id' => $target->id, 'expense_category_id' => $category->id, 'amount' => 10000]);
        $this->assertDatabaseHas('expenses', ['id' => $unchanged, 'chart_account_id' => $other->id]);
    }

    #[TestWith(['2026_11_23_000002_move_electricity_expenses_to_5_1_b.php', '5-1010', '5.1.B'])]
    #[TestWith(['2026_11_23_000004_move_expense_category_from_5_1012_to_5_1_c.php', '5-1012', '5.1.C'])]
    public function test_expense_category_migration_moves_source_category_and_uses_destination_account(string $file, string $from, string $to): void
    {
        $migration = require database_path('migrations/'.$file);
        $oldAccount = ChartAccount::where('type', 'EXPENSE')->firstOrFail();
        $source = ExpenseCategory::create(['code' => $from, 'name' => 'Kategori Lama', 'expense_account_id' => $oldAccount->id]);
        $other = ExpenseCategory::where('code', 'UMUM')->firstOrFail();
        $fields = [
            'expense_date' => '2026-11-20', 'chart_account_id' => $oldAccount->id, 'description' => 'Pembayaran',
            'amount' => 10000, 'payment_method' => 'CASH', 'cash_bank_account_id' => DB::table('cash_bank_accounts')->value('id'),
            'created_by' => DB::table('users')->value('id'),
        ];
        $moved = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'LISTRIK-1', 'expense_category_id' => $source->id]);
        $unchanged = DB::table('expenses')->insertGetId($fields + ['expense_no' => 'LISTRIK-2', 'expense_category_id' => $other->id]);

        try {
            $migration->up();
            $this->fail('The migration should stop if the target category is missing.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('tujuan '.$to, $error->getMessage());
        }
        $this->assertDatabaseHas('expenses', ['id' => $moved, 'expense_category_id' => $source->id]);

        $newAccount = ChartAccount::create(['code' => '5102', 'name' => 'Listrik', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true]);
        $target = ExpenseCategory::create(['code' => $to, 'name' => 'Kategori Tujuan', 'expense_account_id' => $newAccount->id, 'is_active' => true]);
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expenses', ['id' => $moved, 'expense_category_id' => $target->id, 'chart_account_id' => $newAccount->id, 'amount' => 10000]);
        $this->assertDatabaseHas('expenses', ['id' => $unchanged, 'expense_category_id' => $other->id, 'chart_account_id' => $oldAccount->id]);
    }

    public function test_expense_category_search_paginates_and_preserves_master_actions(): void
    {
        (require database_path('migrations/2026_08_18_000008_create_financial_groups_table.php'))->up();
        $type = 'expense-categories';
        $attributes = [
            'financial_group_id' => DB::table('financial_groups')->where('code', 'EXP_OPERATIONAL')->value('id'),
            'expense_account_id' => ChartAccount::where('type', 'EXPENSE')->value('id'),
            'is_active' => true,
        ];
        $this->post(route('accounting.master.store', $type), $attributes + ['code' => 'UJI_01', 'name' => 'Perlengkapan 01'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $category = ExpenseCategory::where('code', 'UJI_01')->firstOrFail();
        foreach (range(2, 11) as $number) {
            ExpenseCategory::create($attributes + ['code' => "UJI-{$number}", 'name' => sprintf('Perlengkapan %02d', $number)]);
        }

        $this->get(route('accounting.master.index', [$type, 'q' => 'Perlengkapan']))
            ->assertOk()->assertSee('Cari Kategori Pengeluaran')
            ->assertViewHas('items', fn ($items) => $items->total() === 11
                && $items->count() === 10
                && str_contains($items->nextPageUrl(), 'q=Perlengkapan')
                && $items->getCollection()->every(fn ($row) => $row->relationLoaded('financialGroup')));
        $this->get(route('accounting.master.index', [$type, 'q' => 'Perlengkapan', 'page' => 2]))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->count() === 1);
        $this->get(route('accounting.master.index', [$type, 'q' => 'UJI_', 'edit' => $category->id]))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->is($category))
            ->assertViewHas('editingItem', fn ($item) => $item->is($category));

        $this->put(route('accounting.master.update', [$type, $category->id]), $attributes + ['code' => 'UJI_01', 'name' => 'Alat Uji'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->get(route('accounting.master.index', [$type, 'q' => 'Alat Uji']))->assertOk()->assertSee('Alat Uji');
        $this->delete(route('accounting.master.destroy', [$type, $category->id]))->assertSessionHas('success');
        $this->get(route('accounting.master.index', [$type, 'q' => 'Alat Uji']))
            ->assertOk()->assertSee('Kategori pengeluaran tidak ditemukan.');
    }

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
