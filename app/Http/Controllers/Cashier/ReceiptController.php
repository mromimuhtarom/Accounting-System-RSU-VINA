<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\ChartAccount;
use App\Models\Receipt;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = Receipt::with(['account', 'journal', 'referralExpense', 'cashier'])
            ->latest('transaction_date')
            ->latest('id');

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->integer('cashier_id'));
        }

        if ($request->filled('account_id')) {
            $query->where('chart_account_id', $request->account_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                    ->orWhere('income_code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('doctor_name', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($account) use ($search) {
                        $account->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $page = $query->paginate(10)->withQueryString();
        $bpjsLinkedIds = \Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->whereIn('receipt_id', $page->getCollection()->pluck('id'))->pluck('receipt_id')->all();

        return view('cashier.receipts.index', [
            'receipts' => $page,
            'bpjsLinkedIds' => $bpjsLinkedIds,
            'accounts' => $this->accounts(),
            'cashiers' => \App\Models\User::query()->whereIn('id', Receipt::query()->select('cashier_id')->whereNotNull('cashier_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('cashier.receipts.create', [
            'accounts' => $this->accounts(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['transaction_no'] = 'RCV-'
            . now()->format('YmdHis')
            . '-'
            . random_int(100, 999);
        $data['cashier_id'] = $request->user()->id;
        $data['status'] = 'ACTIVE';

        $posted = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $receipt = Receipt::create($data);
            return app(\App\Services\AutomaticJournalService::class)->receipt($receipt, $request->user()->id);
        });

        return redirect()
            ->route('cashier.receipts.index')
            ->with('success', $posted ? 'Penerimaan tersimpan dan jurnal otomatis terposting.' : 'Penerimaan tersimpan; jurnal menunggu kelengkapan pemetaan akun dan metode pembayaran.');
    }

    public function edit(Request $request, Receipt $receipt)
    {
        $this->ensureOwnedByCashier($request, $receipt);

        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return redirect()->route('cashier.receipts.index')->with('error', 'Penerimaan terhubung dengan pelunasan BPJS; lakukan koreksi melalui rekonsiliasi BPJS.');
        }

        if ($receipt->journal()->exists()) {
            return redirect()
                ->route('cashier.receipts.index')
                ->with('error', 'Transaksi sudah dijurnal oleh Accounting sehingga tidak dapat diedit oleh Kasir.');
        }

        return view('cashier.receipts.edit', [
            'receipt' => $receipt,
            'accounts' => $this->accounts(),
        ]);
    }

    public function update(Request $request, Receipt $receipt)
    {
        $this->ensureOwnedByCashier($request, $receipt);

        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return redirect()->route('cashier.receipts.index')->with('error', 'Penerimaan terhubung dengan pelunasan BPJS; lakukan koreksi melalui rekonsiliasi BPJS.');
        }

        if ($receipt->journal()->exists()) {
            return redirect()
                ->route('cashier.receipts.index')
                ->with('error', 'Transaksi sudah dijurnal oleh Accounting sehingga tidak dapat diedit oleh Kasir.');
        }

        $receipt->update($this->validatedData($request));

        return redirect()
            ->route('cashier.receipts.index')
            ->with('success', 'Pemasukan berhasil diperbarui.');
    }

    public function destroy(Request $request, Receipt $receipt)
    {
        $this->ensureOwnedByCashier($request, $receipt);

        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return redirect()->route('cashier.receipts.index')->with('error', 'Penerimaan terhubung dengan pelunasan BPJS; lakukan koreksi melalui rekonsiliasi BPJS.');
        }

        if ($receipt->journal()->exists()) {
            return redirect()
                ->route('cashier.receipts.index')
                ->with('error', 'Transaksi sudah dijurnal oleh Accounting sehingga tidak dapat dihapus oleh Kasir.');
        }

        if ($receipt->referralExpense()->exists()) {
            return redirect()
                ->route('cashier.receipts.index')
                ->with('error', 'Transaksi sudah memiliki pengeluaran referal sehingga tidak dapat dihapus.');
        }

        $receipt->delete();

        return redirect()
            ->route('cashier.receipts.index')
            ->with('success', 'Pemasukan berhasil dihapus.');
    }

    private function ensureOwnedByCashier(Request $request, Receipt $receipt): void
    {
        abort_unless(
            (int) $receipt->cashier_id === (int) $request->user()->id,
            403
        );
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'chart_account_id' => ['required', 'exists:chart_accounts,id'],
            'notes' => ['required', 'string', 'max:1000'],
            'doctor_name' => ['nullable', 'string', 'max:150'],
            'hd' => ['nullable', 'string', 'max:100'],
            'rs' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
    }

    private function accounts()
    {
        return ChartAccount::where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}
