<?php

namespace Tests\Feature;

use App\Models\{ChartAccount, Expense, ReferralRecipient};
use App\Services\{ReferralRecipientService, ReferralSummary, SmartExcelImport};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};

class ReferralRecipientTest extends ExpenseAccessTest
{
    private function payload(ReferralRecipient $recipient, array $overrides = []): array
    {
        $account = ChartAccount::firstOrCreate(['code'=>'REF-TEST'], ['name'=>'Beban Referral', 'type'=>'EXPENSE', 'normal_balance'=>'DEBIT', 'is_referral'=>true]);
        return array_replace([
            'expense_date'=>'2026-10-26', 'referral_recipient_id'=>$recipient->id,
            'referral_patient_name'=>'Pasien Rahasia', 'referral_medical_record_no'=>'RM-RAHASIA-001',
            'referral_procedure'=>'SC', 'chart_account_id'=>$account->id,
            'amount'=>100000, 'payment_method'=>'CASH', 'cash_bank_account_id'=>DB::table('cash_bank_accounts')->value('id'),
        ], $overrides);
    }

    private function postReferral(ReferralRecipient $recipient, array $overrides = []): Expense
    {
        $this->post(route('accounting.referrals.store'), $this->payload($recipient, $overrides))->assertSessionHasNoErrors();
        return Expense::latest('id')->firstOrFail();
    }

    public function test_master_crud_search_and_normalized_unique_names(): void
    {
        $this->post(route('accounting.referral-recipients.store'), ['name'=>'Budi Santoso', 'phone'=>'081234'])->assertSessionHasNoErrors();
        $recipient = ReferralRecipient::firstOrFail();
        $this->assertStringStartsWith('RR-', $recipient->code);
        $code = $recipient->code;
        $this->get(route('accounting.referral-recipients.index', ['q'=>'Budi']))->assertOk()->assertSee($code);
        $this->get(route('accounting.referral-recipients.index', ['q'=>'Tidak Ada']))->assertOk()->assertDontSee($code);
        $this->put(route('accounting.referral-recipients.update', $recipient), ['name'=>'Budi Santosa', 'confirm_distinct'=>1])->assertSessionHasNoErrors();
        $this->assertSame($code, $recipient->fresh()->code);
        $this->postJson(route('accounting.referral-recipients.store'), ['name'=>' BUDI  SANTOSA ', 'confirm_distinct'=>1])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->delete(route('accounting.referral-recipients.destroy', $recipient))->assertRedirect();
        $this->assertDatabaseMissing('referral_recipients', ['id'=>$recipient->id]);
    }

    public function test_similar_names_require_explicit_choice_and_modal_returns_identity(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Budi Santoso']);
        $this->getJson(route('accounting.referral-recipients.similar', ['name'=>'Budi Santosa']))->assertOk()->assertJsonPath('recipients.0.id', $recipient->id);
        $this->postJson(route('accounting.referral-recipients.store'), ['name'=>'Budi Santosa'])->assertStatus(409)->assertJsonPath('recipients.0.id', $recipient->id);
        $this->postJson(route('accounting.referral-recipients.store'), ['name'=>'Budi Santosa', 'confirm_distinct'=>1])->assertCreated()->assertJsonStructure(['id','code','name','is_active']);
        $this->get(route('accounting.referrals.create'))->assertOk()->assertSee('recipient-dialog')->assertSee('referral-recipient.js')->assertSee('referral_recipient_id');
    }

