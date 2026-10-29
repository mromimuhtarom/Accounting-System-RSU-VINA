<?php

namespace Tests\Feature;

use App\Models\{ChartAccount, Expense, Receipt, ReferralRecipient, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};

class BatchTransactionTest extends ExpenseAccessTest
{
    private function expenseRow(array $overrides = []): array
    {
        return array_replace(['expense_date'=>'2026-10-28', 'expense_category_id'=>DB::table('expense_categories')->value('id'),
            'description'=>'Pengeluaran batch', 'amount'=>150000, 'payment_method'=>'CASH',
            'cash_bank_account_id'=>DB::table('cash_bank_accounts')->value('id')], $overrides);
    }

    private function receiptRow(array $overrides = []): array
    {
        $account = ChartAccount::firstOrCreate(['code'=>'REV-BATCH'], ['name'=>'Pendapatan Batch', 'type'=>'REVENUE', 'normal_balance'=>'CREDIT']);
        return array_replace(['transaction_date'=>'2026-10-28', 'chart_account_id'=>$account->id, 'notes'=>'Pemasukan batch', 'amount'=>250000], $overrides);
    }

    public function test_cashier_can_create_twenty_distinct_expenses_and_receipts_in_one_submission(): void
    {
        $this->get(route('cashier.expenses.create', ['multiple'=>1]))->assertOk()->assertSee('data-batch-form', false)->assertSee('rows[0][attachment]', false);
        $this->post(route('cashier.expenses.store'), ['rows'=>array_fill(0,20,$this->expenseRow())])->assertRedirect(route('cashier.expenses.index'))->assertSessionHasNoErrors();
        $this->assertSame(20, Expense::count());
        $this->assertSame(20, Expense::distinct()->count('expense_no'));
        $this->assertEquals(3000000, Expense::sum('amount'));
        foreach (Expense::with('journal.lines')->get() as $expense) {
            $this->assertSame(auth()->id(), $expense->created_by);
            $this->assertEquals(150000, $expense->journal->lines->sum('debit'));
            $this->assertEquals(150000, $expense->journal->lines->sum('credit'));
        }
        $this->get(route('cashier.receipts.create', ['multiple'=>1]))->assertOk()->assertSee('rows[0][notes]', false);
        $this->post(route('cashier.receipts.store'), ['rows'=>array_fill(0,20,$this->receiptRow())])->assertRedirect(route('cashier.receipts.index'))->assertSessionHasNoErrors();
        $this->assertSame(20, Receipt::count());
        $this->assertSame(20, Receipt::distinct()->count('transaction_no'));
        $this->assertEquals(5000000, Receipt::sum('amount'));
    }

    public function test_invalid_row_preserves_all_input_and_saves_no_transactions_or_files(): void
    {
        Storage::fake('public');
        $rows = [0=>$this->expenseRow(['attachment'=>UploadedFile::fake()->create('nota.pdf',10,'application/pdf')]),
            3=>$this->expenseRow(['amount'=>-1,'description'=>'Pertahankan keterangan ini'])];
        $this->from(route('cashier.expenses.create',['multiple'=>1]))->post(route('cashier.expenses.store'), ['rows'=>$rows])
            ->assertRedirect(route('cashier.expenses.create',['multiple'=>1]))->assertSessionHasErrors('rows.3.amount')
            ->assertSessionHasInput('rows.3.description', 'Pertahankan keterangan ini');
        $this->assertSame(0, Expense::count());
        $this->assertSame([],Storage::disk('public')->allFiles());
        $this->get(route('cashier.expenses.create',['multiple'=>1]))->assertOk()->assertSee('rows[3][amount]',false)->assertSee('Pertahankan keterangan ini');
        $this->post(route('cashier.receipts.store'), ['rows'=>[$this->receiptRow(),$this->receiptRow(['chart_account_id'=>999999])]])->assertSessionHasErrors('rows.1.chart_account_id');
        $this->assertSame(0,Receipt::count());
    }

    public function test_batch_limits_and_nested_batches_are_rejected(): void
    {
        $this->post(route('cashier.expenses.store'), ['rows'=>[]])->assertSessionHasErrors('rows');
        $this->post(route('cashier.expenses.store'), ['rows'=>array_fill(0,21,$this->expenseRow())])->assertSessionHasErrors('rows');
        $this->post(route('cashier.expenses.store'), ['rows'=>[$this->expenseRow(['rows'=>[$this->expenseRow()]])]])->assertSessionHasErrors('rows.0.rows');
        $this->assertSame(0,Expense::count());
    }

