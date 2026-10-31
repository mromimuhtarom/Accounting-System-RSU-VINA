<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OpeningBalance;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AccountingAnalysis;
use App\Services\FinancialPosition;
use App\Services\FinancialSources;
use App\Services\LedgerAmounts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialPositionTest extends TestCase
{
    private User $admin;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_08_18_000001_add_accounting_fields_to_users_table.php', '2026_08_18_000002_create_applications_and_roles_tables.php', '2026_08_18_000003_create_chart_accounts_table.php', '2026_08_18_000007_create_journal_tables.php', '2026_10_29_000005_create_opening_balances.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $application = DB::table('applications')->insertGetId(['code' => 'ACCOUNTING', 'name' => 'Accounting']);
        foreach (['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'KASIR', 'PETUGAS_ASET', 'OWNER', 'FINANCE'] as $role) {
            DB::table('roles')->insert(['application_id' => $application, 'code' => $role, 'name' => $role]);
        }
        $this->admin = $this->user('ADMIN_ACCOUNTING');
        $this->actingAs($this->admin);
        foreach (['cash' => ['1001', 'Kas', 'ASSET', 'DEBIT'], 'equipment' => ['1501', 'Alat medis', 'ASSET', 'DEBIT'], 'accumulated' => ['1591', 'Akumulasi penyusutan', 'ASSET', 'CREDIT'], 'debt' => ['2001', 'Utang', 'LIABILITY', 'CREDIT'], 'equity' => ['3001', 'Modal', 'EQUITY', 'CREDIT'], 'revenue' => ['4001', 'Pendapatan', 'REVENUE', 'CREDIT'], 'expense' => ['5001', 'Beban', 'EXPENSE', 'DEBIT']] as $key => [$code,$name,$type,$normal]) {
            $this->accounts[$key] = ChartAccount::create(['code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_active' => true]);
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(DB::table('roles')->where('code', $role)->value('id'));

        return $user;
    }

    private function data(array $changes = []): array
    {
        return array_replace(['version' => 0, 'balance_date' => '2025-12-31', 'evidence' => 'Rekonsiliasi kas dan laporan saldo sebelumnya telah diperiksa', 'lines' => [
            ['chart_account_id' => $this->accounts['cash']->id, 'amount' => '100000000.00', 'side' => 'NORMAL'],
            ['chart_account_id' => $this->accounts['equity']->id, 'amount' => '100000000.00', 'side' => 'NORMAL'],
        ]], $changes);
    }

    private function postOpening(array $data = []): OpeningBalance
    {
        $this->post(route('opening-balances.store'), $data ?: $this->data())->assertSessionHasNoErrors();
        $b = OpeningBalance::sole();
        $this->post(route('opening-balances.approve', $b), ['version' => $b->version, 'verified' => '1'])->assertSessionHasNoErrors();
        $this->post(route('opening-balances.post', $b), ['version' => $b->fresh()->version])->assertSessionHasNoErrors();

        return $b->fresh();
    }

    private function journal(string $date, array $lines): JournalEntry
    {
        $journal = JournalEntry::create(['journal_no' => 'TEST-'.uniqid(), 'journal_date' => $date, 'description' => 'Jurnal uji', 'status' => 'POSTED', 'created_by' => $this->admin->id]);
        foreach ($lines as [$key,$debit,$credit]) {
            $journal->lines()->create(['chart_account_id' => $this->accounts[$key]->id, 'debit' => $debit, 'credit' => $credit]);
        }

        return $journal;
    }

    public function test_draft_approval_and_idempotent_posting_create_balanced_opening_once(): void
    {
        $this->get(route('opening-balances.index'))->assertOk()->assertSee('Data apa yang disiapkan');
        $this->post(route('opening-balances.store'), $this->data())->assertSessionHasNoErrors();
        $balance = OpeningBalance::sole();
        $this->assertSame(0, JournalEntry::count());
        $this->post(route('opening-balances.post', $balance), ['version' => $balance->version])->assertSessionHasErrors('opening');
        $this->post(route('opening-balances.approve', $balance), ['version' => $balance->version, 'verified' => 1])->assertSessionHasNoErrors();
        foreach ([1, 2] as $_) {
            $this->post(route('opening-balances.post', $balance), ['version' => $balance->fresh()->version])->assertSessionHasNoErrors();
        }
        $this->assertSame(1, JournalEntry::count());
        $journal = JournalEntry::sole();
        $this->assertEquals($journal->lines()->sum('debit'), $journal->lines()->sum('credit'));
        $this->assertDatabaseHas('opening_balance_audits', ['action' => 'POST', 'actor_id' => $this->admin->id]);
        $this->post(route('opening-balances.store'), $this->data(['version' => $balance->fresh()->version]))->assertSessionHasErrors('opening');
    }

    public function test_unbalanced_duplicate_revenue_and_parent_accounts_are_rejected(): void
    {
        $data = $this->data();
        $data['lines'][1]['amount'] = '99999999.99';
        $this->post(route('opening-balances.store'), $data)->assertSessionHasErrors('opening');
        $data = $this->data();
        $data['lines'][1]['chart_account_id'] = $this->accounts['revenue']->id;
        $this->post(route('opening-balances.store'), $data)->assertSessionHasErrors('opening');
        $data = $this->data();
        $data['lines'][] = $data['lines'][0];
        $this->post(route('opening-balances.store'), $data)->assertSessionHasErrors('opening');
        ChartAccount::create(['code' => '100101', 'name' => 'Sub kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'parent_id' => $this->accounts['cash']->id]);
        $this->post(route('opening-balances.store'), $this->data())->assertSessionHasErrors('opening');
        $this->assertSame(0, OpeningBalance::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_approval_rechecks_account_and_can_return_to_draft(): void
    {
        $this->post(route('opening-balances.store'), $this->data())->assertSessionHasNoErrors();
        $b = OpeningBalance::sole();
        $this->accounts['cash']->update(['is_active' => false]);
        $this->post(route('opening-balances.approve', $b), ['version' => $b->version, 'verified' => 1])->assertSessionHasErrors('opening');
        $this->accounts['cash']->update(['is_active' => true]);
        $this->post(route('opening-balances.approve', $b), ['version' => $b->version, 'verified' => 1])->assertSessionHasNoErrors();
        $this->post(route('opening-balances.draft', $b), ['version' => $b->fresh()->version, 'reason' => 'Koreksi dokumen saldo awal'])->assertSessionHasNoErrors();
        $this->assertSame('DRAFT', $b->fresh()->status);
        $this->assertNull($b->fresh()->approved_by);
        $this->post(route('opening-balances.store'), $this->data(['version' => 0]))->assertSessionHasErrors('opening');
    }

    public function test_opening_date_must_precede_existing_journals_and_post_rechecks_it(): void
    {
        $this->journal('2026-01-01', [['cash', '10.00', '0.00'], ['equity', '0.00', '10.00']]);
        $this->post(route('opening-balances.store'), $this->data(['balance_date' => '2026-01-01']))->assertSessionHasErrors('opening');
        $this->post(route('opening-balances.store'), $this->data())->assertSessionHasNoErrors();
        $b = OpeningBalance::sole();
        $this->post(route('opening-balances.approve', $b), ['version' => $b->version, 'verified' => 1])->assertSessionHasNoErrors();
        $this->journal('2025-12-31', [['cash', '1.00', '0.00'], ['equity', '0.00', '1.00']]);
        $this->post(route('opening-balances.post', $b), ['version' => $b->fresh()->version])->assertSessionHasErrors('opening');
        $this->assertSame('APPROVED', $b->fresh()->status);
    }

    public function test_balance_sheet_uses_cutoff_and_real_unclosed_result(): void
    {
        $this->postOpening();
        $this->journal('2026-01-31', [['cash', '10000000.00', '0.00'], ['revenue', '0.00', '10000000.00']]);
        $this->journal('2026-01-31', [['expense', '4000000.00', '0.00'], ['cash', '0.00', '4000000.00']]);
        $this->journal('2026-02-01', [['cash', '5000000.00', '0.00'], ['debt', '0.00', '5000000.00']]);
        $reader = app(FinancialPosition::class);
        $jan = $reader->at('2026-01-31');
        $this->assertSame(10600000000, $jan['assets']);
        $this->assertSame(600000000, $jan['unclosed_result']);
        $this->assertSame(10600000000, $jan['equity']);
        $this->assertSame(0, $jan['difference']);
        $this->assertSame(0, $jan['liabilities']);
        $this->assertSame(500000000, $reader->at('2026-02-01')['liabilities']);
        $this->assertTrue($reader->at('2025-12-30')['before_opening']);
        $this->get(route('reports.financial-position', ['as_of' => '2026-01-31']))->assertOk()->assertSee('6.000.000,00')->assertSee('Seimbang secara jurnal')->assertSee('Neraca_2026-01-31.pdf');
        $this->get(route('reports.financial-trial-balance', ['as_of' => '2026-01-31']))->assertOk()->assertSee('Neraca Saldo');
    }

    public function test_contra_assets_and_inactive_historical_accounts_are_retained(): void
    {
        $data = $this->data(['lines' => [
            ['chart_account_id' => $this->accounts['cash']->id, 'amount' => '80000000.00', 'side' => 'NORMAL'],
            ['chart_account_id' => $this->accounts['equipment']->id, 'amount' => '20000000.00', 'side' => 'NORMAL'],
            ['chart_account_id' => $this->accounts['accumulated']->id, 'amount' => '3000000.00', 'side' => 'NORMAL'],
            ['chart_account_id' => $this->accounts['equity']->id, 'amount' => '97000000.00', 'side' => 'NORMAL'],
        ]]);
        $this->postOpening($data);
        $this->accounts['cash']->update(['is_active' => false]);
        $p = app(FinancialPosition::class)->at('2026-01-01');
        $this->assertSame(9700000000, $p['assets']);
        $this->assertSame(0, $p['difference']);
        $contra = collect($p['groups']['ASSET'])->firstWhere('id', $this->accounts['accumulated']->id);
        $this->assertSame(-300000000, $contra['balance']);
        $this->get(route('reports.financial-position', ['as_of' => '2026-01-01']))->assertOk()->assertSee('saldo historis tetap ditampilkan');
    }

    public function test_corrupt_unbalanced_ledger_is_shown_without_fabricated_equity(): void
    {
        $this->journal('2026-01-01', [['cash', '0.03', '0.00']]);
        $p = app(FinancialPosition::class)->at('2026-01-01');
        $this->assertSame(3, $p['assets']);
        $this->assertSame(0, $p['equity']);
        $this->assertSame(3, $p['difference']);
        $this->get(route('reports.financial-position', ['as_of' => '2026-01-01']))->assertOk()->assertSee('Terdapat selisih jurnal Rp 0,03')->assertSee('Saldo awal belum terposting');
        $this->assertSame('1.234.567.890.123,45', LedgerAmounts::display(LedgerAmounts::cents('1234567890123.45')));
    }

    public function test_closed_revenue_balances_do_not_double_count_profit_in_equity(): void
    {
        $this->journal('2025-12-30', [['cash', '10.00', '0.00'], ['revenue', '0.00', '10.00']]);
        $this->journal('2025-12-31', [['revenue', '10.00', '0.00'], ['equity', '0.00', '10.00']]);
        $p = app(FinancialPosition::class)->at('2025-12-31');
        $this->assertSame(0, $p['unclosed_result']);
        $this->assertSame(1000, $p['equity']);
        $this->assertSame(0, $p['difference']);
    }

    public function test_permissions_separate_accounting_approval_and_read_only_roles(): void
    {
        $this->actingAs($this->user('ACCOUNTING'));
        $this->post(route('opening-balances.store'), $this->data())->assertSessionHasNoErrors();
        $b = OpeningBalance::sole();
        $this->post(route('opening-balances.approve', $b), ['version' => $b->version, 'verified' => 1])->assertForbidden();
        foreach (['KASIR', 'PETUGAS_ASET', 'OWNER', 'FINANCE'] as $role) {
            $this->actingAs($this->user($role));
            $this->post(route('opening-balances.store'), $this->data())->assertForbidden();
            $this->post(route('opening-balances.post', $b), ['version' => $b->version])->assertForbidden();
            if (in_array($role, ['OWNER', 'FINANCE'], true)) {
                $this->get(route('reports.financial-position'))->assertOk();
            } else {
                $this->get(route('reports.financial-position'))->assertForbidden();
            }
        }
        auth()->logout();
        $this->get(route('reports.financial-position'))->assertRedirect(route('login'));
    }

    public function test_ledger_drilldown_and_invalid_dates(): void
    {
        $j = $this->journal('2026-01-31', [['cash', '1.25', '0.00'], ['equity', '0.00', '1.25']]);
        $this->get(route('reports.financial-ledger', ['account' => $this->accounts['cash'], 'as_of' => '2026-01-31']))->assertOk()->assertSee($j->journal_no)->assertSee('1,25');
        $this->get(route('reports.financial-position', ['as_of' => 'bukan-tanggal']))->assertSessionHasErrors('as_of');
        $this->get(route('reports.financial-position', ['as_of' => '2099-01-01']))->assertSessionHasErrors('as_of');
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_dashboard_neraca_summary_distinguishes_empty_books_from_posted_balances_and_uses_report_date(): void
    {
        $empty = view('dashboard-financial-position', ['financialPosition' => app(FinancialPosition::class)->at('2025-12-31')])->render();
        $this->assertStringContainsString('Pembukuan neraca belum tersedia', $empty);
        $this->assertStringContainsString('Saldo awal belum terposting', $empty);
        $this->postOpening();
        $this->journal('2026-02-01', [['cash', '10.00', '0.00'], ['revenue', '0.00', '10.00']]);
        $position = app(FinancialPosition::class)->at('2026-01-31');
        $html = view('dashboard-financial-position', ['financialPosition' => $position])->render();
        $this->assertStringContainsString('31/01/2026', $html);
        $this->assertStringContainsString('100.000.000,00', $html);
        $this->assertStringNotContainsString('100.000.010,00', $html);
        $this->assertStringNotContainsString('Saldo awal belum terposting', $html);
        $this->assertStringContainsString('as_of=2026-01-31', $html);
    }

    public function test_navigation_places_dashboard_first_and_groups_bookkeeping_without_duplicate_inventory_input(): void
    {
        $html = view('layouts.partials.financial-navigation')->render();
        $this->assertLessThan(strpos($html, 'Pembukuan'), strpos($html, 'Dashboard'));
        $this->assertStringContainsString('Ruang Kerja Accounting', $html);
        $this->assertStringNotContainsString(route('reports.financial-sources'), $html);
        $this->assertStringNotContainsString(route('opening-balances.index'), $html);
        $this->assertStringNotContainsString(route('assets.create'), $html);
        $this->assertStringContainsString('Inventaris Barang', $html);
        $this->actingAs($this->user('KASIR'));
        $cashier = view('layouts.partials.financial-navigation')->render();
        $this->assertStringContainsString('Dashboard', $cashier);
        $this->assertStringNotContainsString('Pembukuan', $cashier);
    }

    private function noncashData(array $changes = []): array
    {
        return array_replace(['entry_token' => (string) Str::uuid(), 'kind' => 'expense_accrual', 'journal_date' => '2026-01-31', 'reference' => 'INV-ACCRUAL-001', 'description' => 'Tagihan listrik sudah diverifikasi, belum dibayar', 'amount' => '1250.25', 'debit_account_id' => $this->accounts['expense']->id, 'credit_account_id' => $this->accounts['debt']->id, 'verified' => '1'], $changes);
    }

    public function test_guided_noncash_entry_is_balanced_idempotent_and_changes_liabilities_without_cash_receipt(): void
    {
        $this->get(route('accounting.workspace'))->assertOk()->assertSee('Input Penerimaan')->assertSee('Input Pencatatan Nonkas');
        $this->get(route('accounting.entries.create'))->assertOk()->assertSee('Posting Pencatatan Nonkas');
        $data = $this->noncashData();
        foreach ([1, 2] as $_) {
            $this->post(route('accounting.entries.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(2, JournalLine::count());
        $this->assertSame('1250.25', JournalEntry::sole()->lines->first()->debit);
        $p = app(FinancialPosition::class)->at('2026-01-31');
        $this->assertSame(125025, $p['liabilities']);
        $this->assertSame(-125025, $p['equity']);
        $this->assertSame(0, $p['assets']);
        $this->assertSame(0, $p['difference']);
        $this->post(route('accounting.entries.store'), array_replace($data, ['amount' => '1250.26']))->assertSessionHasErrors('entry');
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_noncash_entries_reject_wrong_inactive_parent_same_accounts_and_missing_verification(): void
    {
        $this->post(route('accounting.entries.store'), $this->noncashData(['verified' => null]))->assertSessionHasErrors('verified');
        $this->post(route('accounting.entries.store'), $this->noncashData(['amount' => '1.001']))->assertSessionHasErrors('amount');
        $this->post(route('accounting.entries.store'), $this->noncashData(['credit_account_id' => $this->accounts['expense']->id]))->assertSessionHasErrors('credit_account_id');
        $this->post(route('accounting.entries.store'), $this->noncashData(['credit_account_id' => $this->accounts['cash']->id]))->assertSessionHasErrors('entry');
        $this->accounts['expense']->update(['is_active' => false]);
        $this->post(route('accounting.entries.store'), $this->noncashData())->assertSessionHasErrors('entry');
        $this->accounts['expense']->update(['is_active' => true]);
        ChartAccount::create(['code' => '5001-1', 'name' => 'Anak beban', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true, 'parent_id' => $this->accounts['expense']->id]);
        $this->post(route('accounting.entries.store'), $this->noncashData())->assertSessionHasErrors('entry');
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_noncash_role_and_opening_cutoff_guards_remain_enforced(): void
    {
        $this->postOpening();
        $this->post(route('accounting.entries.store'), $this->noncashData(['journal_date' => '2025-12-31']))->assertSessionHasErrors('journal_date');
        $this->post(route('accounting.entries.store'), $this->noncashData(['journal_date' => '2099-01-01']))->assertSessionHasErrors('journal_date');
        foreach (['OWNER', 'FINANCE', 'KASIR'] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('accounting.entries.create'))->assertForbidden();
            $this->post(route('accounting.entries.store'), $this->noncashData())->assertForbidden();
        }
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_analysis_distinguishes_period_mutations_from_cumulative_balances_and_does_not_include_future_entries(): void
    {
        $this->postOpening();
        $this->journal('2026-01-15', [['cash', '100.25', '0.00'], ['revenue', '0.00', '100.25']]);
        $this->journal('2026-01-20', [['expense', '10.10', '0.00'], ['debt', '0.00', '10.10']]);
        $this->journal('2026-02-01', [['cash', '999.00', '0.00'], ['revenue', '0.00', '999.00']]);
        $a = app(AccountingAnalysis::class)->period('2026-01-01', '2026-01-31');
        $this->assertSame('2025-12-31', $a['before']);
        $this->assertSame(10000000000, $a['start']['assets']);
        $this->assertSame(10000010025, $a['end']['assets']);
        $this->assertSame(1010, $a['end']['liabilities']);
        $cash = collect($a['rows'])->firstWhere('id', $this->accounts['cash']->id);
        $this->assertSame(10025, $cash['period_debit']);
        $this->assertSame(10025, $cash['change']);
        $this->get(route('accounting.analysis', ['from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk()->assertSee('Analisis Aset')->assertSee('100.000.100,25');
        $this->get(route('accounting.analysis', ['from' => '2026-02-01', 'to' => '2026-01-31']))->assertSessionHasErrors('from');
        $this->get(route('accounting.analysis', ['to' => '2099-01-01']))->assertSessionHasErrors('to');
        $this->actingAs($this->user('OWNER'))->get(route('accounting.workspace'))->assertOk()->assertDontSee('Input Pencatatan Nonkas');
        $this->get(route('accounting.analysis', ['from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk();
        $this->actingAs($this->user('KASIR'))->get(route('accounting.analysis'))->assertForbidden();
    }

    public function test_noncash_asset_entries_exclude_cash_accounts_and_require_open_asset_period_and_correct_contra_account(): void
    {
        Schema::create('cash_bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('chart_account_id');
        });
        DB::table('cash_bank_accounts')->insert(['chart_account_id' => $this->accounts['cash']->id]);
        $data = $this->noncashData(['kind' => 'asset_credit', 'debit_account_id' => $this->accounts['cash']->id]);
        $this->post(route('accounting.entries.store'), $data)->assertSessionHasErrors('entry');
        Schema::create('asset_periods', function (Blueprint $t) {
            $t->id();
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status');
        });
        DB::table('asset_periods')->insert(['starts_at' => '2026-01-01', 'ends_at' => '2026-01-31', 'status' => 'CLOSED']);
        $data = $this->noncashData(['kind' => 'asset_credit', 'debit_account_id' => $this->accounts['equipment']->id]);
        $this->post(route('accounting.entries.store'), $data)->assertSessionHasErrors('entry');
        DB::table('asset_periods')->update(['status' => 'OPEN']);
        $this->post(route('accounting.entries.store'), $data)->assertSessionHasNoErrors();
        $depreciation = $this->noncashData(['kind' => 'depreciation', 'credit_account_id' => $this->accounts['equipment']->id]);
        $this->post(route('accounting.entries.store'), $depreciation)->assertSessionHasErrors('entry');
        $depreciation['credit_account_id'] = $this->accounts['accumulated']->id;
        $this->post(route('accounting.entries.store'), $depreciation)->assertSessionHasNoErrors();
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(-125025, collect(app(FinancialPosition::class)->at('2026-01-31')['rows'])->firstWhere('id', $this->accounts['accumulated']->id)['balance']);
    }

    private function sourceTables(): void
    {
        foreach (['receipts' => 'transaction_date', 'expenses' => 'expense_date'] as $table => $date) {
            Schema::create($table, function (Blueprint $t) use ($date) {
                $t->id();
                $t->date($date);
                $t->string('status');
                $t->decimal('amount', 18, 2);
                $t->unsignedBigInteger('cash_bank_account_id')->nullable();
                $t->unsignedBigInteger('chart_account_id')->nullable();
                foreach (['transaction_no', 'expense_no', 'notes', 'patient_name', 'description'] as $column) {
                    $t->string($column)->nullable();
                }
            });
        }
        Schema::create('insurance_monthly_claims', function (Blueprint $t) {
            $t->id();
            $t->decimal('claimed_amount', 18, 2);
            $t->decimal('approved_amount', 18, 2)->nullable();
            $t->decimal('paid_amount', 18, 2);
            $t->string('status');
        });
    }

    public function test_source_report_keeps_tracking_out_of_ledger_and_counts_only_active_transactions_through_cutoff(): void
    {
        $this->sourceTables();
        DB::table('receipts')->insert([
            ['transaction_date' => '2026-01-31', 'amount' => '100.25', 'status' => 'ACTIVE', 'cash_bank_account_id' => null],
            ['transaction_date' => '2026-02-01', 'amount' => '999.00', 'status' => 'ACTIVE', 'cash_bank_account_id' => null],
            ['transaction_date' => '2026-01-31', 'amount' => '1000.00', 'status' => 'CANCELLED', 'cash_bank_account_id' => null],
        ]);
        DB::table('expenses')->insert(['expense_date' => '2026-01-31', 'amount' => '10.10', 'status' => 'ACTIVE']);
        DB::table('insurance_monthly_claims')->insert([
            ['claimed_amount' => '200.00', 'approved_amount' => '150.00', 'paid_amount' => '50.00', 'status' => 'SEBAGIAN'],
            ['claimed_amount' => '50.00', 'approved_amount' => null, 'paid_amount' => '0.00', 'status' => 'DIAJUKAN'],
            ['claimed_amount' => '30.00', 'approved_amount' => '20.00', 'paid_amount' => '0.00', 'status' => 'DITOLAK'],
        ]);
        $s = app(FinancialSources::class)->at('2026-01-31');
        $this->assertSame(10025, $s['books']['receipts']['amount']);
        $this->assertSame(1010, $s['books']['expenses']['amount']);
        $this->assertSame(1, $s['books']['receipts']['unposted_count']);
        $this->assertSame(15000, $s['tracking']['approved']);
        $this->assertSame(10000, $s['tracking']['remaining']);
        $this->assertSame(1, $s['tracking']['unverified']);
        $this->get(route('reports.financial-sources', ['as_of' => '2026-01-31']))->assertOk()->assertSee('pemantauan terkini')->assertSee('Input penerimaan yang benar-benar cair');
        $position = app(FinancialPosition::class)->at('2026-01-31');
        $this->assertSame(0, $position['assets']);
        $this->assertSame(0, $position['equity']);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(3, DB::table('receipts')->count());
    }

    public function test_source_pending_totals_exclude_transactions_covered_by_opening_and_already_posted_receipts(): void
    {
        $this->sourceTables();
        $this->postOpening();
        DB::table('receipts')->insert(['transaction_date' => '2025-12-31', 'amount' => '25.00', 'status' => 'ACTIVE']);
        $posted = DB::table('receipts')->insertGetId(['transaction_date' => '2026-01-01', 'amount' => '30.00', 'status' => 'ACTIVE']);
        DB::table('receipts')->insert(['transaction_date' => '2026-01-02', 'amount' => '40.00', 'status' => 'ACTIVE']);
        $j = $this->journal('2026-01-01', [['cash', '30.00', '0.00'], ['revenue', '0.00', '30.00']]);
        $j->update(['source_type' => Receipt::class, 'source_id' => $posted]);
        $s = app(FinancialSources::class)->at('2026-01-31');
        $this->assertSame(9500, $s['books']['receipts']['amount']);
        $this->assertSame(4000, $s['books']['receipts']['unposted_amount']);
        $this->assertSame('2025-12-31', $s['coverage_from']);
        $this->assertSame(1, $s['books']['receipts']['unposted_count']);
    }

    public function test_source_report_authorization_and_dates(): void
    {
        $this->get(route('reports.financial-sources', ['as_of' => '2099-12-31']))->assertSessionHasErrors('as_of');
        foreach (['OWNER', 'FINANCE'] as $role) {
            $this->actingAs($this->user($role))->get(route('reports.financial-sources'))->assertOk()->assertDontSee('Input penerimaan yang benar-benar cair');
        }
        $this->actingAs($this->user('KASIR'))->get(route('reports.financial-sources'))->assertForbidden();
    }

    public function test_source_transaction_list_filters_mapped_posted_future_and_cancelled_rows_without_mutation(): void
    {
        $this->sourceTables();
        DB::table('receipts')->insert([
            ['transaction_date' => '2026-01-31', 'amount' => '100.25', 'status' => 'ACTIVE', 'transaction_no' => 'NEEDS-MAPPING'],
            ['transaction_date' => '2026-02-01', 'amount' => '999.00', 'status' => 'ACTIVE', 'transaction_no' => 'FUTURE-RECEIPT'],
            ['transaction_date' => '2026-01-31', 'amount' => '1000.00', 'status' => 'CANCELLED', 'transaction_no' => 'CANCELLED-RECEIPT'],
        ]);
        $this->get(route('reports.financial-source-transactions', ['kind' => 'receipts', 'as_of' => '2026-01-31', 'unmapped' => 1]))
            ->assertOk()->assertSee('NEEDS-MAPPING')->assertDontSee('FUTURE-RECEIPT')->assertDontSee('CANCELLED-RECEIPT')->assertSee('Periksa sumber');
        $this->get(route('reports.financial-source-transactions', ['kind' => 'expenses', 'as_of' => '2026-01-31']))->assertOk()->assertSee('Tidak ada transaksi sesuai filter');
        $this->get(route('reports.financial-source-transactions', ['kind' => 'invalid']))->assertNotFound();
        $this->actingAs($this->user('OWNER'))->get(route('reports.financial-source-transactions', ['kind' => 'receipts', 'as_of' => '2026-01-31']))->assertOk()->assertDontSee('Periksa sumber</a>', false);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(3, DB::table('receipts')->count());
    }

    public function test_posted_opening_locks_its_journal_and_prevents_backdated_posting(): void
    {
        $b = $this->postOpening();
        try {
            $this->journal('2025-12-31', [['cash', '1.00', '0.00'], ['equity', '0.00', '1.00']]);
            $this->fail('Backdated posting must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('journal_date', $e->errors());
        }
        try {
            $b->journal->lines()->first()->update(['debit' => '1.00']);
            $this->fail('Opening lines must be locked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('journal', $e->errors());
        }
        $this->journal('2026-01-01', [['cash', '1.00', '0.00'], ['debt', '0.00', '1.00']]);
        $this->assertSame(2, JournalEntry::count());
    }
}
