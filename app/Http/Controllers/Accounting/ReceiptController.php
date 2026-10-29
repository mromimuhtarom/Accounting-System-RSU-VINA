<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartAccount;
use App\Models\Receipt;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = Receipt::with([
            'account',
            'cashier',
            'journal',
            'referralExpense',
        ])->latest('transaction_date')
            ->latest('id');

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
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

        return view('accounting.receipts.index', [
            'receipts' => $page,
            'bpjsLinkedIds' => $bpjsLinkedIds,
            'accounts' => $this->accounts(),
        ]);
    }

    public function create()
    {
        $this->requireAccountingReceiptEditor();

        return view(request()->boolean('multiple') ? 'transactions.batch' : 'accounting.receipts.create', [
            'module'=>'receipts', 'action'=>route('accounting.receipts.store'), 'singleUrl'=>route('accounting.receipts.create'), 'returnUrl'=>route('accounting.receipts.index'),
            'accounts' => $this->accounts(),
            'guarantors' => \App\Models\Guarantor::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAccountingReceiptEditor();
        if ($request->exists('rows')) return app(\App\Services\BatchTransactionService::class)->store($request, fn ($row) => $this->validatedData($row), fn ($row) => $this->store($row), 'accounting.receipts.index');
        $data = $this->validatedData($request);
        $data['transaction_no'] = 'RCV-'
            . now()->format('YmdHis')
            . '-'
            . (string) \Illuminate\Support\Str::ulid();
        $data['cashier_id'] = $request->user()->id;
        $data['status'] = 'ACTIVE';

        $posted = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $receipt = Receipt::create($data);
            return app(\App\Services\AutomaticJournalService::class)->receipt($receipt, $request->user()->id);
        });

        return redirect()
            ->route('accounting.receipts.index')
            ->with('success', $posted ? 'Penerimaan tersimpan dan jurnal otomatis terposting.' : 'Penerimaan tersimpan; jurnal menunggu kelengkapan pemetaan akun dan metode pembayaran.');
    }

    public function edit(Receipt $receipt)
    {
        $this->requireAccountingReceiptEditor();
        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return back()->with('error', 'Penerimaan sudah dialokasikan ke klaim BPJS. Koreksi melalui proses rekonsiliasi, bukan mengubah transaksi langsung.');
        }

        if ($receipt->journal()->exists()) {
            return redirect()
                ->route('accounting.receipts.index')
                ->withErrors([
                    'transaction' => 'Transaksi yang sudah memiliki jurnal tidak dapat diubah.',
                ]);
        }

        return view('accounting.receipts.edit', [
            'receipt' => $receipt,
            'accounts' => $this->accounts(),
        ]);
    }

    public function update(Request $request, Receipt $receipt)
    {
        $this->requireAccountingReceiptEditor();
        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return back()->with('error', 'Penerimaan sudah dialokasikan ke klaim BPJS. Koreksi melalui proses rekonsiliasi, bukan mengubah transaksi langsung.');
        }

        if ($receipt->journal()->exists()) {
            return back()->withErrors([
                'transaction' => 'Transaksi yang sudah memiliki jurnal tidak dapat diubah.',
            ]);
        }

        $receipt->update(
            $this->validatedData($request, true)
        );

        return redirect()
            ->route('accounting.receipts.index')
            ->with('success', 'Pemasukan diperbarui.');
    }

    public function destroy(Receipt $receipt)
    {
        $this->requireAccountingReceiptEditor();
        if (\Illuminate\Support\Facades\DB::table('bpjs_claim_payments')->where('receipt_id', $receipt->id)->exists()) {
            return back()->with('error', 'Penerimaan sudah dialokasikan ke klaim BPJS. Koreksi melalui proses rekonsiliasi, bukan mengubah transaksi langsung.');
        }

        if ($receipt->journal()->exists()) {
            return back()->with(
                'error',
                'Pemasukan yang sudah dijurnal tidak dapat dihapus.'
            );
        }

        if ($receipt->referralExpense()->exists()) {
            return back()->with(
                'error',
                'Pemasukan yang sudah memiliki pengeluaran referal tidak dapat dihapus.'
            );
        }

        $receipt->delete();

        return redirect()
            ->route('accounting.receipts.index')
            ->with('success', 'Pemasukan berhasil dihapus.');
    }

    public function generateJournal(
        Receipt $receipt,
        JournalService $service,
        Request $request
    ) {
        $service->fromReceipt(
            $receipt,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Jurnal pemasukan berhasil dibuat.'
        );
    }

    private function requireAccountingReceiptEditor(): void
    {
        $user = request()->user();
        $application = config('accounting.application_code') ?: 'ACCOUNTING';
        abort_unless($user && collect(['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING'])
            ->contains(fn ($role) => $user->hasRole($role, $application)), 403);
    }

    private function requireAccountingJournalEditor(): void
    {
        $user = request()->user();
        $application = config('accounting.application_code') ?: 'ACCOUNTING';
        abort_unless($user && ($user->hasRole('ACCOUNTING', $application)
            || $user->hasRole('KEPALA_KEUANGAN', $application)), 403);
    }

    public function validatedData(
        Request $request,
        bool $includeStatus = false
    ): array {
        $rules = [
            'transaction_date' => ['required', 'date'],
            'chart_account_id' => ['required', Rule::exists('chart_accounts', 'id')->where('type', 'REVENUE')->where('is_active', 1)],
            'notes' => ['required', 'string', 'max:1000'],
            'doctor_name' => ['nullable', 'string', 'max:150'],
            'hd' => ['nullable', 'string', 'max:100'],
            'rs' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'guarantor_id' => ['nullable', 'integer', 'exists:guarantors,id'],
            'service_type' => ['nullable', 'in:RAWAT_JALAN,RAWAT_INAP,MCU,LAINNYA'],
        ];

        if ($includeStatus) {
            $rules['status'] = ['required', 'in:ACTIVE,CANCELLED'];
        }

        return $request->validate($rules);
    }

    private function accounts()
    {
        return ChartAccount::where('is_active', true)
            ->where('type', 'REVENUE')
            ->orderBy('code')
            ->get();
    }
}
