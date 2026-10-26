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
        if ($role === 'IT') return app(\App\Http\Controllers\IT\DashboardController::class)->index($request);
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
            $stats['expense'] = Expense::where('status','ACTIVE')->whereBetween('expense_date', $period)->sum('amount');
            // Komposisi seluruh penerimaan TERCATAT (bukan klaim sebagai kas cair). Termasuk penjamin
            // yang sudah tidak aktif, dan penjamin kosong/ID tidak ditemukan.
            $guarantorComposition = (clone $active)
                ->leftJoin('guarantors', 'receipts.guarantor_id', '=', 'guarantors.id')
                ->selectRaw("COALESCE(guarantors.name, 'Belum ada penjamin') AS guarantor_name, COALESCE(SUM(receipts.amount),0) AS total, COUNT(receipts.id) AS transactions")
                ->groupBy('guarantors.id', 'guarantors.name')
                ->orderByDesc('total')->get();

            $latestReceipts = Receipt::with(['patient','serviceUnit','guarantor','cashier','cashBankAccount'])
                ->where('status','ACTIVE')->whereBetween('transaction_date', $period)
                ->orderByDesc('transaction_date')->orderByDesc('id')->limit(8)->get();
        }
        return view('dashboard', compact('role','stats','latestReceipts','guarantorComposition','year'));
    }
}
