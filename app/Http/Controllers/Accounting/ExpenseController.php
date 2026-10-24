<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with([
            'category',
            'account',
            'cashBankAccount',
            'patient',
            'receipt',
            'journal',
        ])->latest('expense_date');

        if ($this->isCashier($request)) {
            $query->where('created_by', $request->user()->id)
                ->where('expense_type', 'GENERAL');
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('expense_no','like','%'.$term.'%')
                  ->orWhere('description','like','%'.$term.'%')
                  ->orWhere('reference_no','like','%'.$term.'%')
                  ->orWhereHas('category',fn($category)=>$category->where('name','like','%'.$term.'%'));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        return view('accounting.expenses.index', [
            'expenses' => $query->paginate(10)->withQueryString(),
            'routePrefix' => $this->routePrefix($request),
            'isCashier' => $this->isCashier($request),
        ]);
    }

    public function create(Request $request)
    {
        return view('accounting.expenses.create', array_merge(
            $this->formData(),
            ['routePrefix' => $this->routePrefix($request)]
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['expense_no'] = 'EXP-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        $data['expense_type'] = 'GENERAL';
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'ACTIVE';

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request
                ->file('attachment')
                ->store('expenses', 'public');
        }

        Expense::create($data);

        return redirect()
            ->route($this->routePrefix($request) . '.index')
            ->with('success', 'Pengeluaran berhasil disimpan.');
    }

    public function edit(Request $request, Expense $pengeluaran)
    {
        $this->ensureAccessible($request, $pengeluaran);

        if ($pengeluaran->expense_type === 'REFERRAL') {
            return redirect()->route('accounting.referrals.edit', $pengeluaran);
        }

        if ($pengeluaran->journal()->exists()) {
            return redirect()
                ->route($this->routePrefix($request) . '.index')
                ->withErrors([
                    'expense' => 'Pengeluaran yang sudah dijurnal tidak dapat diubah.',
                ]);
        }

        return view('accounting.expenses.edit', array_merge(
            $this->formData(),
            [
                'expense' => $pengeluaran,
                'routePrefix' => $this->routePrefix($request),
            ]
        ));
    }

    public function update(Request $request, Expense $pengeluaran)
    {
        $this->ensureAccessible($request, $pengeluaran);

        if ($pengeluaran->expense_type === 'REFERRAL') {
            abort(404);
        }

        if ($pengeluaran->journal()->exists()) {
            return back()->withErrors([
                'expense' => 'Pengeluaran yang sudah dijurnal tidak dapat diubah.',
            ]);
        }

        $data = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request
                ->file('attachment')
                ->store('expenses', 'public');
        }

        unset($data['attachment']);
        $pengeluaran->update($data);

        return redirect()
            ->route($this->routePrefix($request) . '.index')
            ->with('success', 'Pengeluaran diperbarui.');
    }

    public function destroy(Request $request, Expense $pengeluaran)
    {
        $this->ensureAccessible($request, $pengeluaran);

        if ($pengeluaran->expense_type === 'REFERRAL') {
            return back()->with('error', 'Pengeluaran Referal dikelola melalui module Referal.');
        }

        if ($pengeluaran->journal()->exists()) {
            return back()->with('error', 'Pengeluaran yang sudah dijurnal tidak dapat dihapus.');
        }

        $pengeluaran->delete();

        return redirect()
            ->route($this->routePrefix($request) . '.index')
            ->with('success', 'Pengeluaran berhasil dihapus.');
    }

    public function generateJournal(
        Expense $expense,
        JournalService $service,
        Request $request
    ) {
        $service->fromExpense(
            $expense,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Jurnal pengeluaran berhasil dibuat.'
        );
    }

    private function formData(): array
    {
        return [
            'categories' => ExpenseCategory::where('is_active', true)
                ->where('code', '!=', 'REFERAL')
                ->get(),

            'accounts' => ChartAccount::where('is_active', true)
                ->where('type', 'EXPENSE')
                ->where('code', '!=', '5-1014')
                ->get(),

            'cashBanks' => CashBankAccount::where('is_active', true)
                ->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'chart_account_id' => ['required', 'exists:chart_accounts,id'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:CASH,TRANSFER,QRIS,CARD'],
            'cash_bank_account_id' => ['required', 'exists:cash_bank_accounts,id'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $category = ExpenseCategory::findOrFail(
            $data['expense_category_id']
        );

        if ($category->code === 'REFERAL') {
            throw ValidationException::withMessages([
                'expense_category_id' => 'Kategori Referal harus diinput melalui module Pengeluaran Referal.',
            ]);
        }

        return $data;
    }

    private function ensureAccessible(Request $request, Expense $expense): void
    {
        if (!$this->isCashier($request)) {
            return;
        }

        abort_unless(
            $expense->expense_type === 'GENERAL'
                && (int) $expense->created_by === (int) $request->user()->id,
            403
        );
    }

    private function isCashier(Request $request): bool
    {
        return $request->user()?->accountingRole() === 'KASIR';
    }

    private function routePrefix(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        return str_starts_with($routeName, 'cashier.')
            ? 'cashier.expenses'
            : 'accounting.pengeluaran';
    }
}
