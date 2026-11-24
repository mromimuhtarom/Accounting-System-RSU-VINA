<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;

class TransactionPerformanceTest extends ExpenseAccessTest
{
    protected function usesLegacyExpenseSchema(): bool
    {
        return in_array($this->name(), [
            'test_expense_account_migration_updates_coa_and_category',
            'test_expense_coa_migration_moves_only_expenses_linked_to_the_source_account',
            'test_expense_category_migration_moves_source_category_and_uses_destination_account',
            'test_report_groups_migration_preserves_expense_classification',
        ], true);
    }

    public function test_report_groups_migration_preserves_expense_classification(): void
    {
        $id = DB::table('chart_accounts')->where('code', '5001')->value('id');
        $category = DB::table('expense_categories')->where('expense_account_id', $id)->first();
        $expense = DB::table('expenses')->insertGetId([
            'expense_no' => 'GROUP-MIGRATION', 'expense_date' => '2026-11-24', 'description' => 'Migrasi',
            'amount' => 12500, 'payment_method' => 'CASH', 'expense_category_id' => $category->id,
            'chart_account_id' => $id, 'cash_bank_account_id' => DB::table('cash_bank_accounts')->value('id'),
            'created_by' => DB::table('users')->value('id'),
        ]);
        $migration = require database_path('migrations/2026_11_24_000007_move_report_groups_to_coa.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('chart_accounts', ['id' => $id, 'financial_group_id' => $category->financial_group_id]);
        $this->assertDatabaseHas('expenses', ['id' => $expense, 'chart_account_id' => $id, 'amount' => 12500]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('expenses', 'expense_category_id'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('expense_categories'));
    }

    public function test_master_coa_can_show_revenue_and_expense_accounts_separately(): void
    {
        $income = ChartAccount::create(['code'=>'TEST-REV', 'name'=>'Kelompok Uji', 'type'=>'REVENUE', 'normal_balance'=>'CREDIT']);
        $expense = ChartAccount::create(['code'=>'TEST-EXP', 'name'=>'Kelompok Uji', 'type'=>'EXPENSE', 'normal_balance'=>'DEBIT']);

        foreach ([$income, $expense] as $account) {
            $page = $this->get(route('accounting.master.index', ['accounts', 'account_type'=>$account->type, 'group'=>'general', 'q'=>'Kelompok Uji']))
                ->assertOk()->assertSee('COA Pemasukan')->assertSee('COA Pengeluaran')
                ->assertViewHas('items', fn ($items) => $items->getCollection()->modelKeys() === [$account->id]);
            $page->assertSee('value="'.$account->type.'" selected', false);
            $html = new \DOMDocument();
            $html->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($html);
            $groupType = $account->type === 'REVENUE' ? 'INCOME' : 'EXPENSE';
            $this->assertSame(3, $xpath->query("//select[@id='coa-financial-group']//option[@data-group-type='$groupType' and not(@disabled)]")->length);
            $this->assertSame(0, $xpath->query("//select[@id='coa-financial-group']//option[@data-group-type!='$groupType' and not(@disabled)]")->length);
        }
    }

    public function test_restoring_report_groups_preserves_existing_group_settings(): void
    {
        DB::table('financial_groups')->where('code', 'REV_BPJS')->delete();
        DB::table('financial_groups')->where('code', 'EXP_HR')->update(['name' => 'SDM Khusus', 'is_active' => false]);
        $migration = require database_path('migrations/2026_11_24_000008_restore_missing_default_report_groups.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('financial_groups', ['code' => 'REV_BPJS', 'type' => 'INCOME', 'is_active' => true]);
        $this->assertDatabaseHas('financial_groups', ['code' => 'EXP_HR', 'name' => 'SDM Khusus', 'is_active' => false]);
        $this->assertDatabaseCount('financial_groups', 6);
    }

    #[TestWith(['2026_11_24_000001_move_office_supply_expenses_to_5_1_h.php', '5-1008', '5.1.H', 'XLS_5_1_H'])]
    #[TestWith(['2026_11_24_000002_move_unmapped_expenses_to_5_1_i.php', 'IMPORT-EXPENSE-UNMAPPED', '5.1.I', 'XLS_5_1_I'])]
    #[TestWith(['2026_11_24_000003_move_medicine_expenses_to_5_2_a.php', '5-1005', '5.2.A', 'XLS_5_2_A'])]
    #[TestWith(['2026_11_24_000006_move_transfusion_expenses_to_5_3_c.php', '5-1002', '5.3.C', 'XLS_5_3_C'])]
    public function test_expense_account_migration_updates_coa_and_category(string $file, string $from, string $to, string $destinationCode): void
    {
        $migration = require database_path('migrations/'.$file);
        $attributes = ['name' => 'ATK', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true];
        $source = ChartAccount::create($attributes + ['code' => $from]);
        $target = ChartAccount::create($attributes + ['code' => $to]);
        $category = LegacyExpenseCategory::create(['code' => 'ATK', 'name' => 'ATK', 'expense_account_id' => $source->id]);
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

        $destination = LegacyExpenseCategory::create(['code' => $destinationCode, 'name' => 'Kategori Tujuan', 'expense_account_id' => $target->id, 'is_active' => true]);
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
        $category = LegacyExpenseCategory::where('code', 'UMUM')->firstOrFail();
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
        $source = LegacyExpenseCategory::create(['code' => $from, 'name' => 'Kategori Lama', 'expense_account_id' => $oldAccount->id]);
        $other = LegacyExpenseCategory::where('code', 'UMUM')->firstOrFail();
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
        $target = LegacyExpenseCategory::create(['code' => $to, 'name' => 'Kategori Tujuan', 'expense_account_id' => $newAccount->id, 'is_active' => true]);
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expenses', ['id' => $moved, 'expense_category_id' => $target->id, 'chart_account_id' => $newAccount->id, 'amount' => 10000]);
        $this->assertDatabaseHas('expenses', ['id' => $unchanged, 'expense_category_id' => $other->id, 'chart_account_id' => $oldAccount->id]);
    }

    #[TestWith(['2026_11_23_000001_move_receipts_from_1_1_m_to_1_1_o.php', '1.1.M', '1.1.O'])]
    #[TestWith(['2026_11_24_000004_move_laboratory_receipts_to_1_1_q.php', '4-1004', '1.1.Q'])]
    public function test_receipt_coa_migration_moves_only_source_accounts_and_unmapped_legacy_codes(string $file, string $from, string $to): void
    {
        $migration = require database_path('migrations/'.$file);
        $attributes = ['name' => 'Pendapatan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT', 'is_active' => true];
        $source = ChartAccount::create($attributes + ['code' => $from]);
        $other = ChartAccount::create($attributes + ['code' => '1.1.P']);
        $records = ['transaction_date' => '2026-11-20', 'amount' => 10000, 'cashier_id' => DB::table('users')->value('id')];
        $mappedId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-1', 'chart_account_id' => $source->id]);
        $legacyId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-2', 'income_code' => $from]);
        $otherId = DB::table('receipts')->insertGetId($records + ['transaction_no' => 'COA-3', 'chart_account_id' => $other->id, 'income_code' => $from]);

        try {
            $migration->up();
            $this->fail('The migration should stop if the destination account is missing.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('COA tujuan '.$to, $error->getMessage());
        }
        $this->assertDatabaseHas('receipts', ['id' => $mappedId, 'chart_account_id' => $source->id]);

        $target = ChartAccount::create($attributes + ['code' => $to]);
        $migration->up();
        $migration->up();

        foreach ([$mappedId, $legacyId] as $id) {
            $this->assertDatabaseHas('receipts', ['id' => $id, 'chart_account_id' => $target->id, 'income_code' => $to, 'amount' => 10000]);
        }
        $this->assertDatabaseHas('receipts', ['id' => $otherId, 'chart_account_id' => $other->id, 'income_code' => $from]);
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
        $row = ['expense_date'=>'2026-11-13', 'chart_account_id'=>ChartAccount::where('code', '5001')->value('id'),
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
        $this->assertDatabaseHas('chart_accounts', ['code' => '5001']);
    }
}

class LegacyExpenseCategory extends Model
{
    protected $table = 'expense_categories';

    protected $guarded = [];
}
