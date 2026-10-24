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
        $query = Expense::with([
            'receipt.account',
            'cashBankAccount',
            'journal',
        ])
            ->where('expense_type', 'REFERRAL')
            ->latest('expense_date')
            ->latest('id');

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('expense_no', 'like', "%{$search}%")
                    ->orWhere('referral_recipient', 'like', "%{$search}%")
                    ->orWhereHas('receipt', function ($receipt) use ($search) {
                        $receipt->where('transaction_no', 'like', "%{$search}%")
                            ->orWhere('income_code', 'like', "%{$search}%")
                            ->orWhereHas('account', function ($account) use ($search) {
                                $account->where('code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%");
                            })
                            ->orWhere('notes', 'like', "%{$search}%");
                    });
            });
        }

        return view('accounting.referrals.index', [
            'referrals' => $query->paginate(10)->withQueryString(),
        ]);
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
        $receipt = Receipt::findOrFail($data['receipt_id']);
        $category = $this->referralCategory();

        $data['patient_id'] = $receipt->patient_id;
        $data['expense_no'] = 'REF-'
            . now()->format('YmdHis')
            . '-'
            . random_int(100, 999);
        $data['expense_type'] = 'REFERRAL';
        $data['expense_category_id'] = $category->id;
        $data['chart_account_id'] = $category->expense_account_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'ACTIVE';
        $data['description'] = $data['description']
            ?: 'Pengeluaran referal sesi ' . $receipt->transaction_no;

        $posted = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
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

        if ($referral->journal()->exists()) {
            return redirect()
                ->route('accounting.referrals.index')
                ->with('error', 'Referal yang sudah dijurnal tidak dapat diedit.');
        }

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

        if ($referral->journal()->exists()) {
            return back()->withErrors([
                'referral' => 'Referal yang sudah dijurnal tidak dapat diedit.',
            ]);
        }

        $data = $this->validated($request, $referral->id);
        $receipt = Receipt::findOrFail($data['receipt_id']);
        $category = $this->referralCategory();

        $data['patient_id'] = $receipt->patient_id;
        $data['expense_type'] = 'REFERRAL';
        $data['expense_category_id'] = $category->id;
        $data['chart_account_id'] = $category->expense_account_id;
        $data['description'] = $data['description']
            ?: 'Pengeluaran referal sesi ' . $receipt->transaction_no;

        $referral->update($data);

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
            'cashBanks' => CashBankAccount::where('is_active', true)
                ->orderBy('name')
                ->get(),
            'editingReferralId' => $editing?->id,
        ];
    }

    private function validated(
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

        return $request->validate([
            'expense_date' => ['required', 'date'],
            'receipt_id' => [
                'required',
                'exists:receipts,id',
                $uniqueSession,
            ],
            'referral_recipient' => [
                'required',
                'string',
                'max:150',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                'in:CASH,TRANSFER,QRIS,CARD',
            ],
            'cash_bank_account_id' => [
                'required',
                'exists:cash_bank_accounts,id',
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
    }

    private function referralCategory(): ExpenseCategory
    {
        return ExpenseCategory::where('code', 'REFERAL')
            ->firstOrFail();
    }

    private function ensureReferral(Expense $expense): void
    {
        abort_unless(
            $expense->expense_type === 'REFERRAL',
            404
        );
    }
}
