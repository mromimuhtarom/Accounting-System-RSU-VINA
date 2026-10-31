<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\JournalLine;
use App\Models\OpeningBalance;
use App\Models\Receipt;
use App\Services\FinancialPosition;
use App\Services\FinancialSources;
use App\Services\LedgerAmounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class FinancialPositionController extends Controller
{
    public function sources(Request $request, FinancialSources $sources, FinancialPosition $ledger)
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        $date = $this->date($request);

        return view('reports.financial-sources', ['sources' => $sources->at($date), 'position' => $ledger->at($date)]);
    }

    public function sourceTransactions(Request $request, string $kind)
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        abort_unless(in_array($kind, ['receipts', 'expenses'], true), 404);
        $date = $this->date($request);
        $request->validate(['unmapped' => 'nullable|boolean']);
        [$model,$dateColumn] = $kind === 'receipts' ? [Receipt::class, 'transaction_date'] : [Expense::class, 'expense_date'];
        $opening = Schema::hasTable('opening_balances') ? OpeningBalance::where('status', 'POSTED')->first() : null;
        $coverage = $opening && $opening->balance_date->format('Y-m-d') <= $date ? $opening->balance_date->format('Y-m-d') : null;
        $query = $model::with('cashBankAccount', 'account')->where('status', 'ACTIVE')->whereDate($dateColumn, '<=', $date)
            ->when($coverage, fn ($q) => $q->whereDate($dateColumn, '>', $coverage))->whereDoesntHave('journal')
            ->when($request->boolean('unmapped'), fn ($q) => $q->whereNull('cash_bank_account_id'));
        $transactions = $query->orderByDesc($dateColumn)->orderByDesc('id')->paginate(30)->withQueryString();

        return view('reports.financial-source-transactions', compact('kind', 'date', 'dateColumn', 'coverage', 'transactions'));
    }

    private function date(Request $request): string
    {
        return $request->validate(['as_of' => 'nullable|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString()])['as_of'] ?? now('Asia/Jakarta')->toDateString();
    }

    public function index(Request $request, FinancialPosition $service)
    {
        Gate::authorize('viewAny', OpeningBalance::class);

        return view('reports.financial-position', ['position' => $service->at($this->date($request))]);
    }

    public function trialBalance(Request $request, FinancialPosition $service)
    {
        Gate::authorize('viewAny', OpeningBalance::class);

        return view('reports.financial-trial-balance', ['position' => $service->at($this->date($request))]);
    }

    public function ledger(Request $request, ChartAccount $account)
    {
        Gate::authorize('viewAny', OpeningBalance::class);
        $date = $this->date($request);
        $q = JournalLine::where('chart_account_id', $account->id)->whereHas('journal', fn ($q) => $q->where('status', 'POSTED')->whereDate('journal_date', '<=', $date));
        $sum = (clone $q)->selectRaw('SUM(debit) AS debit, SUM(credit) AS credit')->first();
        $net = LedgerAmounts::cents($sum->debit ?? 0) - LedgerAmounts::cents($sum->credit ?? 0);
        $lines = $q->with('journal')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('reports.financial-ledger', compact('account', 'date', 'net', 'lines'));
    }
}