    public function test_used_recipient_is_deactivated_and_rename_preserves_expense_and_journal(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Penerima Awal']);
        $expense = $this->postReferral($recipient);
        $before = $expense->getAttributes();
        $journal = $expense->journal()->firstOrFail()->getAttributes();
        $this->put(route('accounting.referral-recipients.update', $recipient), ['name'=>'Nama Pengganti'])->assertSessionHasNoErrors();
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame('Nama Pengganti', $expense->fresh()->referral_recipient_name);
        $this->assertSame($journal, $expense->journal()->firstOrFail()->getAttributes());
        $this->delete(route('accounting.referral-recipients.destroy', $recipient))->assertRedirect();
        $this->assertFalse($recipient->fresh()->is_active);
        $this->post(route('accounting.referrals.store'), $this->payload($recipient))->assertSessionHasErrors('referral_recipient_id');
        $this->put(route('accounting.referrals.update', $expense), $this->payload($recipient, ['amount'=>125000]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['id'=>$expense->id, 'referral_recipient_id'=>$recipient->id]);
    }

    public function test_recap_counts_unique_rm_and_records_in_selected_period(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Penerima Rekap']);
        $this->postReferral($recipient);
        $this->postReferral($recipient, ['referral_procedure'=>'RI', 'amount'=>200000]);
        $this->postReferral($recipient, ['referral_procedure'=>'RI', 'referral_medical_record_no'=>'RM-002', 'amount'=>300000]);
        $this->postReferral($recipient, ['expense_date'=>'2026-09-01', 'amount'=>999999]);
        $cancelled = $this->postReferral($recipient, ['amount'=>777777]);
        $cancelled->update(['status'=>'CANCELLED']);
        $page = $this->get(route('accounting.referral-recipients.show', [$recipient, 'month'=>10,'year'=>2026]))->assertOk();
        $stats = $page->viewData('stats');
        $this->assertSame(3, $stats['transactions']);
        $this->assertSame(2, $stats['patients']);
        $this->assertSame(1, $stats['sc']);
        $this->assertSame(2, $stats['ri']);
        $this->assertEquals(100000, $stats['sc_amount']);
        $this->assertEquals(500000, $stats['ri_amount']);
        $this->assertEquals(600000, $stats['total']);
    }

    public function test_master_excel_reuses_names_and_rolls_back_ambiguous_import(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Budi Santoso', 'phone'=>'tetap']);
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([['Nama','Nomor Kontak','Keterangan'], [' BUDI SANTOSO ','berubah',''], ['Siti Aminah','08123',''], ['SITI AMINAH','08123','']]);
        $path = tempnam(sys_get_temp_dir(), 'referral-import');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $this->post(route('accounting.referral-recipients.import'), ['file'=>new UploadedFile($path, 'penerima.xlsx', null, null, true)])->assertSessionHasNoErrors()->assertRedirect();
        } finally { if (is_file($path)) unlink($path); $book->disconnectWorksheets(); }
        $this->assertSame(2, ReferralRecipient::count());
        $this->assertSame('tetap', $recipient->fresh()->phone);
        try {
            app(ReferralRecipientService::class)->import([['Nama'], ['Nama Baru Unik'], ['Budi Santosa']]);
            $this->fail('Similar name should reject the entire import.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('referral_recipients', ['name'=>'Nama Baru Unik']);
        }
    }

    public function test_transaction_excel_uses_existing_recipient_id_and_deduplicates(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Penerima Import']);
        $payload = $this->payload($recipient);
        $headers = array_keys($payload);
        $row = array_values(array_replace($payload, ['referral_recipient_id'=>' PENERIMA IMPORT ']));
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([$headers, $row, $row]);
        $path = tempnam(sys_get_temp_dir(), 'referral-transactions');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $preview = $this->post(route('accounting.referrals.import'), ['action'=>'upload', 'file'=>new UploadedFile($path, 'transaksi.xlsx', null, null, true)])->assertOk();
            $rows = $preview->viewData('preview');
            $this->assertSame('Valid', reset($rows)['status']);
            $this->assertSame('Duplicate', end($rows)['status']);
            $this->post(route('accounting.referrals.import'), ['action'=>'confirm', 'token'=>$preview->viewData('token'), 'mapping'=>$preview->viewData('mapping')])->assertOk()->assertViewHas('result', ['success'=>1,'failed'=>0,'skipped'=>1]);
        } finally { if (is_file($path)) unlink($path); $book->disconnectWorksheets(); }
        $this->assertSame(1, ReferralRecipient::count());
        $this->assertSame(1, Expense::where('referral_recipient_id', $recipient->id)->count());
        $request = Request::create('/accounting/referrals/import');
        $request->setUserResolver(fn () => auth()->user());
        $mapping = array_combine(range(0, count($headers)-1), $headers);
        $rows = app(SmartExcelImport::class)->preview([array_values(array_replace($payload, ['referral_recipient_id'=>'Nama Tidak Dikenal']))], $mapping, 'referrals', $request);
        $this->assertSame('Error', $rows[0]['status']);
        $this->assertSame(1, ReferralRecipient::count());
    }

