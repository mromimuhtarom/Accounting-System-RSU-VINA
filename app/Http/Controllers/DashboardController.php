<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Http\Request;
use App\Models\InsuranceMonthlyClaim;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $role = strtoupper(trim((string) ($request->user()->accountingRole() ?? '')));
        if ($role === 'ASURANSI') {
            $year = max(2020,min(2100,(int)$request->input('year',now()->year)));
            $monthly = InsuranceMonthlyClaim::where('year',$year)->selectRaw('month, SUM(claimed_amount) total')->groupBy('month')->pluck('total','month');
            $totals = InsuranceMonthlyClaim::where('year',$year)->selectRaw('SUM(claimed_amount) claimed, SUM(COALESCE(approved_amount,0)) approved, SUM(paid_amount) paid')->first();
            return view('insurance.dashboard',compact('year','monthly','totals'));
        }

        $role = $role !== '' ? $role : null;
        if ($role === 'CASEMIX') {
            return redirect()->route('accounting.bpjs.index');
        }

        $stats = [];
        $latestReceipts = collect();
        $guarantorComposition = collect();
        // Tahun periode eksplisit, tidak bergantung pada nilai default client.
        $year = (int) $request->query('tahun', now()->year);
        if ($year < 2020 || $year > (int) now()->year + 1) {
            $year = (int) now()->year;
        }

        if ($role === 'KASIR') {
            $stats = [
                'today_count' => Receipt::where('cashier_id', $request->user()->id)
                    ->where('status','ACTIVE')->whereDate('transaction_date', today())->count(),
                'today_income' => Receipt::where('cashier_id', $request->user()->id)
                    ->where('status','ACTIVE')->whereDate('transaction_date', today())
                    ->whereIn('payment_method', ['CASH','TRANSFER','QRIS','CARD'])->sum('amount'),
            ];
        }

        if (in_array($role, ['ACCOUNTING','ADMIN_ACCOUNTING','ATASAN','FINANCE','KEPALA_KEUANGAN','DIREKTUR','OWNER'])) {
            $active = Receipt::query()->where('receipts.status','ACTIVE')
                ->whereYear('receipts.transaction_date', $year);
            // GUARANTOR menunjukkan mekanisme penjamin/tagihan, bukan bukti uang telah cair.
            // NULL harus ditinjau manual. Jangan dipindah otomatis menjadi kas atau BPJS.
            $cash = (clone $active)->whereIn('receipts.payment_method', ['CASH','TRANSFER','QRIS','CARD']);
            $tagihan = (clone $active)->where('receipts.payment_method', 'GUARANTOR');
            $unknown = (clone $active)->whereNull('receipts.payment_method');
            $stats = [
                'income' => (clone $cash)->sum('receipts.amount'),
                'expense' => Expense::where('status','ACTIVE')->whereYear('expense_date', $year)->sum('amount'),
                'transactions' => (clone $cash)->count(),
                'tagihan' => (clone $tagihan)->sum('receipts.amount'),
                'tagihan_count' => (clone $tagihan)->count(),
                'unknown_method' => (clone $unknown)->sum('receipts.amount'),
                'unknown_method_count' => (clone $unknown)->count(),
                'unclassified' => (clone $cash)->whereNull('receipts.guarantor_id')->sum('receipts.amount'),
                'total_records' => (clone $active)->sum('receipts.amount'),
                'total_record_count' => (clone $active)->count(),
            ];
            // Komposisi seluruh penerimaan TERCATAT (bukan klaim sebagai kas cair). Termasuk penjamin
            // yang sudah tidak aktif, dan penjamin kosong/ID tidak ditemukan.
            $guarantorComposition = (clone $active)
                ->leftJoin('guarantors', 'receipts.guarantor_id', '=', 'guarantors.id')
                ->selectRaw("COALESCE(guarantors.name, 'Belum ada penjamin') AS guarantor_name, COALESCE(SUM(receipts.amount),0) AS total, COUNT(receipts.id) AS transactions")
                ->groupBy('guarantors.id', 'guarantors.name')
                ->orderByDesc('total')->get();

            $latestReceipts = Receipt::with(['patient','serviceUnit','guarantor','cashier'])
                ->where('status','ACTIVE')->whereYear('transaction_date',$year)
                ->orderByDesc('transaction_date')->orderByDesc('id')->limit(8)->get();
        }
        return view('dashboard', compact('role','stats','latestReceipts','guarantorComposition','year'));
    }
}
