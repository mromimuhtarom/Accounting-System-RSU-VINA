<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialPosition
{
    public function at(string $date): array
    {
        $records = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')->join('chart_accounts as a', 'a.id', '=', 'l.chart_account_id')
            ->where('j.status', 'POSTED')->whereDate('j.journal_date', '<=', $date)
            ->select('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance', 'a.is_active')->selectRaw('SUM(l.debit) AS debits, SUM(l.credit) AS credits')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance', 'a.is_active')->orderBy('a.code')->get();
        $groups = ['ASSET' => [], 'LIABILITY' => [], 'EQUITY' => []];
        $rows = [];
        $totals = ['ASSET' => 0, 'LIABILITY' => 0, 'EQUITY' => 0];
        $profit = 0;
        $debit = 0;
        $credit = 0;
        $trialDebit = 0;
        $trialCredit = 0;
        foreach ($records as $record) {
            $d = LedgerAmounts::cents($record->debits);
            $c = LedgerAmounts::cents($record->credits);
            $net = $d - $c;
            $debit += $d;
            $credit += $c;
            $trialDebit += max(0, $net);
            $trialCredit += max(0, -$net);
            $balance = in_array($record->type, ['LIABILITY', 'EQUITY', 'REVENUE'], true) ? -$net : $net;
            $row = ['id' => $record->id, 'code' => $record->code, 'name' => $record->name, 'type' => $record->type, 'active' => (bool) $record->is_active, 'normal_balance' => $record->normal_balance, 'debit' => $d, 'credit' => $c, 'balance' => $balance, 'trial_debit' => max(0, $net), 'trial_credit' => max(0, -$net)];
            $rows[] = $row;
            if (isset($groups[$record->type])) {
                $totals[$record->type] += $balance;
                if ($balance !== 0) {
                    $groups[$record->type][] = $row;
                }
            }
            if ($record->type === 'REVENUE') {
                $profit += $balance;
            }
            if ($record->type === 'EXPENSE') {
                $profit -= $balance;
            }
        }
        $opening = Schema::hasTable('opening_balances') ? OpeningBalance::with('journal')->where('scope', 'INITIAL')->first() : null;
        $coverageFrom = $opening?->status === 'POSTED' && $date >= $opening->balance_date->format('Y-m-d') ? $opening->balance_date->format('Y-m-d') : null;
        $unpostedReceipts = Schema::hasTable('receipts') ? Receipt::where('status', 'ACTIVE')->whereDate('transaction_date', '<=', $date)->when($coverageFrom, fn ($q) => $q->whereDate('transaction_date', '>', $coverageFrom))->whereDoesntHave('journal')->count() : 0;
        $unpostedExpenses = Schema::hasTable('expenses') ? Expense::where('status', 'ACTIVE')->whereDate('expense_date', '<=', $date)->when($coverageFrom, fn ($q) => $q->whereDate('expense_date', '>', $coverageFrom))->whereDoesntHave('journal')->count() : 0;

        return ['as_of' => $date, 'groups' => $groups, 'rows' => $rows, 'assets' => $totals['ASSET'], 'liabilities' => $totals['LIABILITY'],
            'equity_accounts' => $totals['EQUITY'], 'unclosed_result' => $profit, 'equity' => $totals['EQUITY'] + $profit,
            'liabilities_equity' => $totals['LIABILITY'] + $totals['EQUITY'] + $profit, 'difference' => $totals['ASSET'] - $totals['LIABILITY'] - $totals['EQUITY'] - $profit,
            'movement_debit' => $debit, 'movement_credit' => $credit, 'trial_debit' => $trialDebit, 'trial_credit' => $trialCredit,
            'journal_count' => JournalEntry::where('status', 'POSTED')->whereDate('journal_date', '<=', $date)->count(),
            'opening' => $opening, 'before_opening' => $opening?->status === 'POSTED' && $date < $opening->balance_date->format('Y-m-d'),
            'coverage_from' => $coverageFrom, 'unposted_receipts' => $unpostedReceipts, 'unposted_expenses' => $unpostedExpenses];
    }
}
