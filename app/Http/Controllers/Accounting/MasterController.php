<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\ChartAccount;
use App\Models\ExpenseCategory;
use App\Models\FinancialGroup;
use App\Models\Guarantor;
use App\Models\ServiceUnit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterController extends Controller
{
    private array $map = [
        'accounts' => ChartAccount::class,
        'units' => ServiceUnit::class,
        'guarantors' => Guarantor::class,
        'cash-banks' => CashBankAccount::class,
        'expense-categories' => ExpenseCategory::class,
        'financial-groups' => FinancialGroup::class,
    ];

    public function index(Request $request, string $type)
    {
        $this->ensureCanManage($request, $type);

        $model = $this->model($type);
        $sortColumn = in_array($type, ['accounts', 'financial-groups'], true)
            ? 'code'
            : 'name';

        $editingItem = null;

        if ($request->filled('edit')) {
            $editingItem = $model::findOrFail((int) $request->edit);
        }

        $lockedCoaIds = [];
        if ($type === 'accounts') {
            foreach (ChartAccount::query()->select(['id', 'code'])->get() as $account) {
                if ($this->coaDeleteDependencies($account) !== []) {
                    $lockedCoaIds[] = (int) $account->id;
                }
            }
        }

        return view('accounting.master.index', [
            'type' => $type,
            'items' => $model::orderBy($sortColumn)
                ->paginate(10)
                ->withQueryString(),
            'editingItem' => $editingItem,
            'lockedCoaIds' => $lockedCoaIds,
            'accounts' => ChartAccount::orderBy('code')->get(),
            'financialGroups' => FinancialGroup::orderBy('type')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->ensureCanManage($request, $type);
        if ($type === 'accounts') {
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING'], true), 403);
        }

        $class = $this->model($type);
        $class::create($this->validateData($request, $type));

        return redirect()
            ->route('accounting.master.index', $type)
            ->with('success', 'Master data berhasil ditambahkan.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->ensureCanManage($request, $type);
        if ($type === 'accounts') {
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING'], true), 403);
        }

        $class = $this->model($type);
        $item = $class::findOrFail($id);
        $item->update($this->validateData($request, $type, $id));

        return redirect()
            ->route('accounting.master.index', $type)
            ->with(
                'success',
                'Master data diperbarui. Data terkait otomatis menggunakan data master terbaru.'
            );
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $this->ensureCanManage($request, $type);
        if ($type === 'accounts') {
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING'], true), 403);
        }

        $class = $this->model($type);
        $item = $class::findOrFail($id);

        $dependencies = $this->dependencies($type, $item);
        if ($type === 'accounts') {
            $dependencies = array_values(array_unique(array_merge($dependencies, $this->coaDeleteDependencies($item))));
        }

        if ($dependencies !== []) {
            return back()->with(
                'error',
                'Data tidak dapat dihapus karena masih terhubung dengan: '
                . implode(', ', $dependencies)
                . '. Gunakan status Inactive bila data tidak ingin digunakan lagi.'
            );
        }

        try {
            $item->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                'Data tidak dapat dihapus karena masih digunakan oleh data lain. Gunakan status Inactive.'
            );
        }

        return redirect()
            ->route('accounting.master.index', $type)
            ->with('success', 'Master data berhasil dihapus.');
    }

    private function dependencies(string $type, object $item): array
    {
        $used = [];

        $checks = match ($type) {
            'accounts' => [
                'COA turunan' => fn () => $item->children()->exists(),
                'Unit Pelayanan' => fn () => $item->serviceUnits()->exists(),
                'Kas & Bank' => fn () => $item->cashBankAccounts()->exists(),
                'Kategori Pengeluaran' => fn () => $item->expenseCategories()->exists(),
                'Pemasukan' => fn () => $item->receipts()->exists(),
                'Pengeluaran' => fn () => $item->expenses()->exists(),
                'Jurnal' => fn () => $item->journalLines()->exists(),
            ],
            'units' => [
                'Penerimaan' => fn () => $item->receipts()->exists(),
            ],
            'guarantors' => [
                'Penerimaan' => fn () => $item->receipts()->exists(),
            ],
            'cash-banks' => [
                'Penerimaan' => fn () => $item->receipts()->exists(),
                'Pengeluaran' => fn () => $item->expenses()->exists(),
            ],
            'expense-categories' => [
                'Pengeluaran' => fn () => $item->expenses()->exists(),
            ],
            'financial-groups' => [
                'Penjamin' => fn () => $item->guarantors()->exists(),
                'Kategori Pengeluaran' => fn () => $item->expenseCategories()->exists(),
            ],
            default => [],
        };

        foreach ($checks as $label => $check) {
            if ($check()) {
                $used[] = $label;
            }
        }

        return $used;
    }

    /** Guard hard deletion, including historical/cancelled transactions and legacy income_code. */
    private function coaDeleteDependencies(object $account): array
    {
        $id = (int) $account->id;
        $code = (string) $account->code;
        $references = [
            ['receipts', 'chart_account_id', 'Penerimaan'],
            ['receipts', 'income_code', 'Kode COA historis pada Penerimaan', $code],
            ['expenses', 'chart_account_id', 'Pengeluaran'],
            ['journal_entry_lines', 'chart_account_id', 'Jurnal yang sudah ada'],
            ['service_units', 'revenue_account_id', 'Unit Pelayanan'],
            ['cash_bank_accounts', 'chart_account_id', 'Kas dan Bank'],
            ['expense_categories', 'expense_account_id', 'Kategori Pengeluaran'],
            ['chart_accounts', 'parent_id', 'COA Turunan'],
        ];
        $dependencies = [];
        foreach ($references as $ref) {
            [$table, $column, $label] = $ref;
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)
                || !\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                continue;
            }
            $value = array_key_exists(3, $ref) ? $ref[3] : $id;
            if (\Illuminate\Support\Facades\DB::table($table)->where($column, $value)->exists()) {
                $dependencies[] = $label;
            }
        }
        // The monthly insurance claim ledger references guarantors, NOT chart_accounts.
        // Do not invent a COA/claim link; when present, guard the actual direct reference.
        if (\Illuminate\Support\Facades\Schema::hasTable('insurance_monthly_claims')
            && \Illuminate\Support\Facades\Schema::hasColumn('insurance_monthly_claims', 'chart_account_id')
            && \Illuminate\Support\Facades\DB::table('insurance_monthly_claims')->where('chart_account_id', $id)->exists()) {
            $dependencies[] = 'Klaim dan Piutang Asuransi';
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('bpjs_claims')
            && \Illuminate\Support\Facades\Schema::hasColumn('bpjs_claims', 'chart_account_id')
            && \Illuminate\Support\Facades\DB::table('bpjs_claims')->where('chart_account_id', $id)->exists()) {
            $dependencies[] = 'Klaim BPJS lama';
        }
        return array_values(array_unique($dependencies));
    }

    private function ensureCanManage(Request $request, string $type): void
    {
        $role = strtoupper(trim((string) ($request->user()?->accountingRole() ?? '')));

        abort_unless(
            ($role === 'ASURANSI' && $type === 'guarantors') || in_array($role, ['KASIR', 'ACCOUNTING', 'IT', 'ADMIN_ACCOUNTING','KEPALA_KEUANGAN'], true),
            403
        );
    }

    private function model(string $type)
    {
        abort_unless(isset($this->map[$type]), 404);

        return $this->map[$type];
    }

    private function validateData(
        Request $request,
        string $type,
        ?int $id = null
    ): array {
        return match ($type) {
            'accounts' => $request->validate([
                'code' => [
                    'required',
                    'max:30',
                    Rule::unique('chart_accounts', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'type' => ['required', 'in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE'],
                'parent_id' => [
                    'nullable',
                    'exists:chart_accounts,id',
                    Rule::notIn(array_filter([$id])),
                ],
                'normal_balance' => ['required', 'in:DEBIT,CREDIT'],
                'is_active' => ['required', 'boolean'],
            ]),
            'units' => $request->validate([
                'code' => [
                    'required',
                    'max:30',
                    Rule::unique('service_units', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'revenue_account_id' => ['nullable', 'exists:chart_accounts,id'],
                'is_active' => ['required', 'boolean'],
            ]),
            'guarantors' => $request->validate([
                'code' => [
                    'required',
                    'max:30',
                    Rule::unique('guarantors', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'category' => [
                    'required',
                    'in:SELF_PAY,BPJS,PRIVATE_INSURANCE,CORPORATE,OTHER',
                ],
                'financial_group_id' => ['required', 'exists:financial_groups,id'],
                'is_bpjs' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
            ]),
            'cash-banks' => $request->validate([
                'code' => [
                    'required',
                    'max:30',
                    Rule::unique('cash_bank_accounts', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'type' => ['required', 'in:CASH,BANK'],
                'bank_name' => ['nullable', 'max:150'],
                'account_number' => ['nullable', 'max:100'],
                'chart_account_id' => ['required', 'exists:chart_accounts,id'],
                'is_active' => ['required', 'boolean'],
            ]),
            'expense-categories' => $request->validate([
                'code' => [
                    'required',
                    'max:30',
                    Rule::unique('expense_categories', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'financial_group_id' => ['required', 'exists:financial_groups,id'],
                'expense_account_id' => ['required', 'exists:chart_accounts,id'],
                'is_active' => ['required', 'boolean'],
            ]),
            'financial-groups' => $request->validate([
                'code' => [
                    'required',
                    'max:40',
                    Rule::unique('financial_groups', 'code')->ignore($id),
                ],
                'name' => ['required', 'max:150'],
                'type' => ['required', 'in:INCOME,EXPENSE'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ]),
        };
    }
}
