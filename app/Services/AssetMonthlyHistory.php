<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\JournalEntry;
use Carbon\CarbonImmutable;

class AssetMonthlyHistory
{
    public function forMonth(Asset $asset, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $end = $start->endOfMonth();
        $asset->loadMissing('capitalization.journal');
        $cap = $asset->capitalization;
        $events = $asset->events()->whereIn('type', ['DEPRECIATION', 'DISPOSAL'])->orderBy('event_date')->get();
        $journalDate = $cap?->journal?->journal_date?->format('Y-m-d');
        $posted = $cap?->status === 'POSTED' && $cap->journal?->status === 'POSTED';
        $disposal = $events->firstWhere('type', 'DISPOSAL');
        $cents = fn ($v) => (int) round((float) $v * 100);
        $accAt = function (string $date) use ($cap, $events, $cents) {
            return $cents($cap?->opening_accumulated ?? 0) + $events->filter(fn ($e) => $e->type === 'DEPRECIATION' && $e->event_date->format('Y-m-d') <= $date)->sum(fn ($e) => $cents($e->data['amount'] ?? 0));
        };
        $position = function (string $date) use ($cap, $posted, $journalDate, $disposal, $cents, $accAt) {
            if (! $posted || ! $journalDate || $journalDate > $date || $disposal && $disposal->event_date->format('Y-m-d') <= $date) {
                return ['cost' => 0, 'accumulated' => 0, 'book' => 0];
            }
            $cost = $cents($cap->cost);
            $acc = $accAt($date);

            return ['cost' => $cost / 100, 'accumulated' => $acc / 100, 'book' => ($cost - $acc) / 100];
        };
        $inMonth = fn ($date) => $date >= $start->toDateString() && $date <= $end->toDateString();
        $newCapitalization = $posted && $journalDate && $inMonth($journalDate);
        $depreciation = $posted ? $events->filter(fn ($e) => $e->type === 'DEPRECIATION' && $inMonth($e->event_date->format('Y-m-d')))->sum(fn ($e) => $cents($e->data['amount'] ?? 0)) / 100 : 0;
        $released = $posted && $disposal && $inMonth($disposal->event_date->format('Y-m-d')) ? ($cents($cap->cost) - $accAt($disposal->event_date->format('Y-m-d'))) / 100 : 0;

        return ['month' => $month, 'opening' => $position($start->subDay()->toDateString()), 'closing' => $position($end->toDateString()),
            'capitalization' => $newCapitalization ? (float) $cap->cost : 0, 'opening_accumulated_added' => $newCapitalization ? (float) $cap->opening_accumulated : 0,
            'depreciation' => $depreciation, 'released_book_value' => $released,
            'journals' => JournalEntry::where('source_type', Asset::class)->where('source_id', $asset->id)->where('status', 'POSTED')->whereDate('journal_date', '>=', $start->toDateString())->whereDate('journal_date', '<=', $end->toDateString())->withSum('lines', 'debit')->orderBy('journal_date')->orderBy('id')->get()];
    }
}