    public function test_dashboard_reports_and_pdf_include_referral_once_without_rm_in_summary(): void
    {
        (require database_path('migrations/2026_08_18_000008_create_financial_groups_table.php'))->up();
        (require database_path('migrations/2026_10_24_120000_create_insurance_monthly_claims.php'))->up();
        $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'), 'code'=>'ACCOUNTING', 'name'=>'Accounting']);
        auth()->user()->roles()->sync([$role]);
        auth()->user()->unsetRelation('roles');
        $recipient = ReferralRecipient::create(['name'=>'Rekap Keuangan']);
        $expense = $this->postReferral($recipient, ['amount'=>500000]);
        $before = $expense->getAttributes();
        // Cover legacy/unmapped reporting categories without rewriting old expenses.
        $expense->category->update(['financial_group_id'=>null]);
        $this->get(route('dashboard', ['tahun'=>2026]))->assertOk()->assertSee('Rekap Pengeluaran Referral per Penerima')
            ->assertSee('Rekap Keuangan')->assertDontSee('RM-RAHASIA-001')->assertViewHas('stats', fn ($s) => (float) $s['expense'] === 500000.0);
        foreach (['monthly'=>['month'=>10,'year'=>2026], 'yearly'=>['year'=>2026]] as $report=>$period) {
            $page = $this->get(route('reports.'.$report, $period))->assertOk()->assertSee('Export PDF')->assertSee('report-pdf.js')->assertSee('Rekap Keuangan')->assertDontSee('RM-RAHASIA-001')->assertViewHas('totalExpense', 500000);
            $this->assertEquals(500000, $page->viewData('referralSummary')->sum('total'));
            $this->assertSame(1, $page->viewData('referralSummary')->sum('transactions'));
            $html = $page->getContent();
            $this->assertGreaterThan(strpos($html, 'id="report-pdf-content"'), strpos($html, 'class="card referral-summary"'));
        }
        $this->get(route('reports.monthly.detail', ['month'=>10,'year'=>2026,'kind'=>'expense','group'=>'REFERRAL_UNMAPPED']))->assertOk()->assertViewHas('total', 500000);
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame(1, Expense::count());
    }

    public function test_legacy_master_mapping_survives_rename_without_rewriting_transaction(): void
    {
        $expense = $this->postReferral(ReferralRecipient::create(['name'=>'Sementara']));
        DB::table('expenses')->where('id', $expense->id)->update(['referral_recipient_id'=>null, 'referral_recipient'=>'Nama Historis']);
        $migration = require database_path('migrations/2026_10_26_000002_create_referral_recipients.php');
        $migration->down();
        $before = DB::table('expenses')->find($expense->id);
        $migration->up();
        $recipient = ReferralRecipient::where('name', 'Nama Historis')->firstOrFail();
        $recipient->update(['name'=>'Nama Baru']);
        $stats = app(ReferralSummary::class)->rows('2026-10-01', '2026-10-31')->first();
        $this->assertSame('Nama Baru', $stats['name']);
        $this->assertSame(1, app(ReferralSummary::class)->forRecipient($recipient)->count());
        $this->assertSame($before->referral_recipient, DB::table('expenses')->find($expense->id)->referral_recipient);
        $this->assertNull(DB::table('expenses')->find($expense->id)->referral_recipient_id);
        $this->delete(route('accounting.referral-recipients.destroy', $recipient))->assertRedirect();
        $this->assertFalse($recipient->fresh()->is_active);
    }

    public function test_master_and_patient_history_are_protected_from_unauthorized_roles(): void
    {
        $recipient = ReferralRecipient::create(['name'=>'Penerima Privat']);
        $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'), 'code'=>'OWNER', 'name'=>'Owner']);
        auth()->user()->roles()->sync([$role]);
        auth()->user()->unsetRelation('roles');
        foreach (['index','create','show','edit','similar','template'] as $action) {
            $this->get(route('accounting.referral-recipients.'.$action, in_array($action, ['show','edit']) ? [$recipient] : []))->assertForbidden();
        }
        $this->post(route('accounting.referral-recipients.store'), ['name'=>'Tidak Boleh'])->assertForbidden();
        $this->put(route('accounting.referral-recipients.update', $recipient), ['name'=>'Tidak Boleh'])->assertForbidden();
        $this->delete(route('accounting.referral-recipients.destroy', $recipient))->assertForbidden();
        $this->post(route('accounting.referral-recipients.import'), [])->assertForbidden();
        $this->get(route('accounting.referrals.import'))->assertForbidden();
    }
}
