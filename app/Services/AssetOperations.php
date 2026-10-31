<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetOperations
{
    public function record(Asset $asset, array $d, int $actor): void
    {
        DB::transaction(function () use ($asset, $d, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            if ($asset->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['asset' => 'Inventaris tidak aktif.']);
            }
            $before = $asset->toArray();
            $last = $asset->events()->whereIn('type', ['TRANSFER', 'STOCKTAKE', 'CALIBRATION', 'CALIBRATION_SCHEDULE'])->max('event_date');
            if ($last && $d['event_date'] < substr($last, 0, 10) && in_array($d['type'], ['TRANSFER', 'STOCKTAKE', 'CALIBRATION', 'CALIBRATION_SCHEDULE'], true)) {
                throw ValidationException::withMessages(['event_date' => 'Tanggal mendahului perubahan operasional terakhir.']);
            }
            if ($d['type'] === 'TRANSFER') {
                $d['from_location'] = $asset->location;
                $d['from_custodian'] = $asset->custodian;
                $asset->update(['location' => mb_strtoupper(trim($d['location'])), 'custodian' => $d['custodian']]);
                $asset->update(AssetExcelImport::identityKeys($asset->toArray()));
            }
            if ($d['type'] === 'STOCKTAKE') {
                $d['expected_location'] = $asset->location;
                $d['previous_condition'] = $asset->condition;
                $d['location_matches'] = AssetExcelImport::key($d['location']) === AssetExcelImport::key($asset->location);
                $asset->update(['condition' => $d['condition']]);
            }
            if (in_array($d['type'], ['CALIBRATION', 'CALIBRATION_SCHEDULE'], true)) {
                $asset->update(['calibration_required' => true, 'calibration_due_at' => $d['next_due_at']]);
            }
            $asset->events()->create(['type' => $d['type'], 'event_date' => $d['event_date'], 'data' => $d, 'created_by' => $actor]);
            AssetAudit::record($asset, $d['type'], $actor, $before, $asset->toArray() + ['event' => $d]);
        });
    }
}
