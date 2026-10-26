<?php
namespace App\Http\Controllers;
use App\Models\InsuranceMonthlyClaim as Claim;
use App\Models\Receipt;
use App\Models\Expense;
use App\Services\InsuranceExcel;
use Illuminate\Http\Request;

class InsuranceExcelController extends Controller
{
    public function claims(Request $request)
    {
        $query = Claim::with('guarantor');
        foreach (['year','month','guarantor_id'] as $key) if ($request->filled($key)) $query->where($key, $request->input($key));
        $rows = $query->orderByDesc('year')->orderByDesc('month')->get()->map(fn($c) => [
            $c->year, $c->month, $c->guarantor?->name ?? '-', $c->service_type,
            (float) $c->claimed_amount, $c->approved_amount === null ? '' : (float) $c->approved_amount,
            (float) $c->paid_amount, max(0,(float) $c->approved_amount-(float) $c->paid_amount), $c->status
        ])->all();
        return InsuranceExcel::download('Klaim_Asuransi_RSUVE.xlsx', ['Tahun','Bulan','Penjamin','Jenis Layanan','Diajukan','Disetujui','Cair','Sisa','Status'], $rows, 'Rekap klaim asuransi; bukan bukti seluruh dana sudah cair.');
    }
    public function receipts(Request $request)
    {
        // Filter sama dengan halaman Penerimaan: tanggal, COA, petugas, dan pencarian.
        $query = Receipt::with(['guarantor','cashier','account']);
        if ($request->filled('date_from')) $query->whereDate('transaction_date','>=',$request->date_from);
        if ($request->filled('date_to')) $query->whereDate('transaction_date','<=',$request->date_to);
        if ($request->filled('account_id')) $query->where('chart_account_id',$request->account_id);
        if ($request->filled('cashier_id')) $query->where('cashier_id',$request->cashier_id);
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(fn($q) => $q->where('transaction_no','like',"%{$term}%")
                ->orWhere('income_code','like',"%{$term}%")
                ->orWhere('notes','like',"%{$term}%")
                ->orWhere('patient_name','like',"%{$term}%")
                ->orWhere('doctor_name','like',"%{$term}%")
                ->orWhereHas('account',fn($a) => $a->where('code','like',"%{$term}%")->orWhere('name','like',"%{$term}%")));
        }
        $rows = $query->orderByDesc('transaction_date')->orderByDesc('id')->get()->map(fn($v) => [
            $v->transaction_no, (string) $v->transaction_date, $v->income_code ?: ($v->account?->code ?? ''),
            $v->account?->name ?? '', $v->notes ?: ($v->patient_name ?? ''), $v->patient_name ?? '',
            $v->doctor_name ?? '', $v->guarantor?->name ?? 'Belum dipilih', $v->payment_method ?? 'Belum diisi',
            $v->cashier?->name ?? '', (float) $v->amount, $v->status ?? ''
        ])->all();
        return InsuranceExcel::download('Penerimaan_RSUVE.xlsx',
            ['No Transaksi','Tanggal','Kode COA','Nama Akun','Keterangan','Pasien','Dokter','Penjamin','Metode','Petugas','Nominal','Status'],
            $rows, 'Data berdasarkan filter halaman Penerimaan; semua status ditampilkan.');
    }
    public function expenses(Request $request)
    {
        $query = Expense::with(['category','cashBankAccount','account']);
        if ($request->filled('date_from')) $query->whereDate('expense_date','>=',$request->date_from);
        if ($request->filled('date_to')) $query->whereDate('expense_date','<=',$request->date_to);
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn($q) => $q->where('expense_no','like',"%{$term}%")
                ->orWhere('description','like',"%{$term}%")
                ->orWhere('reference_no','like',"%{$term}%")
                ->orWhereHas('category', fn($c)=>$c->where('name','like',"%{$term}%")));
        }
        $rows = $query->orderByDesc('expense_date')->orderByDesc('id')->get()->map(fn($e) => [
            $e->expense_no, (string) $e->expense_date, $e->expense_type ?? 'GENERAL',
            $e->category?->name ?? '', $e->account?->code ?? '', $e->description,
            $e->payment_method ?? '', $e->cashBankAccount?->name ?? '', (float) $e->amount,
            $e->status ?? ''
        ])->all();
        return InsuranceExcel::download('Pengeluaran_RSUVE.xlsx',
            ['No Transaksi','Tanggal','Jenis','Kategori','COA','Keterangan','Metode','Kas/Bank','Nominal','Status'],
            $rows, 'Data berdasarkan filter halaman Pengeluaran; semua status ditampilkan.');
    }
}
