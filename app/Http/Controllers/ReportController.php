<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\InsuranceMonthlyClaim;
use App\Models\FinancialGroup;
use App\Models\JournalLine;
use App\Models\Receipt;
use App\Services\CoaReportGroups;
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

        $receipts = Receipt::with(['account.financialGroup', 'serviceUnit:id,name', 'guarantor:id,name,category,is_bpjs'])
            ->where('status', 'ACTIVE')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get(['chart_account_id', 'service_unit_id', 'guarantor_id', 'income_code', 'service_type', 'transaction_date', 'amount']);

        $expenses = Expense::with('account:id,name,financial_group_id')
            ->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $incomeGroups = FinancialGroup::where('type', 'INCOME')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $expenseGroups = FinancialGroup::where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $expenseGroups = $this->includeUnmappedExpenseGroup($expenseGroups, $expenses);
        $referralSummary = app(\App\Services\ReferralSummary::class)->rows($start->toDateString(), $end->toDateString());
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

        $assetReport=\Illuminate\Support\Facades\Gate::allows('viewAny',\App\Models\Asset::class) ? app(\App\Services\AssetReporting::class)->summary($year,$month) : null;
        return view('reports.monthly', compact(
            'month', 'year', 'start', 'income', 'expense', 'totalIncome', 'totalExpense', 'surplus', 'claimsByGuarantor', 'referralSummary','assetReport'
        ));
    }

    public function yearly(Request $request)
    {
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $start = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $end = $start->endOfYear();

        $receipts = Receipt::with(['account.financialGroup', 'serviceUnit:id,name', 'guarantor:id,name,category,is_bpjs'])
            ->where('status', 'ACTIVE')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get(['chart_account_id', 'service_unit_id', 'guarantor_id', 'income_code', 'service_type', 'transaction_date', 'amount']);

        $expenses = Expense::with('account:id,name,financial_group_id')
            ->where('status', 'ACTIVE')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->select('chart_account_id')
            ->selectRaw('MIN(expense_date) AS expense_date, SUM(amount) AS amount')
            ->groupBy('chart_account_id')
            ->groupByRaw('SUBSTR(expense_date, 1, 7)')
            ->get();

        $incomeGroups = FinancialGroup::where('type', 'INCOME')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $expenseGroups = FinancialGroup::where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $expenseGroups = $this->includeUnmappedExpenseGroup($expenseGroups, $expenses);
        $referralSummary = app(\App\Services\ReferralSummary::class)->rows($start->toDateString(), $end->toDateString());
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

        $assetReport=\Illuminate\Support\Facades\Gate::allows('viewAny',\App\Models\Asset::class) ? app(\App\Services\AssetReporting::class)->summary($year) : null;
        return view('reports.yearly', compact(
            'year', 'income', 'expense', 'incomeMonths', 'expenseMonths', 'surplusMonths',
            'totalIncome', 'totalExpense', 'surplus', 'referralSummary','assetReport'
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
        $source = $isIncome
            ? Receipt::with(['account.financialGroup', 'guarantor', 'serviceUnit', 'cashier'])
                ->where('status', 'ACTIVE')->whereBetween('transaction_date', [$start, $end])->get()
            : Expense::with(['account.financialGroup'])
                ->where('status', 'ACTIVE')->whereBetween('expense_date', [$start, $end])->get();
        if ($isIncome) {
            $incomeRows = $this->incomeRowsByGroup($groups, $source);
        } else {
            $groups = $this->includeUnmappedExpenseGroup($groups, $source);
        }
        $selectedGroup = $groupCode === 'all' ? null : $groups->firstWhere('code', $groupCode === 'REFERRAL_UNMAPPED' ? 'EXP_UNMAPPED' : $groupCode);
        abort_if($groupCode !== 'all' && !$selectedGroup, 404, 'Kelompok laporan tidak ditemukan.');
        $groupIds = $groups->keyBy('id');
        $rows = $isIncome ? ($selectedGroup ? $incomeRows->get($selectedGroup->code, collect()) : $source)
            : $source->filter(function ($record) use ($selectedGroup, $groupIds) {
            $id = (int) ($record->account?->financial_group_id ?? 0);
            $id = $groupIds->has($id) ? $id : -1;
            return !$selectedGroup || $id === (int) $selectedGroup->id;
        });
        $rows = $rows->sortByDesc(fn ($record) => ($isIncome ? $record->transaction_date : $record->expense_date) . sprintf('%012d', $record->id))->values();
        $total = (float) $rows->sum('amount');
        $groupNames = $groups->pluck('name', 'code');
        $chartRows = $rows->groupBy(function ($record) use ($isIncome, $selectedGroup, $groupNames, $groupIds) {
            if (!$selectedGroup) {
                if (!$isIncome) return $groupIds->get((int) $record->account?->financial_group_id)?->name ?? 'Pengeluaran tanpa grup laporan';
                return $groupNames->get(CoaReportGroups::receiptCode($record), 'Pemasukan tanpa grup laporan');
            }
            if (!$isIncome) return $record->account?->name ?? 'Tanpa COA';
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
        $q = Receipt::with(['account.financialGroup', 'guarantor:id,category,is_bpjs', 'cashier:id,name'])->where('status', 'ACTIVE');
        $this->dateFilter($q, $r, 'transaction_date');
        $total = (clone $q)->sum('amount');
        $rows = $q->orderBy('transaction_date')->paginate(10)->withQueryString();

        $incomeGroupNames = FinancialGroup::where('type', 'INCOME')->where('is_active', true)->pluck('name', 'code');
        return view('reports.income', compact('rows', 'total', 'incomeGroupNames'));
    }

    public function expense(Request $r)
    {
        $q = Expense::with('account.financialGroup')->where('status', 'ACTIVE');
        $this->dateFilter($q, $r, 'expense_date');
        $total = (clone $q)->sum('amount');
        $rows = $q->orderBy('expense_date')->paginate(10)->withQueryString();

        return view('reports.expense', compact('rows', 'total'));
    }

    // public function ledger(Request $r)
    // {
    //     $accounts = ChartAccount::orderBy('code')->get();
    //     $lines = collect();
    //     $account = null;

    //     if ($r->filled('account_id')) {
    //         $account = ChartAccount::findOrFail($r->account_id);
    //         $q = JournalLine::with('account')
    //             ->where('chart_account_id', $account->id)
    //             ->whereHas('journal', function ($j) use ($r) {
    //                 $j->where('status', 'POSTED');
    //                 if ($r->filled('date_from')) $j->whereDate('journal_date', '>=', $r->date_from);
    //                 if ($r->filled('date_to')) $j->whereDate('journal_date', '<=', $r->date_to);
    //             })
    //             ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
    //             ->orderBy('journal_entries.journal_date')
    //             ->orderBy('journal_entry_lines.id')
    //             ->select('journal_entry_lines.*', 'journal_entries.journal_no', 'journal_entries.journal_date', 'journal_entries.reference');

    //         $balance = 0;
    //         $lines = $q->get()->map(function ($line) use (&$balance, $account) {
    //             $delta = $account->normal_balance === 'DEBIT'
    //                 ? (float) $line->debit - (float) $line->credit
    //                 : (float) $line->credit - (float) $line->debit;
    //             $balance += $delta;
    //             $line->running_balance = $balance;
    //             return $line;
    //         });

    //         $lines = $this->paginateCollection($lines, $r);
    //     }

    //     return view('reports.ledger', compact('accounts', 'account', 'lines'));
    // }

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
        $receiptsByGroup = $this->incomeRowsByGroup($groups, $receipts);
        return $groups->map(function ($group) use ($receiptsByGroup) {
            $groupRows = $receiptsByGroup->get($group->code, collect());

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

    private function includeUnmappedExpenseGroup(Collection $groups, Collection $expenses): Collection
    {
        $groupIds = $groups->keyBy('id');
        if ($expenses->contains(fn ($e) => !$groupIds->has((int) ($e->account?->financial_group_id ?? 0)))) {
            $groups->push((object) ['id'=>-1, 'code'=>'EXP_UNMAPPED', 'name'=>'Pengeluaran tanpa grup laporan']);
        }
        return $groups;
    }

    private function buildMonthlyExpense(Collection $groups, Collection $expenses): Collection
    {
        $expensesByGroup = $this->expensesByGroup($groups, $expenses);
        return $groups->map(function ($group) use ($expensesByGroup) {
            $groupRows = $expensesByGroup->get((int) $group->id, collect());

            $details = $groupRows
                ->groupBy(fn ($expense) => $expense->account?->name ?? 'Tanpa COA')
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
        $receiptsByGroup = $this->incomeRowsByGroup($groups, $receipts);
        return $groups->map(function ($group) use ($receiptsByGroup) {
            $groupRows = $receiptsByGroup->get($group->code, collect());

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
        $expensesByGroup = $this->expensesByGroup($groups, $expenses);
        return $groups->map(function ($group) use ($expensesByGroup) {
            $groupRows = $expensesByGroup->get((int) $group->id, collect());

            $details = $groupRows
                ->groupBy(fn ($expense) => $expense->account?->name ?? 'Tanpa COA')
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

    private function expensesByGroup(Collection $groups, Collection $expenses): Collection
    {
        $groupIds = $groups->where('id', '!=', -1)->keyBy('id');
        return $expenses->groupBy(function ($expense) use ($groupIds) {
            $id = (int) ($expense->account?->financial_group_id ?? 0);
            return $groupIds->has($id) ? $id : -1;
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

    private function incomeRowsByGroup(Collection $groups, Collection $receipts): Collection
    {
        $codes = $groups->keyBy('code');
        $rows = $receipts->groupBy(function (Receipt $receipt) use ($codes) {
            $code = CoaReportGroups::receiptCode($receipt);
            return $codes->has($code) ? $code : CoaReportGroups::UNMAPPED_INCOME;
        });
        if ($rows->has(CoaReportGroups::UNMAPPED_INCOME) && !$codes->has(CoaReportGroups::UNMAPPED_INCOME)) {
            $groups->push((object) ['id' => -1, 'code' => CoaReportGroups::UNMAPPED_INCOME, 'name' => 'Pemasukan tanpa grup laporan']);
        }
        return $rows;
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
