<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class AccountingAnalysis
{
    public function period(string $from, string $to): array
    {
        $ledger = app(FinancialPosition::class);
        $before = CarbonImmutable::parse($from)->subDay()->toDateString();
        $start = $ledger->at($before);
        $end = $ledger->at($to);
        $old = collect($start['rows'])->keyBy('id');
        $new = collect($end['rows'])->keyBy('id');
        $rows = [];
        foreach ($old->keys()->merge($new->keys())->unique() as $id) {
            $a = $new->get($id) ?? $old->get($id);
            $a['opening'] = $old->get($id)['balance'] ?? 0;
            $a['closing'] = $new->get($id)['balance'] ?? 0;
            $a['change'] = $a['closing'] - $a['opening'];
            $a['period_debit'] = ($new->get($id)['debit'] ?? 0) - ($old->get($id)['debit'] ?? 0);
            $a['period_credit'] = ($new->get($id)['credit'] ?? 0) - ($old->get($id)['credit'] ?? 0);
            if ($a['opening'] !== 0 || $a['closing'] !== 0 || $a['period_debit'] !== 0 || $a['period_credit'] !== 0) {
                $rows[] = $a;
            }
        }
        usort($rows, fn ($a, $b) => strnatcmp($a['code'], $b['code']));

        return ['from' => $from, 'to' => $to, 'before' => $before, 'start' => $start, 'end' => $end, 'rows' => $rows,
            'debt_asset_percent' => $end['assets'] > 0 && $end['liabilities'] >= 0 ? 100 * $end['liabilities'] / $end['assets'] : null];
    }
}
