<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCapitalization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class AssetReporting
{
    private function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    public function summary(int $year, ?int $month = null): ?array
    {
        // Older databases/tests can still render their financial reports before this module is installed.
        if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_capitalizations')) {
            return null;
        }
        $start = CarbonImmutable::create($year, $month ?? 1, 1)->startOfDay();
        $end = $month ? $start->endOfMonth() : $start->endOfYear();
        $counts = Asset::where('status', 'ACTIVE')->selectRaw('inventory_type, COUNT(*) AS records, SUM(quantity) AS units')->groupBy('inventory_type')->get()->keyBy('inventory_type');
        $types = [];
        foreach (Asset::TYPES as $key => $label) {
            if ($key !== 'FACILITY') {
                $types[] = ['type' => $key, 'label' => $label, 'records' => (int) ($counts[$key]->records ?? 0), 'units' => (int) ($counts[$key]->units ?? 0)];
            }
        }
        $units = Asset::where('status', 'ACTIVE')->where('inventory_type', '!=', 'FACILITY')->selectRaw('location, COUNT(*) AS records, SUM(quantity) AS units')->groupBy('location')->orderByDesc('records')->orderBy('location')->limit(8)->get()->toArray();
        $caps = AssetCapitalization::where('status', 'POSTED')->whereHas('journal', fn ($q) => $q->where('status', 'POSTED')->whereDate('journal_date', '<=', $year.'-12-31'))
            ->with(['journal', 'asset' => fn ($q) => $q->withTrashed(), 'asset.events' => fn ($q) => $q->whereIn('type', ['DEPRECIATION', 'DISPOSAL'])->orderBy('event_date')])->get();
        $position = function (string $date) use ($caps) {
            $cost = 0;
            $accumulated = 0;
            $count = 0;
            foreach ($caps as $cap) {
                $asset = $cap->asset;
                if (! $asset || $cap->journal->journal_date->format('Y-m-d') > $date || $asset->deleted_at && $asset->deleted_at->format('Y-m-d') <= $date) {
                    continue;
                }
                $disposal = $asset->events->firstWhere('type', 'DISPOSAL');
                if ($disposal && $disposal->event_date->format('Y-m-d') <= $date || ! $disposal && $asset->status === 'DISPOSED') {
                    continue;
                }
                $cost += $this->cents($cap->cost);
                $count++;
                $accumulated += $this->cents($cap->opening_accumulated);
                foreach ($asset->events as $event) {
                    if ($event->type === 'DEPRECIATION' && $event->event_date->format('Y-m-d') <= $date) {
                        $accumulated += $this->cents($event->data['amount'] ?? 0);
                    }
                }
            }

            return ['fixed_assets' => $count, 'cost' => $cost / 100, 'accumulated' => $accumulated / 100, 'book_value' => ($cost - $accumulated) / 100];
        };
        $flow = function (string $from, string $to) use ($caps) {
            $additions = 0;
            $depreciation = 0;
            foreach ($caps as $cap) {
                $date = $cap->journal->journal_date->format('Y-m-d');
                if ($date >= $from && $date <= $to) {
                    $additions += $this->cents($cap->cost);
                }
                foreach ($cap->asset?->events ?? [] as $event) {
                    $date = $event->event_date->format('Y-m-d');
                    if ($event->type === 'DEPRECIATION' && $date >= $from && $date <= $to) {
                        $depreciation += $this->cents($event->data['amount'] ?? 0);
                    }
                }
            }

            return ['additions' => $additions / 100, 'depreciation' => $depreciation / 100];
        };
        $trend = [];
        for ($m = 1; $m <= 12; $m++) {
            $from = CarbonImmutable::create($year, $m, 1);
            $trend[$m] = $flow($from->toDateString(), $from->endOfMonth()->toDateString()) + $position($from->endOfMonth()->toDateString());
        }

        return ['period' => $month ? sprintf('%04d-%02d', $year, $month) : (string) $year, 'as_of' => $end->toDateString(),
            'types' => $types, 'units' => $units, 'inventory_count' => array_sum(array_column($types, 'records')),
            'facility_count' => (int) ($counts['FACILITY']->records ?? 0),
            'uncapitalized' => Asset::where('status', 'ACTIVE')->where('inventory_type', '!=', 'FACILITY')->whereDoesntHave('capitalization', fn ($q) => $q->where('status', 'POSTED'))->count(),
            'position' => $position($end->toDateString()), 'flow' => $flow($start->toDateString(),$end->toDateString()), 'trend' => $trend];
    }
}
