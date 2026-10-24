<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\InsuranceMonthlyClaim;
use App\Models\FinancialGroup;
use App\Models\JournalLine;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $receipts = Receipt::with(['account', 'serviceUnit', 'guarantor.financialGroup'])
            ->where('status', 'ACTIVE')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $expenses = Expense::with(['category.financialGroup'])
            ->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $incomeGroups = FinancialGroup::where('type', 'INCOME')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $expenseGroups = FinancialGroup::where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $income = $this->buildMonthlyIncome($incomeGroups, $receipts);
        $expense = $this->buildMonthlyExpense($expenseGroups, $expenses);
        $totalIncome = (float) $income->sum('total');
        $totalExpense = (float) $expense->sum('total');
        $surplus = $totalIncome - $totalExpense;

        // Tagihan klaim terpisah dari penerimaan; tidak ditambahkan ke totalIncome.
        $claimRows = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('insurance_monthly_claims')) {
            $claimRows = InsuranceMonthlyClaim::with('guarantor')
                ->where('year', $year)->where('month', $month)->get();
        }
        $claimsByGuarantor = $claimRows->groupBy(fn ($row) => $row->guarantor?->name ?? 'Penjamin tidak ditemukan')
            ->map(function ($items, $name) {
                $byService = [];
                foreach (['RAWAT_JALAN', 'RAWAT_INAP'] as $service) {
                    $set = $items->where('service_type', $service);
                    $byService[$service] = [
                        'count' => $set->count(),
                        'submitted' => (float) $set->sum('claimed_amount'),
                        'approved' => (float) $set->sum('approved_amount'),
                        'paid' => (float) $set->sum('paid_amount'),
                    ];
                }
                return ['name' => $name, 'services' => $byService,
                    'total' => array_sum(array_column($byService, 'submitted'))];
            })->values();

        return view('reports.monthly', compact(
            'month', 'year', 'start', 'income', 'expense', 'totalIncome', 'totalExpense', 'surplus', 'claimsByGuarantor'
        ));
    }

    public function yearly(Request $request)
    {
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $start = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $end = $start->endOfYear();

        $receipts = Receipt::with(['account', 'serviceUnit', 'guarantor.financialGroup'])
            ->where('status', 'ACTIVE')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $expenses = Expense::with(['category.financialGroup'])
            ->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $incomeGroups = FinancialGroup::where('type', 'INCOME')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $expenseGroups = FinancialGroup::where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $income = $this->buildYearlyIncome($incomeGroups, $receipts);
        $expense = $this->buildYearlyExpense($expenseGroups, $expenses);

        $incomeMonths = $this->sumGroupMonths($income);
        $expenseMonths = $this->sumGroupMonths($expense);
        $surplusMonths = [];
        for ($m = 1; $m <= 12; $m++) {
            $surplusMonths[$m] = ($incomeMonths[$m] ?? 0) - ($expenseMonths[$m] ?? 0);
        }

        $totalIncome = array_sum($incomeMonths);
        $totalExpense = array_sum($expenseMonths);
        $surplus = $totalIncome - $totalExpense;

        return view('reports.yearly', compact(
            'year', 'income', 'expense', 'incomeMonths', 'expenseMonths', 'surplusMonths',
            'totalIncome', 'totalExpense', 'surplus'
        ));
    }

    /** Rincian kelompok Laporan Bulanan; tidak membuat/mengubah jurnal. */
    public function monthlyDetail(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'kind' => ['required', 'in:income,expense'],
            'group' => ['nullable', 'string', 'max:60'],
        ]);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $kind = $validated['kind'];
        $groupCode = $validated['group'] ?? 'all';
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth()->toDateString();
        $end = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();
        $isIncome = $kind === 'income';
        $groups = FinancialGroup::where('type', $isIncome ? 'INCOME' : 'EXPENSE')
            ->where('is_active', true)->orderBy('sort_order')->get();
        $selectedGroup = null;
        if ($groupCode !== 'all') {
            $selectedGroup = $groups->firstWhere('code', $groupCode);
            abort_unless($selectedGroup, 404, 'Kelompok laporan tidak ditemukan.');
        }
        $source = $isIncome
            ? Receipt::with(['account', 'guarantor', 'serviceUnit', 'cashier'])
                ->where('status', 'ACTIVE')->whereBetween('transaction_date', [$start, $end])->get()
            : Expense::with(['category.financialGroup', 'account'])
                ->where('status', 'ACTIVE')->whereBetween('expense_date', [$start, $end])->get();
        $rows = $source->filter(function ($record) use ($isIncome, $selectedGroup, $groups) {
            if ($isIncome) {
                return $selectedGroup
                    ? $this->receiptBelongsToIncomeGroup($record, $selectedGroup)
                    : $groups->contains(fn ($group) => $this->receiptBelongsToIncomeGroup($record, $group));
            }
            return $selectedGroup
                ? (int) ($record->category?->financial_group_id ?? 0) === (int) $selectedGroup->id
                : $groups->contains(fn ($group) => (int) ($record->category?->financial_group_id ?? 0) === (int) $group->id);
        })->sortByDesc(fn ($record) => ($isIncome ? $record->transaction_date : $record->expense_date) . sprintf('%012d', $record->id))->values();
        $total = (float) $rows->sum('amount');
        $chartRows = $rows->groupBy(function ($record) use ($isIncome, $selectedGroup, $groups) {
            if (!$selectedGroup) {
                if (!$isIncome) return $record->category?->financialGroup?->name ?? 'Tanpa kelompok';
                return $groups->first(fn ($group) => $this->receiptBelongsToIncomeGroup($record, $group))?->name ?? 'Tanpa kelompok';
            }
            if (!$isIncome) return $record->category?->name ?? 'Tanpa kategori';
            if (in_array($selectedGroup->code, ['REV_BPJS', 'REV_GUARANTOR'], true)) {
                $type = strtoupper(trim((string) $record->service_type));
                $service = in_array($type, ['RAWAT_JALAN', 'RJ', 'BPJS_RJ'], true) ? 'Rawat Jalan'
                    : (in_array($type, ['RAWAT_INAP', 'RI', 'BPJS_RI'], true) ? 'Rawat Inap' : 'Belum diklasifikasi');
                return ($record->guarantor?->name ?? 'Penjamin tidak diketahui') . ' — ' . $service;
            }
            return $this->incomeDetailLabel($record, $selectedGroup->code);
        })->map(fn ($items, $label) => [
            'label' => (string) $label, 'amount' => (float) $items->sum('amount'), 'count' => $items->count(),
        ])->sortByDesc('amount')->values();
        $paged = $this->paginateCollection($rows, $request, 30);
        $title = $selectedGroup?->name ?? ($isIncome ? 'Semua Penerimaan' : 'Semua Pengeluaran');
        return view('reports.monthly_detail', compact('month','year','kind','title','selectedGroup','rows','paged','chartRows','total','isIncome'));
    }

    public function income(Request $r)
    {
        $q = Receipt::with(['account', 'cashier'])->where('status', 'ACTIVE');
        $this->dateFilter($q, $r, 'transaction_date');
        $total = (clone $q)->sum('amount');
        $rows = $q->orderBy('transaction_date')->paginate(10)->withQueryString();

        return view('reports.income', compact('rows', 'total'));
    }

    public function expense(Request $r)
    {
        $q = Expense::with(['category', 'account', 'cashBankAccount'])->where('status', 'ACTIVE');
        $this->dateFilter($q, $r, 'expense_date');
        $total = (clone $q)->sum('amount');
        $rows = $q->orderBy('expense_date')->paginate(10)->withQueryString();

        return view('reports.expense', compact('rows', 'total'));
    }

    public function ledger(Request $r)
    {
        $accounts = ChartAccount::orderBy('code')->get();
        $lines = collect();
        $account = null;

        if ($r->filled('account_id')) {
            $account = ChartAccount::findOrFail($r->account_id);
            $q = JournalLine::with('account')
                ->where('chart_account_id', $account->id)
                ->whereHas('journal', function ($j) use ($r) {
                    $j->where('status', 'POSTED');
                    if ($r->filled('date_from')) $j->whereDate('journal_date', '>=', $r->date_from);
                    if ($r->filled('date_to')) $j->whereDate('journal_date', '<=', $r->date_to);
                })
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.journal_date')
                ->orderBy('journal_entry_lines.id')
                ->select('journal_entry_lines.*', 'journal_entries.journal_no', 'journal_entries.journal_date', 'journal_entries.reference');

            $balance = 0;
            $lines = $q->get()->map(function ($line) use (&$balance, $account) {
                $delta = $account->normal_balance === 'DEBIT'
                    ? (float) $line->debit - (float) $line->credit
                    : (float) $line->credit - (float) $line->debit;
                $balance += $delta;
                $line->running_balance = $balance;
                return $line;
            });

            $lines = $this->paginateCollection($lines, $r);
        }

        return view('reports.ledger', compact('accounts', 'account', 'lines'));
    }

    public function trialBalance(Request $r)
    {
        $q = ChartAccount::query()->orderBy('code')->get();
        $rows = $q->map(function ($account) use ($r) {
            $lines = JournalLine::where('chart_account_id', $account->id)
                ->whereHas('journal', function ($j) use ($r) {
                    $j->where('status', 'POSTED');
                    if ($r->filled('date_from')) $j->whereDate('journal_date', '>=', $r->date_from);
                    if ($r->filled('date_to')) $j->whereDate('journal_date', '<=', $r->date_to);
                })
                ->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')
                ->first();

            $account->debit = (float) $lines->debit;
            $account->credit = (float) $lines->credit;
            $account->balance = $account->normal_balance === 'DEBIT'
                ? $account->debit - $account->credit
                : $account->credit - $account->debit;
            return $account;
        })->filter(fn ($x) => $x->debit != 0 || $x->credit != 0)->values();

        $totals = [
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
        ];

        $rows = $this->paginateCollection($rows, $r);

        return view('reports.trial_balance', compact('rows', 'totals'));
    }

    private function buildMonthlyIncome(Collection $groups, Collection $receipts): Collection
    {
        return $groups->map(function ($group) use ($receipts) {
            $groupRows = $receipts->filter(
                fn ($receipt) => $this->receiptBelongsToIncomeGroup($receipt, $group)
            );

            $details = $groupRows
                ->groupBy(function ($receipt) use ($group) {
                    if (in_array($group->code, ['REV_BPJS', 'REV_GUARANTOR'], true)) {
                        $penjamin = $receipt->guarantor?->name ?? 'Penjamin tidak diketahui';
                        $service = strtoupper(trim((string) $receipt->service_type));
                        $type = in_array($service, ['RAWAT_JALAN', 'RJ', 'BPJS_RJ'], true) ? 'Rawat Jalan'
                            : (in_array($service, ['RAWAT_INAP', 'RI', 'BPJS_RI'], true) ? 'Rawat Inap' : 'Belum diklasifikasi');
                        return $penjamin . ' — ' . $type;
                    }
                    return $this->incomeDetailLabel($receipt, $group->code);
                })
                ->map(fn ($items, $label) => ['label' => $label, 'amount' => (float) $items->sum('amount'), 'count' => $items->count()])
                ->sortBy('label')->values();

            return [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'details' => $details,
                'total' => (float) $details->sum('amount'),
            ];
        });
    }

    private function buildMonthlyExpense(Collection $groups, Collection $expenses): Collection
    {
        return $groups->map(function ($group) use ($expenses) {
            $groupRows = $expenses->filter(
                fn ($expense) => (int) ($expense->category?->financial_group_id ?? 0) === (int) $group->id
            );

            $details = $groupRows
                ->groupBy(fn ($expense) => $expense->category?->name ?? 'Tanpa Kategori')
                ->map(fn ($items, $label) => ['label' => $label, 'amount' => (float) $items->sum('amount')])
                ->sortBy('label')
                ->values();

            return [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'details' => $details,
                'total' => (float) $details->sum('amount'),
            ];
        });
    }

    private function buildYearlyIncome(Collection $groups, Collection $receipts): Collection
    {
        return $groups->map(function ($group) use ($receipts) {
            $groupRows = $receipts->filter(
                fn ($receipt) => $this->receiptBelongsToIncomeGroup($receipt, $group)
            );

            $details = $groupRows
                ->groupBy(function ($receipt) use ($group) {
                    if (in_array($group->code, ['REV_BPJS', 'REV_GUARANTOR'], true)) {
                        $service = strtoupper(trim((string) $receipt->service_type));
                        $type = in_array($service, ['RAWAT_JALAN', 'RJ', 'BPJS_RJ'], true) ? 'Rawat Jalan' : (in_array($service, ['RAWAT_INAP', 'RI', 'BPJS_RI'], true) ? 'Rawat Inap' : 'Belum diklasifikasi');
                        return ($receipt->guarantor?->name ?? 'Penjamin tidak diketahui') . ' — ' . $type;
                    }
                    return $this->incomeDetailLabel($receipt, $group->code);
                })
                ->map(function ($items, $label) {
                    $months = array_fill(1, 12, 0.0);
                    foreach ($items as $item) {
                        $month = (int) $item->transaction_date->month;
                        $months[$month] += (float) $item->amount;
                    }
                    return ['label' => $label, 'months' => $months, 'total' => array_sum($months)];
                })
                ->sortBy('label')
                ->values();

            $groupMonths = array_fill(1, 12, 0.0);
            foreach ($details as $detail) {
                foreach ($detail['months'] as $month => $amount) {
                    $groupMonths[$month] += $amount;
                }
            }

            return [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'details' => $details,
                'months' => $groupMonths,
                'total' => array_sum($groupMonths),
            ];
        });
    }

    private function buildYearlyExpense(Collection $groups, Collection $expenses): Collection
    {
        return $groups->map(function ($group) use ($expenses) {
            $groupRows = $expenses->filter(
                fn ($expense) => (int) ($expense->category?->financial_group_id ?? 0) === (int) $group->id
            );

            $details = $groupRows
                ->groupBy(fn ($expense) => $expense->category?->name ?? 'Tanpa Kategori')
                ->map(function ($items, $label) {
                    $months = array_fill(1, 12, 0.0);
                    foreach ($items as $item) {
                        $month = (int) $item->expense_date->month;
                        $months[$month] += (float) $item->amount;
                    }
                    return ['label' => $label, 'months' => $months, 'total' => array_sum($months)];
                })
                ->sortBy('label')
                ->values();

            $groupMonths = array_fill(1, 12, 0.0);
            foreach ($details as $detail) {
                foreach ($detail['months'] as $month => $amount) {
                    $groupMonths[$month] += $amount;
                }
            }

            return [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'details' => $details,
                'months' => $groupMonths,
                'total' => array_sum($groupMonths),
            ];
        });
    }

    private function sumGroupMonths(Collection $groups): array
    {
        $months = array_fill(1, 12, 0.0);
        foreach ($groups as $group) {
            foreach ($group['months'] as $month => $amount) {
                $months[$month] += $amount;
            }
        }
        return $months;
    }

    private function receiptBelongsToIncomeGroup(Receipt $receipt, object $group): bool
    {
        // Prioritaskan jenis PENJAMIN, karena data historis BPJS punya COA
        // Pendapatan Rawat Inap/Poli dan sebelumnya salah masuk REV_SERVICE.
        $guarantor = $receipt->guarantor;
        if ($guarantor && ($guarantor->is_bpjs || $guarantor->category === 'BPJS')) {
            return $group->code === 'REV_BPJS';
        }
        if ($guarantor && in_array($guarantor->category, ['PRIVATE_INSURANCE','CORPORATE','OTHER'], true)) {
            return $group->code === 'REV_GUARANTOR';
        }
        return $group->code === 'REV_SERVICE';
    }

    private function incomeDetailLabel(Receipt $receipt, string $groupCode): string
    {
        if ($receipt->chart_account_id) {
            return $receipt->account
                ? $receipt->account->code . ' - ' . $receipt->account->name
                : 'COA Tidak Ditemukan';
        }

        if (
            trim((string) $receipt->income_code) !== ''
            || (!$receipt->service_unit_id && !$receipt->guarantor_id)
        ) {
            return trim((string) $receipt->income_code) !== ''
                ? $receipt->income_code
                : 'Tanpa Kode';
        }

        if ($groupCode === 'REV_SERVICE') {
            return $receipt->serviceUnit?->name ?? 'Tanpa Unit Pelayanan';
        }

        if ($groupCode === 'REV_BPJS') {
            $serviceType = trim((string) $receipt->service_type);

            if ($serviceType !== '') {
                return str_starts_with(strtoupper($serviceType), 'BPJS')
                    ? $serviceType
                    : 'BPJS ' . $serviceType;
            }

            return $receipt->serviceUnit?->name ?? 'BPJS Kesehatan';
        }

        return $receipt->guarantor?->name ?? 'Penjamin Lainnya';
    }

    private function dateFilter($q, Request $r, string $column): void
    {
        if ($r->filled('date_from')) $q->whereDate($column, '>=', $r->date_from);
        if ($r->filled('date_to')) $q->whereDate($column, '<=', $r->date_to);
    }


    private function paginateCollection(
        Collection $items,
        Request $request,
        int $perPage = 10
    ): LengthAwarePaginator {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $items
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
