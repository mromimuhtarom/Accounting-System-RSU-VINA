<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemAssistantTest extends TestCase
{
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_08_18_000001_add_accounting_fields_to_users_table.php', '2026_08_18_000002_create_applications_and_roles_tables.php', '2026_08_18_000003_create_chart_accounts_table.php', '2026_08_18_000007_create_journal_tables.php', '2026_10_29_000005_create_opening_balances.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $app = DB::table('applications')->insertGetId(['code' => 'ACCOUNTING', 'name' => 'Accounting']);
        foreach (['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KASIR', 'ASURANSI', 'OWNER', 'FINANCE', 'PETUGAS_ASET', 'IT'] as $role) {
            DB::table('roles')->insert(['application_id' => $app, 'code' => $role, 'name' => $role]);
        }
        Schema::create('guarantors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code');
            $t->string('category');
        });
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->string('transaction_no');
            $t->date('transaction_date');
            $t->string('income_code')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('amount', 18, 2);
            $t->string('status');
            $t->unsignedBigInteger('guarantor_id')->nullable();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('expense_no');
            $t->date('expense_date');
            $t->string('expense_type');
            $t->text('description');
            $t->string('reference_no')->nullable();
            $t->decimal('amount', 18, 2);
            $t->string('status');
        });
        Schema::create('insurance_monthly_claims', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('guarantor_id');
            $t->integer('year');
            $t->integer('month');
            $t->string('service_type');
            $t->string('status');
            $t->decimal('claimed_amount', 18, 2);
            $t->decimal('approved_amount', 18, 2)->nullable();
            $t->decimal('paid_amount', 18, 2)->default(0);
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->string('serial_number')->nullable();
            $t->string('location')->nullable();
            $t->string('condition');
            $t->string('status')->default('ACTIVE');
            $t->string('inventory_type')->default('MEDICAL');
            $t->integer('quantity')->default(1);
            $t->boolean('calibration_required')->default(false);
            $t->date('calibration_due_at')->nullable();
            $t->softDeletes();
        });
        $this->accountant = $this->user('ACCOUNTING');
        $this->actingAs($this->accountant);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->roles()->attach(DB::table('roles')->where('code', $role)->value('id'));

        return $u;
    }

    private function ask(array $data = [])
    {
        return $this->postJson(route('assistant.ask'), array_replace(['mode' => 'auto', 'message' => 'cara menggunakan sistem'], $data));
    }

    private function receipt(array $data = []): int
    {
        return DB::table('receipts')->insertGetId(array_replace(['transaction_no' => 'RCV-'.uniqid(), 'transaction_date' => '2026-01-31', 'income_code' => '4-01', 'notes' => 'Pembayaran contoh', 'amount' => '1234.50', 'status' => 'ACTIVE', 'guarantor_id' => null], $data));
    }

    public function test_transaction_analysis_matches_active_reports_includes_referrals_and_has_real_comparison_charts(): void
    {
        $this->receipt(['transaction_date' => '2025-12-31', 'amount' => '100.10']);
        $this->receipt(['amount' => '200.20']);
        $this->receipt(['amount' => '999.00', 'status' => 'CANCELLED']);
        $this->receipt(['transaction_date' => '2026-02-01', 'amount' => '999.00']);
        DB::table('expenses')->insert([['expense_no' => 'E1', 'expense_date' => '2026-01-31', 'expense_type' => 'GENERAL', 'description' => 'Beban', 'amount' => '10.10', 'status' => 'ACTIVE'], ['expense_no' => 'E2', 'expense_date' => '2026-01-31', 'expense_type' => 'REFERRAL', 'description' => 'Referal', 'amount' => '20.20', 'status' => 'ACTIVE']]);
        $this->ask(['message' => 'analisa laporan januari 2026'])->assertOk()->assertJsonPath('kind', 'analysis')->assertJsonPath('charts.0.items.0.value', '100.10')->assertJsonPath('charts.0.items.1.value', '200.20')->assertJsonPath('charts.0.items.3.value', '30.30')->assertSee('2025-12-01')->assertSee('100,00%')->assertSee('bukan saldo kas akhir atau laba');
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(4, DB::table('receipts')->count());
        $this->ask(['mode' => 'analysis', 'resource' => 'transactions', 'from' => '2026-01-01', 'to' => '2026-03-31'])->assertOk()->assertJsonPath('charts.1.items.2.label', '2026-03')->assertJsonPath('charts.1.items.2.value', '0.00')->assertJsonPath('charts.2.items.0.value', '30.30');
    }

    public function test_balance_analysis_uses_posted_journals_and_explains_negative_equity_and_missing_opening(): void
    {
        $cash = ChartAccount::create(['code' => '101', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => true]);
        $expense = ChartAccount::create(['code' => '501', 'name' => 'Beban', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT', 'is_active' => true]);
        $debt = ChartAccount::create(['code' => '201', 'name' => 'Utang', 'type' => 'LIABILITY', 'normal_balance' => 'CREDIT', 'is_active' => true]);
        $equity = ChartAccount::create(['code' => '301', 'name' => 'Modal', 'type' => 'EQUITY', 'normal_balance' => 'CREDIT', 'is_active' => true]);
        foreach ([['J1', '2025-12-31', $cash->id, $equity->id, '100.00'], ['J2', '2026-01-31', $expense->id, $debt->id, '200.00']] as [$no,$date,$debit,$credit,$value]) {
            $j = JournalEntry::create(['journal_no' => $no, 'journal_date' => $date, 'description' => 'Uji', 'status' => 'POSTED', 'created_by' => $this->accountant->id]);
            $j->lines()->createMany([['chart_account_id' => $debit, 'debit' => $value, 'credit' => 0], ['chart_account_id' => $credit, 'debit' => 0, 'credit' => $value]]);
        }
        $this->ask(['mode' => 'analysis', 'resource' => 'balance', 'from' => '2026-01-01', 'to' => '2026-01-31'])->assertOk()->assertJsonPath('charts.0.items.0.value', '100.00')->assertJsonPath('charts.0.items.1.value', '200.00')->assertJsonPath('charts.0.items.2.value', '-100.00')->assertSee('Ekuitas negatif')->assertSee('Saldo awal belum terposting');
        $this->assertSame(2, JournalEntry::count());
    }

    public function test_analysis_period_limits_authorization_and_current_page_context(): void
    {
        $this->ask(['mode' => 'analysis', 'resource' => 'transactions', 'from' => '2024-01-01', 'to' => '2026-01-31'])->assertUnprocessable();
        $this->ask(['mode' => 'analysis', 'resource' => 'transactions', 'to' => '2099-01-01'])->assertUnprocessable();
        $this->ask(['message' => 'baca grafik neraca 2026-02-31'])->assertUnprocessable();
        $this->ask(['mode' => 'analysis', 'message' => 'baca laporan halaman ini', 'context' => 'reports.financial-position', 'to' => '2026-01-31'])->assertOk()->assertSee('Belum ada jurnal terposting');
        $this->actingAs($this->user('KASIR'));
        $this->ask(['mode' => 'analysis', 'resource' => 'transactions'])->assertForbidden();
        $this->actingAs($this->user('ASURANSI'));
        $this->ask(['mode' => 'analysis', 'resource' => 'balance'])->assertForbidden();
        $this->actingAs($this->user('PETUGAS_ASET'));
        $this->ask(['mode' => 'analysis', 'resource' => 'transactions'])->assertForbidden();
        $this->ask(['message' => 'analisa inventaris'])->assertOk()->assertSee('inventaris terkini');
    }

    public function test_claim_analysis_is_current_tracking_and_asset_analysis_counts_units_separately_from_records(): void
    {
        DB::table('guarantors')->insert(['id' => 2, 'code' => 'BPJS', 'name' => 'BPJS', 'category' => 'BPJS']);
        DB::table('insurance_monthly_claims')->insert([['guarantor_id' => 2, 'year' => 2026, 'month' => 1, 'service_type' => 'RAWAT_JALAN', 'status' => 'SEBAGIAN', 'claimed_amount' => '100.00', 'approved_amount' => '80.00', 'paid_amount' => '30.00'], ['guarantor_id' => 2, 'year' => 2026, 'month' => 1, 'service_type' => 'RAWAT_INAP', 'status' => 'DITOLAK', 'claimed_amount' => '200.00', 'approved_amount' => '150.00', 'paid_amount' => '0.00']]);
        $this->actingAs($this->user('ASURANSI'));
        $this->ask(['message' => 'analisa klaim januari 2026'])->assertOk()->assertJsonPath('charts.0.items.1.value', '80.00')->assertJsonPath('charts.0.items.3.value', '50.00')->assertSee('bukan riwayat piutang');
        $this->actingAs($this->accountant);
        DB::table('assets')->insert([['name' => 'Alat', 'condition' => 'BROKEN', 'inventory_type' => 'MEDICAL', 'quantity' => 3, 'calibration_required' => true, 'calibration_due_at' => '2020-01-01'], ['name' => 'Kapasitas', 'condition' => 'GOOD', 'inventory_type' => 'FACILITY', 'quantity' => 99, 'calibration_required' => false, 'calibration_due_at' => null]]);
        $r = $this->ask(['mode' => 'analysis', 'resource' => 'assets', 'from' => '2026-01-01', 'to' => '2026-01-31'])->assertOk()->assertSee('Jumlah unit nonfasilitas tercatat 3')->assertSee('1 catatan berkondisi rusak')->assertSee('bukan posisi historis');
        $this->assertSame(2, count($r->json('charts.0.items')));
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_guidance_has_real_links_and_does_not_create_journals_or_transactions(): void
    {
        $this->ask(['message' => 'bagaimana agar neraca muncul'])->assertOk()->assertJsonPath('kind', 'guide')->assertJsonFragment(['url' => route('reports.financial-position')])->assertHeader('Cache-Control', 'no-store, private');
        $this->ask(['message' => 'cara input pemasukan'])->assertOk()->assertJsonFragment(['url' => route('accounting.receipts.index')]);
        $this->ask(['message' => 'bantu saya menggunakan halaman ini', 'context' => 'assets.edit'])->assertOk()->assertJsonFragment(['url' => route('assets.index')]);
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, DB::table('receipts')->count());
    }

    public function test_search_filters_active_records_date_and_returns_only_five_minimal_results(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->receipt(['transaction_no' => 'FIND-'.$i]);
        }
        $this->receipt(['transaction_no' => 'FIND-CANCELLED', 'status' => 'CANCELLED']);
        $this->receipt(['transaction_no' => 'FIND-FUTURE', 'transaction_date' => '2026-02-01']);
        $r = $this->ask(['mode' => 'search', 'resource' => 'receipts', 'term' => 'FIND', 'from' => '2026-01-01', 'to' => '2026-01-31'])->assertOk()->assertJsonPath('total', 7)->assertJsonCount(5, 'results');
        $this->assertStringNotContainsString('notes', $r->getContent());
        $this->assertStringNotContainsString('FIND-CANCELLED', $r->getContent());
        $this->assertStringNotContainsString('FIND-FUTURE', $r->getContent());
        $this->assertSame(9, DB::table('receipts')->count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_insurance_scope_cannot_be_bypassed_by_query_or_context(): void
    {
        DB::table('guarantors')->insert([['id' => 1, 'code' => 'UMUM', 'name' => 'Pasien Umum', 'category' => 'GENERAL'], ['id' => 2, 'code' => 'BPJS', 'name' => 'BPJS', 'category' => 'BPJS']]);
        $this->receipt(['transaction_no' => 'PUBLIC-RECEIPT', 'guarantor_id' => 1]);
        $this->receipt(['transaction_no' => 'INSURANCE-RECEIPT', 'guarantor_id' => 2]);
        $this->actingAs($this->user('ASURANSI'));
        $this->ask(['mode' => 'search', 'resource' => 'receipts', 'context' => 'accounting.receipts.index'])->assertOk()->assertJsonPath('total', 1)->assertJsonPath('results.0.title', 'INSURANCE-RECEIPT');
        foreach (['expenses', 'assets', 'accounts', 'balance'] as $resource) {
            $this->ask(['mode' => 'search', 'resource' => $resource])->assertForbidden();
        }
    }

    public function test_role_boundaries_for_financial_and_inventory_data_and_inactive_users(): void
    {
        $this->actingAs($this->user('PETUGAS_ASET'));
        $this->ask(['mode' => 'search', 'resource' => 'assets'])->assertOk();
        $this->ask(['mode' => 'search', 'resource' => 'receipts'])->assertForbidden();
        $this->ask(['message' => 'total neraca'])->assertForbidden();
        $this->actingAs($this->user('FINANCE'));
        $this->ask(['mode' => 'search', 'resource' => 'claims'])->assertForbidden();
        $this->ask(['mode' => 'summary', 'resource' => 'balance'])->assertOk();
        $this->actingAs($this->user('IT'));
        $this->ask(['mode' => 'search', 'resource' => 'receipts'])->assertForbidden();
        $this->ask()->assertOk();
        $this->accountant->update(['is_active' => false]);
        $this->actingAs($this->accountant);
        $this->ask()->assertForbidden();
        auth()->logout();
        $this->ask()->assertUnauthorized();
    }

    public function test_natural_search_dates_and_literal_wildcards_do_not_execute_arbitrary_commands(): void
    {
        $this->receipt(['transaction_no' => 'RCV-TEST', 'notes' => 'Literal 100% pembayaran']);
        $this->receipt(['notes' => 'Bukan literal']);
        $this->ask(['message' => 'cari data pemasukan RCV-TEST 2026-01-31'])->assertOk()->assertJsonPath('total', 1);
        $this->ask(['message' => 'tolong cari pemasukan RCV-TEST januari 2026'])->assertOk()->assertJsonPath('total', 1);
        $this->ask(['message' => 'berapa total pemasukan januari 2026'])->assertOk()->assertSee('2.469,00');
        $this->ask(['mode' => 'search', 'resource' => 'receipts', 'term' => '100%'])->assertOk()->assertJsonPath('total', 1);
        $this->ask(['mode' => 'search', 'resource' => 'receipts', 'term' => "' OR 1=1 --"])->assertOk()->assertJsonPath('total', 0);
        $this->ask(['message' => 'drop table receipts'])->assertOk()->assertJsonPath('kind', 'guide');
        $this->ask(['message' => 'cari pemasukan 2026-02-31'])->assertUnprocessable();
        $this->ask(['mode' => 'search', 'resource' => 'assets', 'from' => '2026-01-01'])->assertUnprocessable();
        $this->ask(['message' => str_repeat('a', 401)])->assertUnprocessable();
        $this->assertSame(2, DB::table('receipts')->count());
    }

    public function test_summary_uses_posted_ledger_and_preserves_exact_money_totals(): void
    {
        $this->receipt(['amount' => '10.10']);
        $this->receipt(['amount' => '20.20']);
        $this->ask(['mode' => 'summary', 'resource' => 'receipts'])->assertOk()->assertSee('30,30');
        $this->ask(['message' => 'ringkasan neraca'])->assertOk()->assertJsonPath('kind', 'summary')->assertSee('Belum ada jurnal');
        $cash = ChartAccount::create(['code' => '1-01', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => true]);
        $equity = ChartAccount::create(['code' => '3-01', 'name' => 'Modal', 'type' => 'EQUITY', 'normal_balance' => 'CREDIT', 'is_active' => true]);
        $j = JournalEntry::create(['journal_no' => 'TEST-1', 'journal_date' => '2026-01-31', 'description' => 'Dokumen contoh', 'status' => 'POSTED', 'created_by' => $this->accountant->id]);
        $j->lines()->createMany([['chart_account_id' => $cash->id, 'debit' => '100.25', 'credit' => 0], ['chart_account_id' => $equity->id, 'debit' => 0, 'credit' => '100.25']]);
        $this->ask(['mode' => 'summary', 'resource' => 'balance', 'as_of' => '2026-01-31'])->assertOk()->assertSee('100,25')->assertSee('Saldo awal belum terposting');
        $this->ask(['mode' => 'summary', 'resource' => 'balance', 'as_of' => '2026-01-30'])->assertOk()->assertSee('Belum ada jurnal');
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_validation_and_pdf_authentication(): void
    {
        $this->ask(['mode' => 'search', 'resource' => 'receipts', 'to' => '2026-01-31'])->assertOk();
        $this->ask(['mode' => 'search', 'resource' => 'receipts', 'from' => '2026-02-01', 'to' => '2026-01-31'])->assertUnprocessable();
        $this->ask(['mode' => 'search', 'resource' => 'users'])->assertUnprocessable();
        $this->ask(['mode' => 'summary', 'resource' => 'balance', 'as_of' => '2099-01-01'])->assertUnprocessable();
        auth()->logout();
        $this->get(route('assistant.guide'))->assertRedirect(route('login'));
        $this->accountant->update(['is_active' => false]);
        $this->actingAs($this->accountant);
        $this->get(route('assistant.guide'))->assertForbidden();
    }

    public function test_request_limit_is_per_authenticated_user(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->ask()->assertOk();
        }
        $this->ask()->assertTooManyRequests();
        $this->actingAs($this->user('OWNER'));
        $this->ask()->assertOk();
    }

    public function test_asset_identifier_and_claim_period_search_are_local_and_current(): void
    {
        DB::table('assets')->insert([['id' => 768, 'name' => 'Infusion Pump', 'code' => 'PUMP', 'serial_number' => 'SN01', 'location' => 'ICU', 'condition' => 'GOOD', 'deleted_at' => null], ['id' => 769, 'name' => 'Dihapus', 'code' => null, 'serial_number' => null, 'location' => null, 'condition' => 'UNKNOWN', 'deleted_at' => now()]]);
        $this->ask(['message' => 'cari inventaris AST-768'])->assertOk()->assertJsonPath('total', 1)->assertJsonFragment(['url' => route('assets.show', 768)]);
        $this->ask(['message' => 'cari inventaris Dihapus'])->assertOk()->assertJsonPath('total', 0);
        DB::table('guarantors')->insert(['id' => 2, 'code' => 'BPJS', 'name' => 'BPJS', 'category' => 'BPJS']);
        DB::table('insurance_monthly_claims')->insert(['guarantor_id' => 2, 'year' => 2026, 'month' => 1, 'service_type' => 'RAWAT_JALAN', 'status' => 'DIAJUKAN', 'claimed_amount' => '50000.00']);
        $this->ask(['message' => 'cari klaim asuransi BPJS'])->assertOk()->assertJsonPath('total', 1)->assertSee('kondisi terkini');
        $this->assertSame(0, JournalEntry::count());
    }
}
