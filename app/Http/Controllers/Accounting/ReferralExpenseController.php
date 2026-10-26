<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Receipt;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferralExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->query($request);
        return view('accounting.referrals.index', ['referrals' => $query->paginate(10)->withQueryString()]);
    }

    private function query(Request $request)
    {
        $query = Expense::with(['referralRecipient', 'patient', 'account', 'receipt.account', 'cashBankAccount', 'journal'])
            ->where('expense_type', 'REFERRAL')->latest('expense_date')->latest('id');
        if ($request->filled('date_from')) $query->whereDate('expense_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('expense_date', '<=', $request->date_to);
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(fn ($q) => $q->where('referral_recipient', 'like', "%{$term}%")
                ->orWhereHas('referralRecipient', fn ($r) => $r->where('name', 'like', "%{$term}%"))
                ->orWhere('referral_patient_name', 'like', "%{$term}%")
                ->orWhere('referral_medical_record_no', 'like', "%{$term}%")
                ->orWhere('expense_no', 'like', "%{$term}%")
                ->orWhereHas('receipt', fn ($r) => $r->where('transaction_no', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%")));
        }
        return $query;
    }

    public function create()
    {
        return view(
            'accounting.referrals.create',
            $this->formData()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $receipt = !empty($data['receipt_id']) ? Receipt::findOrFail($data['receipt_id']) : null;
        $category = $this->referralCategory($data['chart_account_id']);

        $data['patient_id'] = $receipt?->patient_id;
        $data['expense_no'] = 'REF-'
            . now()->format('YmdHis')
            . '-'
            . random_int(100, 999);
        $data['expense_type'] = 'REFERRAL';
        $data['expense_category_id'] = $category->id;
        // COA khusus referal sudah divalidasi.
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'ACTIVE';
        $data['description'] = ($data['description'] ?? null)
            ?: 'Pengeluaran referal sesi ' . ($receipt?->transaction_no ?? $data['referral_patient_name']);

        $posted = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $this->lockRecipient($data['referral_recipient_id']);
            $expense = Expense::create($data);
            return app(\App\Services\AutomaticJournalService::class)->expense($expense, $request->user()->id);
        });

        return redirect()
            ->route('accounting.referrals.index')
            ->with('success', $posted ? 'Referal tersimpan dan jurnal otomatis terposting.' : 'Referal tersimpan; jurnal menunggu pemetaan akun.');
    }

    public function edit(Expense $referral)
    {
        $this->ensureReferral($referral);

        return view(
            'accounting.referrals.edit',
            array_merge(
                $this->formData($referral),
                compact('referral')
            )
        );
    }

    public function update(Request $request, Expense $referral)
    {
        $this->ensureReferral($referral);

        $data = $this->validated($request, $referral->id);
        $receipt = !empty($data['receipt_id']) ? Receipt::findOrFail($data['receipt_id']) : null;
        $category = $this->referralCategory($data['chart_account_id']);

        $data['patient_id'] = $receipt?->patient_id;
        $data['expense_type'] = 'REFERRAL';
        $data['expense_category_id'] = $category->id;
        // COA khusus referal sudah divalidasi.
        $data['description'] = ($data['description'] ?? null)
            ?: 'Pengeluaran referal sesi ' . ($receipt?->transaction_no ?? $data['referral_patient_name']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $data) {
            $this->lockRecipient($data['referral_recipient_id'], $referral->referral_recipient_id);
            app(\App\Services\ExpenseTransactionService::class)->update($referral, $data);
        });

        return redirect()
            ->route('accounting.referrals.index')
            ->with('success', 'Pengeluaran referal berhasil diperbarui.');
    }

    public function generateJournal(
        Expense $referral,
        JournalService $service,
        Request $request
    ) {
        $this->ensureReferral($referral);
        $service->fromExpense(
            $referral,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Jurnal referal berhasil dibuat.'
        );
    }

    private function formData(?Expense $editing = null): array
    {
        $receipts = Receipt::with(['account', 'referralExpense'])
            ->where('status', 'ACTIVE')
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return [
            'sessions' => $receipts,
            'recipients' => \App\Models\ReferralRecipient::where('is_active', true)->when($editing?->referral_recipient_id, fn ($q) => $q->orWhere('id', $editing->referral_recipient_id))->orderBy('name')->get(),
            'accounts' => \App\Models\ChartAccount::where('is_active', true)->where('type', 'EXPENSE')->where('is_referral', true)->orderBy('code')->get(),
            'cashBanks' => CashBankAccount::where('is_active', true)
                ->orderBy('name')
                ->get(),
            'editingReferralId' => $editing?->id,
        ];
    }

    public function validated(
        Request $request,
        ?int $ignoreExpenseId = null
    ): array {
        $uniqueSession = Rule::unique('expenses', 'receipt_id')
            ->where(
                fn ($q) => $q->where('expense_type', 'REFERRAL')
            );

        if ($ignoreExpenseId) {
            $uniqueSession->ignore($ignoreExpenseId);
        }

        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'chart_account_id' => ['required', Rule::exists('chart_accounts', 'id')->where('is_active', true)->where('type', 'EXPENSE')->where('is_referral', true)],
            'receipt_id' => [
                'nullable',
                Rule::exists('receipts', 'id')->where('status', 'ACTIVE'),
                $uniqueSession,
            ],
            'referral_recipient_id' => ['required', Rule::exists('referral_recipients', 'id')->where(function ($q) use ($ignoreExpenseId) {
                $existing = $ignoreExpenseId ? Expense::findOrFail($ignoreExpenseId)->referral_recipient_id : null;
                $q->where('is_active', true)->when($existing, fn ($q) => $q->orWhere('id', $existing));
            })],
            'referral_patient_name' => ['required', 'string', 'max:150'],
            'referral_medical_record_no' => ['required', 'string', 'max:100'],
            'referral_procedure' => ['required', 'in:SC,RI'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                'in:CASH,TRANSFER,QRIS,CARD',
            ],
            'cash_bank_account_id' => [
                'required',
                Rule::exists('cash_bank_accounts', 'id')->where('is_active', true),
            ],
            'reference_no' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ], [
            'receipt_id.unique' => 'Sesi pemasukan tersebut sudah memiliki referal. Satu sesi hanya boleh memiliki satu referal.',
        ]);
        $data['referral_patient_name'] = trim($data['referral_patient_name']);
        $data['referral_medical_record_no'] = mb_strtoupper(trim($data['referral_medical_record_no']));
        return $data;
    }

    public function destroy(Expense $referral)
    {
        $this->ensureReferral($referral);
        app(\App\Services\ExpenseTransactionService::class)->delete($referral);
        return redirect()->route('accounting.referrals.index')->with('success', 'Pengeluaran referal berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->get()->map(fn ($e, $i) => [
            $i + 1, $e->expense_date->format('Y-m-d'), $e->referral_recipient_name,
            $e->referral_patient_name ?: ($e->patient?->name ?: $e->receipt?->patient_name),
            $e->referral_medical_record_no ?: $e->patient?->medical_record_no, $e->referral_procedure,
            $e->account?->code, $e->account?->name, $e->description,
            $e->payment_method, $e->cashBankAccount?->name, (float) $e->amount,
        ])->all();
        return \App\Services\InsuranceExcel::download('Pengeluaran_Referal.xlsx',
            ['No', 'Tanggal', 'Penerima Referal', 'Pasien', 'No RM', 'Tindakan', 'Kode COA', 'Nama Akun', 'Keterangan', 'Metode', 'Kas/Bank', 'Nominal'], $rows);
    }

    private function lockRecipient(int $id, ?int $existing = null): void
    {
        $recipient = \App\Models\ReferralRecipient::whereKey($id)->lockForUpdate()->firstOrFail();
        if (!$recipient->is_active && $existing !== $id) throw \Illuminate\Validation\ValidationException::withMessages(['referral_recipient_id'=>'Penerima telah dinonaktifkan. Pilih penerima aktif.']);
    }

    private function referralCategory(int $accountId): ExpenseCategory
    {
        $defaults = ['name'=>'Referal', 'is_active'=>true, 'expense_account_id'=>$accountId];
        if (\Illuminate\Support\Facades\Schema::hasColumn('expense_categories', 'financial_group_id')) {
            $defaults['financial_group_id'] = \App\Models\FinancialGroup::where('type', 'EXPENSE')->where('is_active', true)
                ->orderByRaw("CASE WHEN code = 'EXP_OPERATIONAL' THEN 0 ELSE 1 END")->value('id');
        }
        $category = ExpenseCategory::firstOrCreate(['code'=>'REFERAL'], $defaults);
        return $category;
    }

    private function ensureReferral(Expense $expense): void
    {
        abort_unless(
            $expense->expense_type === 'REFERRAL',
            404
        );
    }
}
