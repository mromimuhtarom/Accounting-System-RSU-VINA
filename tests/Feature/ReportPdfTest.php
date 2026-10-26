<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class ReportPdfTest extends ExpenseAccessTest
{
    private function prepareReport(): void
    {
        (require database_path('migrations/2026_08_18_000008_create_financial_groups_table.php'))->up();
        $role = DB::table('roles')->insertGetId([
            'application_id' => DB::table('applications')->value('id'),
            'code' => 'ACCOUNTING', 'name' => 'Accounting',
        ]);
        auth()->user()->roles()->attach($role);
        auth()->user()->unsetRelation('roles');
    }

    public function test_export_uses_displayed_period_and_existing_report_totals(): void
    {
        $this->prepareReport();
        Receipt::create([
            'transaction_no' => 'PDF-TEST', 'transaction_date' => '2026-10-15',
            'income_code' => 'TEST', 'amount' => 250000, 'status' => 'ACTIVE',
            'cashier_id' => auth()->id(), 'payment_method' => 'CASH',
        ]);
        Expense::create([
            'expense_no' => 'PDF-EXP', 'expense_date' => '2026-10-16',
            'expense_type' => 'GENERAL', 'expense_category_id' => DB::table('expense_categories')->value('id'),
            'chart_account_id' => DB::table('expense_categories')->value('expense_account_id'),
            'description' => 'PDF test', 'amount' => 75000, 'status' => 'ACTIVE',
            'created_by' => auth()->id(), 'payment_method' => 'CASH',
            'cash_bank_account_id' => DB::table('cash_bank_accounts')->value('id'),
        ]);
        $before = [Receipt::count(), Expense::count()];
        foreach (['monthly' => ['month' => 10, 'year' => 2026], 'yearly' => ['year' => 2026]] as $report => $period) {
            $response = $this->get(route('reports.'.$report, $period))->assertOk()
                ->assertSee('Export PDF')->assertSee('report-pdf.js')
                ->assertSee($report === 'monthly' ? 'Laporan_Bulanan_2026-10.pdf' : 'Laporan_Tahunan_2026.pdf');
            $this->assertEquals(250000, $response->viewData('totalIncome'));
            $this->assertEquals(75000, $response->viewData('totalExpense'));
            $this->assertEquals(175000, $response->viewData('surplus'));
            if ($report === 'yearly') {
                $this->assertEquals(250000, $response->viewData('incomeMonths')[10]);
                $this->assertEquals(75000, $response->viewData('expenseMonths')[10]);
            }
        }
        $this->assertSame($before, [Receipt::count(), Expense::count()]);
    }

    public function test_empty_and_other_periods_keep_export_available(): void
    {
        $this->prepareReport();
        $this->get(route('reports.monthly', ['month' => 2, 'year' => 2025]))->assertOk()
            ->assertSee('Laporan_Bulanan_2025-02.pdf')->assertSee('Belum ada transaksi')
            ->assertViewHas('totalIncome', 0)->assertViewHas('totalExpense', 0);
        $this->get(route('reports.yearly', ['year' => 2025]))->assertOk()
            ->assertSee('Laporan_Tahunan_2025.pdf')->assertViewHas('totalIncome', 0);
    }

    public function test_reports_still_require_authentication(): void
    {
        auth()->logout();
        $this->get(route('reports.monthly'))->assertRedirect(route('login'));
        $this->get(route('reports.yearly'))->assertRedirect(route('login'));
    }
}
