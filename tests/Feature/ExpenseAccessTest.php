<?php

namespace Tests\Feature;

use App\Models\{CashBankAccount, ChartAccount, Expense, ExpenseCategory, Receipt, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class ExpenseAccessTest extends TestCase
{
    private User $cashier;
    private User $owner;
    private ExpenseCategory $category;
    private CashBankAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the real base schema in isolated SQLite, excluding MySQL-only legacy backfills.
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_000001_add_accounting_fields_to_users_table.php',
            '2026_08_18_000002_create_applications_and_roles_tables.php',
            '2026_08_18_000003_create_chart_accounts_table.php',
            '2026_08_18_000004_create_master_accounting_tables.php',
            '2026_08_18_000005_create_receipts_table.php',
            '2026_08_18_000006_create_expenses_table.php',
            '2026_08_18_000007_create_journal_tables.php',
            '2026_08_19_000009_create_patients_and_link_receipts.php',
            '2026_08_19_000010_add_referral_fields_to_expenses.php',
            '2026_10_25_000001_create_activity_logs_table.php',
            '2026_10_26_000001_add_referral_group_to_chart_accounts.php',
            '2026_10_26_000002_create_referral_recipients.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts');
            $table->string('income_code')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('hd')->nullable();
            $table->string('rs')->nullable();
            // Match the nullable legacy fields in the deployed cashbook schema.
            $table->string('patient_name',150)->nullable()->change();
            $table->unsignedBigInteger('service_unit_id')->nullable()->change();
            $table->unsignedBigInteger('guarantor_id')->nullable()->change();
            $table->string('payment_method')->nullable()->change();
        });
        Schema::create('bpjs_claim_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts');
        });
        $this->cashier = User::factory()->create();
        $this->owner = User::factory()->create();
        $application = DB::table('applications')->insertGetId(['code' => 'ACCOUNTING', 'name' => 'Accounting']);
        $role = DB::table('roles')->insertGetId(['application_id' => $application, 'code' => 'KASIR', 'name' => 'Kasir']);
        $this->cashier->roles()->attach($role);
        $account = ChartAccount::create(['code' => '5001', 'name' => 'Beban', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT']);
        $cashAccount = ChartAccount::create(['code' => '1001', 'name' => 'Kas', 'type' => 'ASSET', 'normal_balance' => 'DEBIT']);
        $this->category = ExpenseCategory::create(['code' => 'UMUM', 'name' => 'Umum', 'expense_account_id' => $account->id]);
        $this->cash = CashBankAccount::create(['code' => 'KAS', 'name' => 'Kas utama', 'type' => 'CASH', 'chart_account_id' => $cashAccount->id]);
        $this->actingAs($this->cashier);
    }

    private function expense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'expense_no' => 'EXP-'.uniqid(), 'expense_date' => '2026-10-25',
            'expense_type' => 'GENERAL', 'expense_category_id' => $this->category->id,
            'chart_account_id' => $this->category->expense_account_id, 'description' => 'Belanja perlengkapan',
            'amount' => 150000, 'payment_method' => 'CASH', 'cash_bank_account_id' => $this->cash->id,
            'created_by' => $this->owner->id, 'status' => 'ACTIVE',
        ], $overrides));
    }

    public function test_accounting_dashboard_uses_one_receipt_summary_query(): void
    {
        (require database_path('migrations/2026_10_24_120000_create_insurance_monthly_claims.php'))->up();
        $application = DB::table('applications')->where('code', 'ACCOUNTING')->value('id');
        $role = DB::table('roles')->insertGetId(['application_id'=>$application, 'code'=>'ACCOUNTING', 'name'=>'Accounting']);
        $this->owner->roles()->attach($role);
        $this->actingAs($this->owner);
        $this->expense();
        DB::enableQueryLog();
        $this->get(route('dashboard', ['tahun'=>2026]))->assertOk()
            ->assertViewHas('stats', fn ($stats) => (float)$stats['income'] === 0.0
                && (int)$stats['total_record_count'] === 0 && (float)$stats['expense'] === 150000.0);
        $summaries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), ' as income'));
        $this->assertCount(1, $summaries);
        DB::disableQueryLog();
    }

    public function test_cashier_sees_general_expenses_separately_from_referrals(): void
    {
        $expense = $this->expense();
        $referral = $this->expense(['expense_type' => 'REFERRAL']);
        $this->get(route('cashier.expenses.index'))->assertOk()
            ->assertSee($expense->expense_no)->assertDontSee($referral->expense_no)
            ->assertDontSee(route('cashier.expenses.edit', $expense))
            ->assertDontSee(route('cashier.expenses.destroy', $expense));
        $this->get(route('cashier.expenses.index', ['q' => 'tidak-ada']))->assertOk()->assertDontSee($expense->expense_no);
    }

    public function test_cashier_can_edit_posted_expense_and_journal_stays_balanced(): void
    {
        $expense = $this->expense(['created_by' => $this->cashier->id]);
        $journal = $expense->journal()->firstOrFail();
        $this->get(route('cashier.expenses.edit', $expense))->assertOk()
            ->assertSee('public/js/searchable-select.js')->assertSee('public/css/searchable-select.css');
        $newAccount = ChartAccount::create(['code' => '5002', 'name' => 'Beban baru', 'type' => 'EXPENSE', 'normal_balance' => 'DEBIT']);
        $newCategory = ExpenseCategory::create(['code' => 'BARU', 'name' => 'Baru', 'expense_account_id' => $newAccount->id]);
        $newCash = CashBankAccount::create(['code' => 'KAS2', 'name' => 'Kas kedua', 'type' => 'CASH', 'chart_account_id' => $this->cash->chart_account_id]);
        $this->put(route('cashier.expenses.update', $expense), [
            'expense_date' => '2026-10-24', 'expense_category_id' => $newCategory->id,
            'description' => 'Koreksi pengeluaran', 'amount' => 275000.50,
            'payment_method' => 'CASH', 'cash_bank_account_id' => $newCash->id,
        ])->assertRedirect(route('cashier.expenses.index'))->assertSessionHasNoErrors();
        $this->assertSame('275000.50', $expense->fresh()->amount);
        $this->assertSame($journal->id, $expense->journal()->firstOrFail()->id);
        $this->assertSame('2026-10-24', $journal->fresh()->journal_date->format('Y-m-d'));
        $this->assertSame('Koreksi pengeluaran', $journal->fresh()->description);
        $this->assertEquals(275000.50, $journal->lines()->sum('debit'));
        $this->assertEquals(275000.50, $journal->lines()->sum('credit'));
        $this->assertSame(2, $journal->lines()->count());
        $this->assertSame($newAccount->id, $journal->lines()->where('debit', '>', 0)->firstOrFail()->chart_account_id);
    }

    public function test_cashier_can_delete_posted_expense_without_orphan_journal(): void
    {
        $expense = $this->expense(['created_by' => $this->cashier->id]);
        $journal = $expense->journal()->firstOrFail();
        $unrelated = $this->expense();
        $this->delete(route('cashier.expenses.destroy', $expense))->assertRedirect(route('cashier.expenses.index'));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $journal->id]);
        $this->assertDatabaseMissing('journal_entry_lines', ['journal_entry_id' => $journal->id]);
        $this->assertNotNull($unrelated->fresh()->journal);
    }

    public function test_invalid_edit_leaves_existing_expense_and_journal_unchanged(): void
    {
        $expense = $this->expense(['created_by' => $this->cashier->id]);
        $journal = $expense->journal()->firstOrFail();
        $this->put(route('cashier.expenses.update', $expense), [
            'expense_date' => '2026-10-24', 'expense_category_id' => $this->category->id,
            'description' => 'Koreksi', 'amount' => -1, 'payment_method' => 'CASH',
            'cash_bank_account_id' => $this->cash->id,
        ])->assertSessionHasErrors('amount');
        $this->assertSame('150000.00', $expense->fresh()->amount);
        $this->assertEquals(150000, $journal->lines()->sum('debit'));
    }

    public function test_cashier_export_includes_other_users_expenses(): void
    {
        $expense = $this->expense();
        $response = $this->get(route('excel.expenses'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        try {
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringNotContainsString($expense->expense_no, $xml);
            $this->assertStringContainsString('Belanja perlengkapan', $xml);
            $this->assertStringContainsString('<c r="A5" s="4"><v>1</v></c>', $xml);
            $this->assertStringNotContainsString('No Transaksi', $xml);
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function test_journal_failure_rolls_back_expense_and_existing_lines(): void
    {
        $expense = $this->expense();
        $journal = $expense->journal()->firstOrFail();
        $event = 'eloquent.creating: '.\App\Models\JournalLine::class;
        \Illuminate\Support\Facades\Event::listen($event, function () {
            throw new \RuntimeException('Simulated journal write failure');
        });
        try {
            app(\App\Services\ExpenseTransactionService::class)->update($expense, ['amount' => 999999]);
            $this->fail('Expected journal failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated journal write failure', $e->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertSame('150000.00', $expense->fresh()->amount);
        $this->assertSame(2, $journal->lines()->count());
        $this->assertEquals(150000, $journal->lines()->sum('debit'));
        $this->assertEquals(150000, $journal->lines()->sum('credit'));
    }

    public function test_cashier_already_sees_receipts_from_other_users(): void
    {
        $unit = DB::table('service_units')->insertGetId(['code' => 'UNIT', 'name' => 'Unit']);
        $guarantor = DB::table('guarantors')->insertGetId(['code' => 'UMUM', 'name' => 'Umum', 'category' => 'SELF_PAY']);
        $receipt = Receipt::create([
            'transaction_no' => 'RCV-OTHER', 'transaction_date' => '2026-10-25',
            'patient_name' => 'Pasien', 'service_unit_id' => $unit, 'guarantor_id' => $guarantor,
            'amount' => 100000, 'payment_method' => 'CASH', 'cashier_id' => $this->owner->id, 'status' => 'ACTIVE',
        ]);
        $this->get(route('cashier.receipts.index'))->assertOk()->assertSee($receipt->transaction_no);
    }

    public function test_cashier_cannot_modify_referrals_through_general_expense_routes(): void
    {
        $expense = $this->expense(['expense_type' => 'REFERRAL']);
        $this->get(route('cashier.expenses.edit', $expense))->assertForbidden();
        $this->delete(route('cashier.expenses.destroy', $expense))->assertForbidden();
        $this->assertNotNull($expense->fresh());
    }

    private function receipt(int $creatorId): Receipt
    {
        $unit = DB::table('service_units')->insertGetId(['code' => uniqid(), 'name' => 'Unit']);
        $guarantor = DB::table('guarantors')->insertGetId(['code' => uniqid(), 'name' => 'Umum', 'category' => 'SELF_PAY']);
        return Receipt::create([
            'transaction_no' => 'RCV-'.uniqid(), 'transaction_date' => '2026-10-25',
            'patient_name' => 'Pasien', 'notes' => 'Pembayaran pasien',
            'service_unit_id' => $unit, 'guarantor_id' => $guarantor,
            'amount' => 100000, 'payment_method' => 'CASH', 'cashier_id' => $creatorId, 'status' => 'ACTIVE',
        ]);
    }

    public function test_cashier_cannot_edit_update_or_delete_other_users_transactions(): void
    {
        $expense = $this->expense();
        $receipt = $this->receipt($this->owner->id);
        foreach (['cashier.expenses' => $expense, 'cashier.receipts' => $receipt] as $prefix => $record) {
            $this->get(route($prefix.'.edit', $record))->assertForbidden();
            $this->put(route($prefix.'.update', $record), ['amount' => 1, 'created_by' => $this->cashier->id, 'cashier_id' => $this->cashier->id])->assertForbidden();
            $this->delete(route($prefix.'.destroy', $record))->assertForbidden();
            $this->assertNotNull($record->fresh());
        }
        $this->get(route('cashier.receipts.index'))->assertOk()->assertSee($receipt->transaction_no)
            ->assertDontSee(route('cashier.receipts.edit', $receipt))
            ->assertDontSee(route('cashier.receipts.destroy', $receipt));
    }

    public function test_cashier_can_manage_own_unposted_receipt(): void
    {
        $receipt = $this->receipt($this->cashier->id);
        $revenue = ChartAccount::create(['code' => '4001', 'name' => 'Pendapatan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT']);
        $this->get(route('cashier.receipts.edit', $receipt))->assertOk();
        $this->get(route('cashier.receipts.index'))->assertOk()->assertSee(route('cashier.receipts.edit', $receipt));
        $this->put(route('cashier.receipts.update', $receipt), [
            'transaction_date' => '2026-10-25', 'chart_account_id' => $revenue->id,
            'notes' => 'Koreksi penerimaan', 'amount' => 120000,
        ])->assertSessionHasNoErrors()->assertRedirect(route('cashier.receipts.index'));
        $this->assertSame('120000.00', $receipt->fresh()->amount);
        $this->assertSame($this->cashier->id, $receipt->fresh()->cashier_id);
        $this->delete(route('cashier.receipts.destroy', $receipt))->assertRedirect(route('cashier.receipts.index'));
        $this->assertDatabaseMissing('receipts', ['id' => $receipt->id]);
    }

    public function test_all_three_accounting_roles_can_manage_other_users_transactions(): void
    {
        $revenue = ChartAccount::create(['code' => '4001', 'name' => 'Pendapatan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT']);
        foreach (['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING'] as $code) {
            $user = User::factory()->create();
            $role = DB::table('roles')->insertGetId(['application_id' => DB::table('applications')->value('id'), 'code' => $code, 'name' => $code]);
            $user->roles()->attach($role);
            $this->actingAs($user);
            $expense = $this->expense();
            $receipt = $this->receipt($this->owner->id);
            $this->get(route('accounting.pengeluaran.index'))->assertOk()->assertSee(route('accounting.pengeluaran.edit', $expense));
            $this->get(route('accounting.pengeluaran.edit', $expense))->assertOk();
            $this->put(route('accounting.pengeluaran.update', $expense), [
                'expense_date' => '2026-10-25', 'expense_category_id' => $this->category->id,
                'description' => 'Koreksi', 'amount' => 200000, 'payment_method' => 'CASH', 'cash_bank_account_id' => $this->cash->id,
            ])->assertSessionHasNoErrors()->assertRedirect(route('accounting.pengeluaran.index'));
            $this->assertSame($this->owner->id, $expense->fresh()->created_by);
            $this->get(route('accounting.receipts.edit', $receipt))->assertOk();
            $this->put(route('accounting.receipts.update', $receipt), [
                'transaction_date' => '2026-10-25', 'chart_account_id' => $revenue->id,
                'notes' => 'Koreksi', 'amount' => 200000, 'status' => 'ACTIVE',
            ])->assertSessionHasNoErrors()->assertRedirect(route('accounting.receipts.index'));
            $this->assertSame($this->owner->id, $receipt->fresh()->cashier_id);
            $this->delete(route('accounting.pengeluaran.destroy', $expense))->assertRedirect(route('accounting.pengeluaran.index'));
            $this->delete(route('accounting.receipts.destroy', $receipt))->assertRedirect(route('accounting.receipts.index'));
            $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
            $this->assertDatabaseMissing('receipts', ['id' => $receipt->id]);
        }
    }

    public function test_posted_expense_audits_include_edits_deletions_and_related_journal(): void
    {
        $expense = $this->expense(['created_by'=>$this->cashier->id]);
        $journalId = $expense->journal()->firstOrFail()->id;
        $this->put(route('cashier.expenses.update',$expense), [
            'expense_date'=>'2026-10-25','expense_category_id'=>$this->category->id,
            'description'=>'Koreksi tercatat','amount'=>175000,'payment_method'=>'CASH','cash_bank_account_id'=>$this->cash->id,
        ])->assertSessionHasNoErrors();
        $edit = \App\Models\ActivityLog::where('subject_type',Expense::class)->where('subject_id',$expense->id)->where('action','updated')->firstOrFail();
        $this->assertEquals(150000,$edit->before_values['amount']);
        $this->assertEquals(175000,$edit->after_values['amount']);
        $this->assertSame($this->cashier->id,$edit->actor_id);
        $this->assertSame('cashier.expenses.update',$edit->route_name);
        $this->delete(route('cashier.expenses.destroy',$expense))->assertRedirect(route('cashier.expenses.index'));
        $deleted = \App\Models\ActivityLog::where('subject_type',Expense::class)->where('subject_id',$expense->id)->where('action','deleted')->firstOrFail();
        $this->assertEquals(175000,$deleted->before_values['amount']);
        $this->assertNull($deleted->after_values);
        $this->assertDatabaseHas('activity_logs',['subject_type'=>\App\Models\JournalEntry::class,'subject_id'=>(string)$journalId,'action'=>'deleted']);
        $this->assertGreaterThanOrEqual(2,\App\Models\ActivityLog::where('subject_type',\App\Models\JournalLine::class)->where('action','deleted')->count());
    }

    private function insuranceUser(): User
    {
        $user = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'),'code'=>'ASURANSI','name'=>'Asuransi']);
        $user->roles()->attach($role);
        $this->actingAs($user);
        return $user;
    }

    public function test_cashier_input_page_receives_guarantors_and_stores_selected_source(): void
    {
        $guarantor = \App\Models\Guarantor::create(['code'=>'TEST-ASR','name'=>'Asuransi Pilihan','category'=>'PRIVATE_INSURANCE']);
        $revenue = ChartAccount::create(['code'=>'4001','name'=>'Pendapatan','type'=>'REVENUE','normal_balance'=>'CREDIT']);
        $this->get(route('cashier.receipts.create'))->assertOk()->assertSee('Asuransi Pilihan');
        $this->post(route('cashier.receipts.store'),[
            'transaction_date'=>'2026-10-25','chart_account_id'=>$revenue->id,'notes'=>'Penerimaan uji',
            'amount'=>'150000.50','guarantor_id'=>$guarantor->id,'service_type'=>'RAWAT_JALAN',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cashier.receipts.index'));
        $this->assertDatabaseHas('receipts',['cashier_id'=>$this->cashier->id,'guarantor_id'=>$guarantor->id,'service_type'=>'RAWAT_JALAN']);
    }

    public function test_insurance_only_sees_insurance_receipts_and_cannot_modify_other_owners(): void
    {
        $general = $this->receipt($this->owner->id);
        $insurance = $this->receipt($this->owner->id);
        $insurance->guarantor->update(['category'=>'BPJS']);
        $this->insuranceUser();
        $page = $this->get(route('insurance.receipts.index'))->assertOk()->assertSee($insurance->transaction_no)->assertDontSee($general->transaction_no);
        $this->assertCount(1,$page->viewData('receipts'));
        $this->assertStringNotContainsString(route('insurance.receipts.edit',$insurance),$page->getContent());
        foreach ([$general,$insurance] as $record) {
            $this->get(route('insurance.receipts.edit',$record))->assertForbidden();
            $this->put(route('insurance.receipts.update',$record),['amount'=>1])->assertForbidden();
            $this->delete(route('insurance.receipts.destroy',$record))->assertForbidden();
        }
    }

    public function test_insurance_can_create_edit_delete_own_insurance_receipt(): void
    {
        $guarantor = \App\Models\Guarantor::create(['code'=>'TEST-BPJS','name'=>'BPJS Uji','category'=>'BPJS']);
        $revenue = ChartAccount::create(['code'=>'4001','name'=>'Pendapatan','type'=>'REVENUE','normal_balance'=>'CREDIT']);
        $user = $this->insuranceUser();
        $this->get(route('insurance.receipts.create'))->assertOk()->assertSee('BPJS Uji');
        $payload=['transaction_date'=>'2026-10-25','chart_account_id'=>$revenue->id,'notes'=>'Penerimaan asuransi',
            'amount'=>'150000.50','guarantor_id'=>$guarantor->id,'service_type'=>'RAWAT_JALAN'];
        $this->post(route('insurance.receipts.store'),$payload)->assertSessionHasNoErrors()->assertRedirect(route('insurance.receipts.index'));
        $receipt = Receipt::where('cashier_id',$user->id)->firstOrFail();
        $this->get(route('insurance.receipts.edit',$receipt))->assertOk();
        $this->put(route('insurance.receipts.update',$receipt),array_replace($payload,['amount'=>200000]))->assertSessionHasNoErrors()->assertRedirect(route('insurance.receipts.index'));
        $this->assertSame('200000.00',$receipt->fresh()->amount);
        $this->delete(route('insurance.receipts.destroy',$receipt))->assertRedirect(route('insurance.receipts.index'));
        $this->assertDatabaseMissing('receipts',['id'=>$receipt->id]);
    }

    public function test_insurance_cannot_create_general_receipt_or_switch_own_receipt_to_general(): void
    {
        $receipt = $this->receipt($this->owner->id);
        $receipt->guarantor->update(['category'=>'BPJS']);
        $general = \App\Models\Guarantor::create(['code'=>'GENERAL','name'=>'Umum Rahasia','category'=>'SELF_PAY']);
        $revenue = ChartAccount::create(['code'=>'4001','name'=>'Pendapatan','type'=>'REVENUE','normal_balance'=>'CREDIT']);
        $user = $this->insuranceUser();
        $receipt->update(['cashier_id'=>$user->id]);
        $payload=['transaction_date'=>'2026-10-25','chart_account_id'=>$revenue->id,'notes'=>'Uji pembatasan','amount'=>100000,'guarantor_id'=>$general->id];
        $this->get(route('insurance.receipts.create'))->assertOk()->assertDontSee('Umum Rahasia');
        $this->post(route('insurance.receipts.store'),$payload)->assertSessionHasErrors('guarantor_id');
        $this->put(route('insurance.receipts.update',$receipt),$payload)->assertSessionHasErrors('guarantor_id');
        $this->assertNotEquals($general->id,$receipt->fresh()->guarantor_id);
        unset($payload['guarantor_id']);
        $this->post(route('insurance.receipts.store'),$payload)->assertSessionHasErrors('guarantor_id');
        $this->get(route('cashier.receipts.index'))->assertForbidden();
        $this->get(route('excel.receipts'))->assertForbidden();
    }

    public function test_insurance_keeps_cashier_locks_for_posted_and_bpjs_linked_receipts(): void
    {
        $posted = $this->receipt($this->owner->id);
        $linked = $this->receipt($this->owner->id);
        $posted->guarantor->update(['category'=>'BPJS']);
        $linked->guarantor->update(['category'=>'BPJS']);
        $user = $this->insuranceUser();
        $posted->update(['cashier_id'=>$user->id]);
        $linked->update(['cashier_id'=>$user->id]);
        $posted->journal()->create(['journal_no'=>'TEST-LOCK','journal_date'=>'2026-10-25','description'=>'Lock','status'=>'POSTED','created_by'=>$user->id]);
        DB::table('bpjs_claim_payments')->insert(['receipt_id'=>$linked->id]);
        foreach ([$posted,$linked] as $receipt) {
            $this->get(route('insurance.receipts.edit',$receipt))->assertRedirect(route('insurance.receipts.index'));
            $this->put(route('insurance.receipts.update',$receipt),['amount'=>1])->assertRedirect(route('insurance.receipts.index'));
            $this->delete(route('insurance.receipts.destroy',$receipt))->assertRedirect(route('insurance.receipts.index'));
            $this->assertNotNull($receipt->fresh());
        }
    }
    public function test_referral_crud_for_all_four_roles_keeps_journals_balanced(): void
    {
        $account = ChartAccount::create(['code'=>'REF-COA', 'name'=>'Beban Referal', 'type'=>'EXPENSE', 'normal_balance'=>'DEBIT', 'is_referral'=>true]);
        $receipt = $this->receipt($this->owner->id);
        foreach (['KASIR', 'ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING'] as $code) {
            $user = $this->cashier;
            if ($code !== 'KASIR') {
                $user = User::factory()->create();
                $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'), 'code'=>$code, 'name'=>$code]);
                $user->roles()->attach($role);
            }
            $this->actingAs($user);
            $this->get(route('accounting.referrals.index'))->assertOk();
            $this->get(route('accounting.referrals.create'))->assertOk()->assertDontSee('name="chart_account_id"', false)->assertDontSee('name="receipt_id"', false);
            $recipient = \App\Models\ReferralRecipient::firstOrCreate(['name'=>'Dokter Referal']);
            $payload = ['referral_recipient_id'=>$recipient->id, 'referral_patient_name'=>'Pasien Uji', 'referral_medical_record_no'=>'RM-001', 'referral_procedure'=>'SC', 'expense_date'=>'2026-10-26', 'receipt_id'=>$receipt->id, 'chart_account_id'=>$account->id,
                'referral_recipient'=>'Dokter Referal', 'amount'=>250000, 'payment_method'=>'CASH', 'cash_bank_account_id'=>$this->cash->id];
            $this->post(route('accounting.referrals.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('accounting.referrals.index'));
            $expense = Expense::where('expense_type', 'REFERRAL')->firstOrFail();
            $journal = $expense->journal()->firstOrFail();
            $this->assertNull($expense->receipt_id);
            $this->assertSame($account->id, $expense->chart_account_id);
            $this->get(route('accounting.referrals.edit', $expense))->assertOk();
            $this->put(route('accounting.referrals.update', $expense), array_replace($payload, ['amount'=>300000]))->assertSessionHasNoErrors();
            $this->assertEquals(300000, $journal->lines()->sum('debit'));
            $this->assertEquals(300000, $journal->lines()->sum('credit'));
            $response = $this->get(route('accounting.referrals.export', ['search'=>'Dokter Referal']))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            unlink($path);
            $this->assertStringContainsString('<c r="A5" s="4"><v>1</v></c>', $xml);
            $this->assertStringContainsString('Dokter Referal', $xml);
            $this->assertStringNotContainsString($expense->expense_no, $xml);
            $this->delete(route('accounting.referrals.destroy', $expense))->assertRedirect(route('accounting.referrals.index'));
            $this->assertDatabaseMissing('expenses', ['id'=>$expense->id]);
            $this->assertDatabaseMissing('journal_entries', ['id'=>$journal->id]);
        }
    }

    public function test_insurance_cannot_access_referrals_or_change_coa(): void
    {
        $expense = $this->expense(['expense_type'=>'REFERRAL']);
        $this->insuranceUser();
        $this->get(route('accounting.referrals.index'))->assertForbidden();
        $this->get(route('accounting.referrals.export'))->assertForbidden();
        $this->post(route('accounting.referrals.store'), [])->assertForbidden();
        $this->put(route('accounting.referrals.update', $expense), [])->assertForbidden();
        $this->delete(route('accounting.referrals.destroy', $expense))->assertForbidden();
        $this->post(route('accounting.master.store', 'accounts'), [])->assertForbidden();
    }

    public function test_receipt_export_uses_sequence_instead_of_transaction_code(): void
    {
        $receipt = $this->receipt($this->owner->id);
        $response = $this->get(route('excel.receipts'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('<c r="A5" s="4"><v>1</v></c>', $xml);
        $this->assertStringNotContainsString('No Transaksi', $xml);
        $this->assertStringNotContainsString($receipt->transaction_no, $xml);
    }

}
