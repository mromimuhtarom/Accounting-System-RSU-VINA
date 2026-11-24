<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\FinancialGroup;
use App\Models\Receipt;
use App\Services\CoaReportGroups;
use App\Services\LedgerAmounts;
use Illuminate\Http\Request;
use App\Models\InsuranceMonthlyClaim;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $role = strtoupper(trim((string) ($request->user()->accountingRole() ?? '')));
        if (in_array($role, ['PETUGAS_ASET', 'ADMIN_ASET'], true)) return redirect()->route('assets.index');
        if ($role === 'IT') return app(\App\Http\Controllers\IT\DashboardController::class)->index($request);
        if ($role === 'ASURANSI') {
            $year = max(2020,min(2100,(int)$request->input('year',now()->year)));
            $monthly = InsuranceMonthlyClaim::where('year',$year)->selectRaw('month, SUM(claimed_amount) total')->groupBy('month')->pluck('total','month');
            $totals = InsuranceMonthlyClaim::where('year',$year)->selectRaw('SUM(claimed_amount) claimed, SUM(COALESCE(approved_amount,0)) approved, SUM(paid_amount) paid')->first();
            return view('insurance.dashboard',compact('year','monthly','totals'));
        }

        $role = $role !== '' ? $role : null;
        if ($role === 'CASEMIX' && Route::has('accounting.bpjs.index')) {
            return redirect()->route('accounting.bpjs.index');
        }

        $stats = [];
        $latestReceipts = collect();
        $guarantorComposition = collect();
        $incomeByGroup = $expenseByGroup = collect();
        // Tahun periode eksplisit, tidak bergantung pada nilai default client.
        $year = (int) $request->query('tahun', now()->year);
        if ($year < 2020 || $year > (int) now()->year + 1) {
            $year = (int) now()->year;
        }

        if ($role === 'KASIR') {
            $summary = Receipt::where('cashier_id', $request->user()->id)->where('status', 'ACTIVE')
                ->where('transaction_date', today()->toDateString())
                ->selectRaw("COUNT(*) AS today_count, COALESCE(SUM(CASE WHEN payment_method IN ('CASH','TRANSFER','QRIS','CARD') THEN amount ELSE 0 END),0) AS today_income")->first();
            $stats = ['today_count'=>(int) $summary->today_count, 'today_income'=>$summary->today_income];
        }

        if (in_array($role, ['ACCOUNTING','ADMIN_ACCOUNTING','ATASAN','FINANCE','KEPALA_KEUANGAN','DIREKTUR','OWNER'])) {
            $period = [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
            $active = Receipt::query()->where('receipts.status','ACTIVE')
                ->whereBetween('receipts.transaction_date', $period);
            // GUARANTOR menunjukkan mekanisme penjamin/tagihan, bukan bukti uang telah cair.
            // NULL harus ditinjau manual. Jangan dipindah otomatis menjadi kas atau BPJS.
            // One scan keeps all figures current without repeated aggregate queries.
            $stats = (clone $active)->selectRaw("
                COALESCE(SUM(CASE WHEN payment_method IN ('CASH','TRANSFER','QRIS','CARD') THEN amount ELSE 0 END),0) AS income,
                COUNT(CASE WHEN payment_method IN ('CASH','TRANSFER','QRIS','CARD') THEN 1 END) AS transactions,
                COALESCE(SUM(CASE WHEN payment_method = 'GUARANTOR' THEN amount ELSE 0 END),0) AS tagihan,
                COUNT(CASE WHEN payment_method = 'GUARANTOR' THEN 1 END) AS tagihan_count,
                COALESCE(SUM(CASE WHEN payment_method IS NULL THEN amount ELSE 0 END),0) AS unknown_method,
                COUNT(CASE WHEN payment_method IS NULL THEN 1 END) AS unknown_method_count,
                COALESCE(SUM(CASE WHEN payment_method IN ('CASH','TRANSFER','QRIS','CARD') AND guarantor_id IS NULL THEN amount ELSE 0 END),0) AS unclassified,
                COALESCE(SUM(amount),0) AS total_records, COUNT(*) AS total_record_count
            ")->first()->getAttributes();
            $groups = FinancialGroup::where('is_active', true)->get(['code', 'name', 'type']);
            $receiptGroups = (clone $active)
                ->leftJoin('guarantors', 'receipts.guarantor_id', '=', 'guarantors.id')
                ->leftJoin('chart_accounts as coa', 'coa.id', '=', 'receipts.chart_account_id')
                ->leftJoin('financial_groups as g', fn ($join) => $join->on('g.id', '=', 'coa.financial_group_id')
                    ->where('g.type', 'INCOME')->where('g.is_active', true))
                ->select('guarantors.id as guarantor_id', 'guarantors.category', 'guarantors.is_bpjs', 'g.code')
                ->selectRaw("COALESCE(guarantors.name, 'Belum ada penjamin') AS guarantor_name, SUM(receipts.amount) AS total, COUNT(*) AS transactions")
                ->groupBy('guarantors.id', 'guarantors.name', 'guarantors.category', 'guarantors.is_bpjs', 'g.code')->get();
            $guarantorComposition = $receiptGroups->groupBy('guarantor_id')->map(fn ($rows) => (object) [
                'guarantor_name' => $rows->first()->guarantor_name,
                'total' => $rows->sum(fn ($row) => LedgerAmounts::cents($row->total)) / 100,
                'transactions' => $rows->sum('transactions'),
            ])->sortByDesc('total')->values();
            $incomeByGroup = CoaReportGroups::totals($receiptGroups, 'INCOME',
                fn ($row) => CoaReportGroups::incomeCode($row->code, $row->category, (bool) $row->is_bpjs), $groups);

            $expenseGroups = Expense::where('expenses.status', 'ACTIVE')->whereBetween('expense_date', $period)
                ->leftJoin('chart_accounts as coa', 'coa.id', '=', 'expenses.chart_account_id')
                ->leftJoin('financial_groups as g', fn ($join) => $join->on('g.id', '=', 'coa.financial_group_id')
                    ->where('g.type', 'EXPENSE')->where('g.is_active', true))
                ->select('g.code')->selectRaw('SUM(expenses.amount) AS total, COUNT(*) AS transactions')->groupBy('g.code')->get();
            $expenseByGroup = CoaReportGroups::totals($expenseGroups, 'EXPENSE', fn ($row) => $row->code, $groups);
            $stats['expense'] = $expenseGroups->sum(fn ($row) => LedgerAmounts::cents($row->total)) / 100;

            $latestReceipts = Receipt::with(['account:id,code,name','patient','serviceUnit','guarantor','cashier','cashBankAccount'])
                ->where('status','ACTIVE')->whereBetween('transaction_date', $period)
                ->orderByDesc('transaction_date')->orderByDesc('id')->limit(8)->get();
        }
        $referralSummary = collect();
        if (in_array($role, ['KASIR','ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN','ATASAN','FINANCE','DIREKTUR','OWNER'], true)) {
            $referralSummary = app(\App\Services\ReferralSummary::class)->rows(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));
        }
        $assetReport = \Illuminate\Support\Facades\Gate::allows('viewAny',\App\Models\Asset::class)
            ? app(\App\Services\AssetReporting::class)->summary($year) : null;
        $financialPosition = null;
        if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\OpeningBalance::class) && $year <= (int) now('Asia/Jakarta')->year) {
            $asOf = min(sprintf('%04d-12-31', $year), now('Asia/Jakarta')->toDateString());
            $financialPosition = app(\App\Services\FinancialPosition::class)->at($asOf);
        }
        return view('dashboard', compact('role','stats','latestReceipts','guarantorComposition','year','referralSummary','assetReport','financialPosition','incomeByGroup','expenseByGroup'));
    }
}