    public function test_runtime_failure_rolls_back_journals_audits_and_uploaded_files(): void
    {
        Storage::fake('public');
        $listener = function ($expense) { if ($expense->description === 'Gagalkan baris kedua') throw new \RuntimeException('Batch failure'); };
        Event::listen('eloquent.creating: '.Expense::class, $listener);
        try {
            $this->withoutExceptionHandling();
            $this->post(route('cashier.expenses.store'), ['rows'=>[
                $this->expenseRow(['attachment'=>UploadedFile::fake()->create('nota.pdf',10,'application/pdf')]),
                $this->expenseRow(['description'=>'Gagalkan baris kedua']),
            ]]);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Batch failure',$e->getMessage());
            $this->assertSame(0,Expense::count());
            $this->assertDatabaseCount('journal_entries',0);
            $this->assertDatabaseCount('activity_logs',0);
            $this->assertSame([],Storage::disk('public')->allFiles());
        } finally { Event::forget('eloquent.creating: '.Expense::class); }
    }

    public function test_referral_batch_keeps_automatic_coa_recipient_relationships_and_separate_evidence(): void
    {
        Storage::fake('local');
        $recipient = ReferralRecipient::create(['name'=>'Penerima Batch']);
        $row = ['expense_date'=>'2026-10-28','referral_recipient_id'=>$recipient->id,'referral_patient_name'=>'Pasien Batch',
            'referral_medical_record_no'=>'RM-001','referral_procedure'=>'SC','amount'=>100000,'payment_method'=>'CASH',
            'cash_bank_account_id'=>DB::table('cash_bank_accounts')->value('id')];
        $this->get(route('accounting.referrals.create',['multiple'=>1]))->assertOk()->assertSee('rows[0][referral_recipient_id]',false)->assertDontSee('name="rows[0][chart_account_id]"',false);
        $this->post(route('accounting.referrals.store'), ['rows'=>[
            $row + ['attachment'=>UploadedFile::fake()->create('kwitansi.pdf',10,'application/pdf')],
            array_replace($row,['referral_medical_record_no'=>'RM-002','referral_procedure'=>'RI','amount'=>200000]),
        ]])->assertSessionHasNoErrors()->assertRedirect(route('accounting.referrals.index'));
        $expenses = Expense::orderBy('id')->get();
        $this->assertCount(2,$expenses);
        $this->assertSame($recipient->id,$expenses[0]->referral_recipient_id);
        $this->assertSame($expenses[0]->chart_account_id,$expenses[1]->chart_account_id);
        Storage::disk('local')->assertExists($expenses[0]->attachment_path);
        $this->assertNull($expenses[1]->attachment_path);
        $this->assertNull($expenses[0]->receipt_id);
        $this->assertEquals(300000,$expenses->sum('amount'));
    }

    public function test_accounting_and_insurance_scopes_still_apply_to_every_batch_row(): void
    {
        $application = DB::table('applications')->value('id');
        foreach (['ACCOUNTING','KEPALA_KEUANGAN','ADMIN_ACCOUNTING'] as $code) {
            $user=User::factory()->create();
            $role=DB::table('roles')->insertGetId(['application_id'=>$application,'code'=>$code,'name'=>$code]);
            $user->roles()->attach($role); $this->actingAs($user);
            $this->get(route('accounting.receipts.create',['multiple'=>1]))->assertOk();
            $this->post(route('accounting.receipts.store'),['rows'=>[$this->receiptRow(),$this->receiptRow()]])->assertSessionHasNoErrors();
            $this->post(route('accounting.pengeluaran.store'),['rows'=>[$this->expenseRow(),$this->expenseRow()]])->assertSessionHasNoErrors();
        }
        $user=User::factory()->create();
        $role=DB::table('roles')->insertGetId(['application_id'=>$application,'code'=>'ASURANSI','name'=>'Asuransi']);
        $user->roles()->attach($role); $this->actingAs($user);
        $general=\App\Models\Guarantor::create(['code'=>'UMUM-BATCH','name'=>'Umum','category'=>'SELF_PAY']);
        $insurance=\App\Models\Guarantor::create(['code'=>'ASR-BATCH','name'=>'Asuransi','category'=>'PRIVATE_INSURANCE']);
        $this->get(route('insurance.receipts.create',['multiple'=>1]))->assertOk();
        $this->post(route('insurance.receipts.store'),['rows'=>[$this->receiptRow(['guarantor_id'=>$insurance->id]),$this->receiptRow(['guarantor_id'=>$general->id])]])->assertSessionHasErrors('rows.1.guarantor_id');
        $this->assertSame(0,Receipt::where('cashier_id',$user->id)->count());
        $this->post(route('insurance.receipts.store'),['rows'=>[$this->receiptRow(['guarantor_id'=>$insurance->id]),$this->receiptRow(['guarantor_id'=>$insurance->id])]])->assertSessionHasNoErrors();
        $this->assertSame(2,Receipt::where('cashier_id',$user->id)->count());
        $this->post(route('accounting.referrals.store'),['rows'=>[]])->assertForbidden();
        $this->post(route('cashier.expenses.store'),['rows'=>[]])->assertForbidden();
    }
}
