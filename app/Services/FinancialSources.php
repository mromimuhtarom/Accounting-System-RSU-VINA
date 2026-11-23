<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\InsuranceMonthlyClaim;
use App\Models\OpeningBalance;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/** Operational sources are reconciliation information, never extra ledger lines. */
class FinancialSources
{
    public function at(string $date): array
    {
        $opening = Schema::hasTable('opening_balances') ? OpeningBalance::where('status', 'POSTED')->first() : null;
        $cutoff = $opening && $opening->balance_date->format('Y-m-d') <= $date ? $opening->balance_date->format('Y-m-d') : null;
        $end = CarbonImmutable::parse($date)->addDay()->toDateString();
        $coverageStart = $cutoff ? $opening->balance_date->copy()->addDay()->toDateString() : null;
        $books = [];
        foreach (['receipts' => [Receipt::class, 'transaction_date'], 'expenses' => [Expense::class, 'expense_date']] as $key => [$model, $dateColumn]) {
            $books[$key] = ['count' => 0, 'amount' => 0, 'unposted_count' => 0, 'unposted_amount' => 0, 'unmapped_count' => 0];
            if (! Schema::hasTable($key)) {
                continue;
            }
            $q = $model::where('status', 'ACTIVE')->where($dateColumn, '<', $end);
            $totals = (clone $q)->selectRaw('COUNT(*) AS records, SUM(amount) AS amount')->first();
            $pending = $q->when($coverageStart, fn ($q) => $q->where($dateColumn, '>=', $coverageStart))->whereDoesntHave('journal')
                ->selectRaw('COUNT(*) AS records, SUM(amount) AS amount, COUNT(CASE WHEN cash_bank_account_id IS NULL THEN 1 END) AS unmapped')
                ->first();
            $books[$key] = ['count' => (int) $totals->records, 'amount' => LedgerAmounts::cents($totals->amount ?? 0),
                'unposted_count' => (int) $pending->records, 'unposted_amount' => LedgerAmounts::cents($pending->amount ?? 0),
                'unmapped_count' => (int) $pending->unmapped];
        }
        // Monthly insurance amounts have no dated payment history. They are a CURRENT
        // tracking snapshot, not historical receivables and not an accounting source.
        $tracking = ['count' => 0, 'claimed' => 0, 'approved' => 0, 'paid' => 0, 'remaining' => 0, 'unverified' => 0, 'invalid' => 0];
        if (Schema::hasTable('insurance_monthly_claims')) {
            foreach (InsuranceMonthlyClaim::select('id', 'claimed_amount', 'approved_amount', 'paid_amount', 'status')->cursor() as $claim) {
                $tracking['count']++;
                $claimed = LedgerAmounts::cents($claim->claimed_amount);
                $approved = LedgerAmounts::cents($claim->approved_amount ?? 0);
                $paid = LedgerAmounts::cents($claim->paid_amount);
                $tracking['claimed'] += $claimed;
                $tracking['paid'] += $paid;
                if ($claim->approved_amount === null) {
                    $tracking['unverified']++;
                }
                if ($paid < 0 || $approved < 0 || $approved > $claimed || $paid > $approved) {
                    $tracking['invalid']++;
                }
                if ($claim->status !== 'DITOLAK' && $claim->approved_amount !== null) {
                    $tracking['approved'] += $approved;
                    $tracking['remaining'] += max(0, $approved - $paid);
                }
            }
        }
        $inventory = ['count' => 0, 'units' => 0, 'capitalized' => 0, 'facilities' => 0];
        if (Schema::hasTable('assets')) {
            $q = Asset::where('status', 'ACTIVE');
            $inventory = ['count' => (clone $q)->count(), 'units' => (int) (clone $q)->where('inventory_type', '!=', 'FACILITY')->sum('quantity'),
                'facilities' => (clone $q)->where('inventory_type', 'FACILITY')->count(),
                'capitalized' => Schema::hasTable('asset_capitalizations') ? (clone $q)->whereHas('capitalization', fn ($c) => $c->where('status', 'POSTED'))->count() : 0];
        }
        $accounts = ChartAccount::select('type')->selectRaw('COUNT(*) AS total')->where('is_active', true)->whereDoesntHave('children')->groupBy('type')->pluck('total', 'type')->all();

        return ['as_of' => $date, 'coverage_from' => $cutoff, 'books' => $books, 'tracking' => $tracking, 'inventory' => $inventory, 'accounts' => $accounts];
    }
}
