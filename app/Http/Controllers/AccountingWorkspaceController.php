<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingEntryRequest;
use App\Models\OpeningBalance;
use App\Services\AccountingAnalysis;
use App\Services\AccountingEntry;
use App\Services\FinancialPosition;
use App\Services\FinancialSources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingWorkspaceController extends Controller
{
    public function index(Request $request, FinancialSources $sources, FinancialPosition $ledger)
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        $date = $request->validate(['as_of' => 'nullable|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString()])['as_of'] ?? now('Asia/Jakarta')->toDateString();

        return view('accounting.workspace', ['sources' => $sources->at($date), 'position' => $ledger->at($date)]);
    }

    public function create(Request $request, AccountingEntry $service)
    {
        Gate::authorize('manage', OpeningBalance::class);
        $kind = $request->validate(['kind' => 'nullable|in:'.implode(',', array_keys(AccountingEntry::TYPES))])['kind'] ?? 'expense_accrual';

        return view('accounting.entry', ['kind' => $kind, 'types' => AccountingEntry::TYPES, 'debitAccounts' => $service->accounts($kind, 'debit'), 'creditAccounts' => $service->accounts($kind, 'credit'), 'entryToken' => (string) Str::uuid()]);
    }

    public function store(AccountingEntryRequest $request, AccountingEntry $service)
    {
        $journal = $service->post($request->validated(), $request->user()->id);

        return redirect()->route('reports.financial-ledger', ['account' => $request->integer('debit_account_id'), 'as_of' => $request->input('journal_date')])->with('success', 'Pencatatan nonkas terposting sebagai '.$journal->journal_no.'. Permintaan berulang tidak membuat jurnal kedua.');
    }

    public function analysis(Request $request, AccountingAnalysis $analysis)
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        $today = now('Asia/Jakarta')->toDateString();
        $dates = $request->validate(['from' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today, 'to' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today]);
        $from = $dates['from'] ?? now('Asia/Jakarta')->startOfMonth()->toDateString();
        $to = $dates['to'] ?? $today;
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'Tanggal awal tidak boleh melewati tanggal akhir.']);
        }

        return view('reports.accounting-analysis', ['analysis' => $analysis->period($from, $to)]);
    }
}
