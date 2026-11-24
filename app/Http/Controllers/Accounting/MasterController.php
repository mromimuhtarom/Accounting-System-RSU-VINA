<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\ChartAccount;
use App\Models\FinancialGroup;
use App\Models\Guarantor;
use App\Models\ServiceUnit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MasterController extends Controller
{
    private array $map = [
        'accounts' => ChartAccount::class,
        'units' => ServiceUnit::class,
        'guarantors' => Guarantor::class,
        'cash-banks' => CashBankAccount::class,
        'financial-groups' => FinancialGroup::class,
    ];

    public function index(Request $request, string $type)
    {
        $this->ensureCanManage($request, $type);

        if ($type === 'expense-categories') {
            return redirect()->route('accounting.master.index', ['accounts', 'account_type' => 'EXPENSE', 'q' => $request->query('q')]);
        }
        $model = $this->model($type);
        $sortColumn = in_array($type, ['accounts', 'financial-groups'], true)
            ? 'code'
            : 'name';

        $editingItem = null;

        if ($request->filled('edit')) {
            $editingItem = $model::findOrFail((int) $request->edit);
        }

        $query = $model::query();
        $coaSort = 'code';
        $coaDirection = 'asc';
        $hasFinancialGroup = $type === 'accounts';
        if ($hasFinancialGroup) {
            $query->with('financialGroup:id,name');
        }
        $search = '';
        if ($type === 'accounts') {
            $keyword = $request->query('q');
            $search = is_string($keyword) ? trim($keyword) : '';
            if ($search !== '') {
                $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(fn ($matches) => $matches
                    ->whereRaw("chart_accounts.code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("chart_accounts.name LIKE ? ESCAPE '!'", [$pattern]));
            }
        }
        if ($type === 'accounts' && $request->filled('group')) {
            $query->where('is_referral', $request->input('group') === 'referral');
        }
        $accountType = $request->query('account_type');
        $coaTypeFilter = $type === 'accounts' && in_array($accountType, ['REVENUE', 'EXPENSE'], true) ? $accountType : null;
        if ($coaTypeFilter !== null) {
            $query->where('chart_accounts.type', $coaTypeFilter);
        }

        if ($type === 'accounts') {
            $requestedSort = $request->query('sort');
            $coaSort = in_array($requestedSort, ['code', 'name', 'report_group', 'status'], true) ? $requestedSort : 'code';
            $coaDirection = $request->query('direction') === 'desc' && $coaSort === $requestedSort ? 'desc' : 'asc';
            if ($coaSort === 'report_group') {
                $query->leftJoin('financial_groups as fg', 'fg.id', '=', 'chart_accounts.financial_group_id')
                    ->select('chart_accounts.*')
                    ->selectRaw("COALESCE(fg.name, CASE WHEN chart_accounts.type = 'REVENUE' THEN ? ELSE ? END) AS report_group_sort", ['Mengikuti penjamin', '-'])
                    ->orderBy('report_group_sort', $coaDirection);
            } elseif ($coaSort === 'status') {
                $query->orderBy('chart_accounts.is_active', $coaDirection === 'asc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('chart_accounts.'.$coaSort, $coaDirection);
            }
            $query->orderBy('chart_accounts.id');
        } else {
            $query->orderBy($sortColumn)->orderBy('id');
        }

        $items = $query->paginate(10)->withQueryString();
        $lockedCoaIds = $type === 'accounts'
            ? array_keys($this->coaDeleteDependencies($items->getCollection()->modelKeys()))
            : [];

        return view('accounting.master.index', [
            'type' => $type,
            'items' => $items,
            'search' => $search,
            'coaTypeFilter' => $coaTypeFilter,
            'coaSort' => $coaSort,
            'coaDirection' => $coaDirection,
            'editingItem' => $editingItem,
            'lockedCoaIds' => $lockedCoaIds,
            'accounts' => in_array($type, ['accounts', 'units', 'cash-banks'], true)
                ? ChartAccount::orderBy('code')
                    ->when($type === 'units', fn ($accounts) => $accounts->where('type', 'REVENUE'))
                    ->get(['id', 'code', 'name', 'type'])
                : collect(),
            'financialGroups' => $hasFinancialGroup ? FinancialGroup::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'type'])->groupBy('type') : collect(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->ensureCanManage($request, $type);
        if ($type === 'accounts') {
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING', 'KASIR'], true), 403);
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
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING', 'KASIR'], true), 403);
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
            abort_unless(in_array(strtoupper(trim((string) ($request->user()?->accountingRole() ?? ''))), ['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING', 'KASIR'], true), 403);
        }

        $class = $this->model($type);
        $item = $class::findOrFail($id);

        $dependencies = $type === 'accounts'
            ? ($this->coaDeleteDependencies([$item->id])[$item->id] ?? [])
            : $this->dependencies($type, $item);

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
            'financial-groups' => [
                'COA' => fn () => $item->accounts()->exists(),
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
    private function coaDeleteDependencies(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $references = [
            ['receipts', 'chart_account_id', 'Penerimaan'],
            ['receipts', 'income_code', 'Kode COA historis pada Penerimaan', 'code'],
            ['expenses', 'chart_account_id', 'Pengeluaran'],
            ['journal_entry_lines', 'chart_account_id', 'Jurnal yang sudah ada'],
            ['service_units', 'revenue_account_id', 'Unit Pelayanan'],
            ['cash_bank_accounts', 'chart_account_id', 'Kas dan Bank'],
            ['chart_accounts', 'parent_id', 'COA Turunan'],
            ['insurance_monthly_claims', 'chart_account_id', 'Klaim dan Piutang Asuransi'],
            ['bpjs_claims', 'chart_account_id', 'Klaim BPJS lama'],
        ];
        $query = DB::table('chart_accounts as coa')->select('coa.id')->whereIn('coa.id', $accountIds);
        $labels = [];
        $columns = [];
        foreach ($references as $index => $ref) {
            [$table, $column, $label] = $ref;
            $columns[$table] ??= array_map(strtolower(...), Schema::getColumnListing($table));
            if (!in_array($column, $columns[$table], true)) {
                continue;
            }
            $alias = 'dependency_'.$index;
            $reference = DB::table($table)->selectRaw('1')->whereColumn($table.'.'.$column, 'coa.'.($ref[3] ?? 'id'));
            $query->selectRaw('EXISTS ('.$reference->toSql().') AS '.$alias);
            $labels[$alias] = $label;
        }

        $dependencies = [];
        foreach ($query->get() as $account) {
            foreach ($labels as $alias => $label) {
                if ($account->{$alias}) {
                    $dependencies[$account->id][] = $label;
                }
            }
        }
        return $dependencies;
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
                'financial_group_id' => [
                    'nullable',
                    Rule::prohibitedIf(!in_array($request->input('type'), ['REVENUE', 'EXPENSE'], true)),
                    Rule::exists('financial_groups', 'id')->where('type', $request->input('type') === 'REVENUE' ? 'INCOME' : 'EXPENSE'),
                ],
                'is_referral' => ['sometimes', 'boolean', function ($attribute, $value, $fail) use ($request, $id) {
                    if ($value && $request->input('type') !== 'EXPENSE') $fail('COA Referal harus bertipe EXPENSE.');
                    $account = $id ? ChartAccount::find($id) : null;
                    if ($account && (bool) $value !== $account->is_referral && $account->expenses()->exists()) {
                        $fail('Kelompok COA yang sudah digunakan tidak dapat diubah.');
                    }
                }],
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
