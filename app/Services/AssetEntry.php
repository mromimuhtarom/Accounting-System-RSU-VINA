<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetEntry
{
    public function create(array $data, int $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
            if ($existing = Asset::withTrashed()->where('entry_token', $data['entry_token'])->first()) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['entry_token' => 'Input sebelumnya sudah dihapus. Buka form baru untuk mencatat aktiva baru.']);
                }
                if ((int) $existing->created_by !== $actor) {
                    throw ValidationException::withMessages(['entry_token' => 'Token input tidak valid. Muat ulang form.']);
                }

                return $existing;
            }
            $keys = AssetExcelImport::identityKeys($data);
            $fingerprint = app(AssetExcelImport::class)->fingerprint($data);
            $similar = $this->checkDuplicates($data);
            $fields = $this->fields($data);
            $asset = Asset::create($fields + $keys + ['qr_token' => (string) Str::uuid(), 'fingerprint' => $fingerprint, 'created_by' => $actor, 'verified_by' => $actor, 'verified_at' => now(), 'status' => 'ACTIVE']);
            AssetAudit::record($asset, 'MANUAL_CREATE', $actor, [], $asset->toArray() + ['similar_candidates' => $similar, 'duplicate_reason' => $data['duplicate_reason'] ?? null]);

            return $asset;
        });
    }

    private function checkDuplicates(array $data, ?int $ignore = null, bool $serialChanged = true): array
    {
        $keys = AssetExcelImport::identityKeys($data);
        $fingerprint = app(AssetExcelImport::class)->fingerprint($data);
        $candidates = Asset::when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->where(function ($q) use ($keys, $fingerprint) {
            $q->where('fingerprint', $fingerprint);
            foreach (array_filter($keys) as $field => $value) {
                $q->orWhere($field, $value);
            }
        })->get();
        $similar = [];
        foreach ($candidates as $asset) {
            $sameSerial = ! empty($data['serial_number']) && AssetExcelImport::key($data['serial_number']) === AssetExcelImport::key($asset->serial_number);
            $exact = $asset->fingerprint === app(AssetExcelImport::class)->fingerprint($data);
            if ($sameSerial && $serialChanged || $exact) {
                throw ValidationException::withMessages(['serial_number' => 'Identitas sudah tercatat pada AST-'.$asset->id.'. Gunakan data tersebut.']);
            }
            if (! empty($data['serial_number']) && ! empty($asset->serial_number)) {
                continue;
            }
            $similar[] = 'AST-'.$asset->id;
        }
        if ($similar && (empty($data['allow_similar']) || empty($data['duplicate_reason']))) {
            throw ValidationException::withMessages(['duplicate_reason' => 'Nama/kode dan lokasi mirip '.implode(', ', $similar).'. Periksa lalu isi alasan jika memang unit berbeda.']);
        }

        return $similar;
    }

    private function fields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(['entry_token', 'name', 'code', 'serial_number', 'brand', 'model', 'inventory_type', 'category', 'location', 'custodian', 'quantity', 'condition', 'procurement_year', 'source_price', 'funding', 'capacity', 'notes', 'calibration_required', 'calibration_due_at']));
        if (empty($data['calibration_required'])) {
            $fields['calibration_due_at'] = null;
        }

        return $fields;
    }

    public function update(Asset $asset, array $data, int $actor): Asset
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            if ($asset->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['asset' => 'Aktiva yang telah dilepas tidak dapat diedit.']);
            }
            $this->checkVersion($asset, (int) $data['lock_version']);
            $before = $asset->toArray();
            $fields = $this->fields($data);
            unset($fields['entry_token']);
            $cap = $asset->capitalization()->lockForUpdate()->first();
            if ($cap) {
                foreach (['quantity', 'inventory_type', 'source_price', 'procurement_year'] as $field) {
                    $a = $asset->$field;
                    $b = $fields[$field] ?? null;
                    $equal = $field === 'source_price' ? ($a === null && $b === null || $a !== null && $b !== null && round((float) $a, 2) === round((float) $b, 2)) : (string) $a === (string) $b;
                    if (! $equal) {
                        throw ValidationException::withMessages([$field => 'Data ini terkunci karena aktiva sudah memiliki proses kapitalisasi. Koreksi melalui Accounting.']);
                    }
                }
            }
            $identityChanged = false;
            foreach (['code', 'serial_number', 'name', 'location'] as $field) {
                if (AssetExcelImport::key($asset->$field) !== AssetExcelImport::key($fields[$field] ?? null)) {
                    $identityChanged = true;
                }
            }
            $serialChanged = AssetExcelImport::key($asset->serial_number) !== AssetExcelImport::key($fields['serial_number'] ?? null);
            $similar = $identityChanged ? $this->checkDuplicates(array_replace($before, $data), $asset->id, $serialChanged) : [];
            $asset->fill($fields + AssetExcelImport::identityKeys($fields) + ['fingerprint' => app(AssetExcelImport::class)->fingerprint($fields)]);
            $changes = $asset->getDirty();
            $asset->save();
            if (array_key_exists('location', $changes) || array_key_exists('custodian', $changes)) {
                $asset->events()->create(['type' => 'TRANSFER', 'event_date' => now()->toDateString(), 'data' => ['from_location' => $before['location'], 'location' => $asset->location, 'from_custodian' => $before['custodian'], 'custodian' => $asset->custodian, 'notes' => $data['edit_reason'], 'source' => 'asset_edit_form'], 'created_by' => $actor]);
            }
            AssetAudit::record($asset, 'MANUAL_UPDATE', $actor, $before, $asset->toArray() + ['edit_reason' => $data['edit_reason'], 'similar_candidates' => $similar, 'duplicate_reason' => $data['duplicate_reason'] ?? null]);

            return $asset;
        });
    }

    public function delete(Asset $asset, string $reason, int $version, int $actor): void
    {
        DB::transaction(function () use ($asset, $reason, $version, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $this->checkVersion($asset, $version);
            $cap = $asset->capitalization()->lockForUpdate()->first();
            if ($asset->status !== 'ACTIVE' || $cap && ($cap->status !== 'DRAFT' || $cap->journal_entry_id) || JournalEntry::where('source_type', Asset::class)->where('source_id', $asset->id)->exists()) {
                throw ValidationException::withMessages(['asset' => 'Aktiva sudah disetujui, dijurnal, atau dilepas. Gunakan proses pelepasan aset.']);
            }
            $before = $asset->toArray();
            $asset->delete();
            AssetAudit::record($asset, 'MANUAL_DELETE', $actor, $before, $asset->toArray() + ['delete_reason' => $reason]);
        });
    }

    private function checkVersion(Asset $asset, int $version): void
    {
        if ((int) $asset->lock_version !== $version) {
            throw ValidationException::withMessages(['lock_version' => 'Data aktiva sudah berubah. Muat ulang halaman sebelum menyimpan atau menghapus.']);
        }
    }
}
