<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetImport;
use App\Models\AssetStagingRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class AssetExcelImport
{
    public const SOURCES = ['classification' => 'Klasifikasi alat (sumber utama)', 'alkes' => 'Format Import Alkes (pembanding)', 'it' => 'Inventaris hardware / software', 'infrastructure' => 'Prasarana / kapasitas'];

    public const FIELDS = ['name', 'code', 'serial_number', 'brand', 'model', 'category', 'location', 'condition', 'procurement_year', 'source_price', 'funding', 'notes', 'quantity', 'capacity', 'availability', 'original_row', 'confidence', 'distributor', 'registration'];

    public function upload(UploadedFile $file, int $actor): AssetImport
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if ($existing = AssetImport::where('file_hash', $hash)->first()) {
            return $existing;
        }
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $info = $reader->listWorksheetInfo($file->getRealPath());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'Workbook tidak dapat dibaca. Gunakan file Excel XLS/XLSX yang tidak rusak atau terenkripsi.']);
        }
        if (! in_array($reader::class, [Xlsx::class, Xls::class], true)) {
            throw ValidationException::withMessages(['file' => 'Isi file harus berupa workbook Excel XLS/XLSX.']);
        }
        $cells = array_sum(array_map(fn ($s) => $s['totalRows'] * $s['totalColumns'], $info));
        if ($cells > 500000 || count($info) > 40) {
            throw ValidationException::withMessages(['file' => 'Workbook terlalu besar (maksimum 500.000 sel / 40 worksheet).']);
        }
        $path = $file->store('asset-imports', 'local');
        try {
            return DB::transaction(function () use ($file, $hash, $path, $info, $actor) {
                $batch = AssetImport::firstOrCreate(['file_hash' => $hash], ['filename' => $file->getClientOriginalName(), 'path' => $path, 'worksheets' => $info, 'created_by' => $actor]);
                AssetAudit::record($batch, 'UPLOAD', $actor, [], ['filename' => $batch->filename, 'sha256' => $hash]);

                return $batch;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function read(AssetImport $batch, string $sheet): array
    {
        if (! collect($batch->worksheets)->contains('worksheetName', $sheet)) {
            throw ValidationException::withMessages(['sheet' => 'Worksheet tidak ditemukan.']);
        }
        $reader = IOFactory::createReaderForFile(Storage::disk('local')->path($batch->path));
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);
        $book = $reader->load(Storage::disk('local')->path($batch->path));
        try {
            return $book->getSheetByName($sheet)->toArray(null, false, false, true);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function preset(string $source, string $sheet): array
    {
        return match ($source) {
            'classification' => [1, ['name' => 'G', 'code' => 'F', 'serial_number' => 'L', 'brand' => 'M', 'model' => 'N', 'category' => 'H', 'location' => 'E', 'condition' => 'P', 'procurement_year' => 'O', 'source_price' => 'Q', 'funding' => 'R', 'notes' => 'U', 'availability' => 'K', 'original_row' => 'B', 'confidence' => 'I', 'distributor' => 'S', 'registration' => 'T']],
            'alkes' => [5, ['name' => 'E', 'code' => 'D', 'serial_number' => 'G', 'brand' => 'H', 'model' => 'I', 'condition' => 'K', 'procurement_year' => 'J', 'source_price' => 'L', 'funding' => 'M', 'notes' => 'P', 'availability' => 'F', 'distributor' => 'N', 'registration' => 'O']],
            'it' => [4, ['name' => 'C', 'location' => 'B', 'quantity' => 'D']],
            'infrastructure' => [3, ['name' => 'D', 'category' => 'B', 'availability' => 'E', 'capacity' => 'F', 'notes' => 'G']],
            default => [1, []],
        };
    }

    public static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')));
    }

    public static function key(mixed $value): string
    {
        return mb_strtolower(self::text($value));
    }

    public function normalize(array $data, string $source, string $sheet): array
    {
        $d = array_fill_keys(self::FIELDS, null);
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $d)) {
                $d[$key] = self::text($value) ?: null;
            }
        }
        $category = self::key($d['category']);
        $d['inventory_type'] = match ($source) {
            'classification' => str_contains($category, 'penunjang') ? 'MEDICAL_SUPPORT' : 'MEDICAL',
            'it' => (str_contains(self::key($sheet), 'sheet2') || str_contains(self::key($sheet), 'soft')) ? 'SOFTWARE' : 'IT',
            'infrastructure' => 'FACILITY',
            default => 'MEDICAL',
        };
        $d['category'] = $d['category'] ? mb_strtoupper($d['category']) : Asset::TYPES[$d['inventory_type']];
        $d['location'] = $d['location'] ? mb_strtoupper($d['location']) : null;
        $condition = self::key($d['condition']);
        $d['condition'] = match ($condition) {
            'baik','berfungsi' => 'GOOD','rusak','tdk berfungsi','tidak berfungsi' => 'BROKEN','tdk beroperasi','tidak beroperasi' => 'INACTIVE', default => 'UNKNOWN'
        };
        $d['quantity'] = $d['quantity'] ?? '1';
        if ($d['source_price'] !== null) {
            $price = str_replace(['Rp', 'rp', ' '], '', $d['source_price']);
            if (str_contains($price, ',')) {
                $price = str_replace(',', '.', str_replace('.', '', $price));
            } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $price)) {
                $price = str_replace('.', '', $price);
            }
            $d['source_price'] = is_numeric($price) && (float) $price >= 0 ? number_format((float) $price, 2, '.', '') : null;
        }

        return $d;
    }

    public function issues(array $d): array
    {
        $issues = [];
        if (empty($d['name'])) {
            $issues[] = 'Nama wajib diisi';
        }
        if (empty($d['location'])) {
            $issues[] = 'Lokasi belum diketahui';
        }
        if (($d['condition'] ?? '') === 'UNKNOWN') {
            $issues[] = 'Kondisi belum diverifikasi';
        }
        if (empty($d['serial_number']) || preg_match('/^(?:\d{1,4}|unknown|-unknown-|unregister|n\/a|-)$/i', $d['serial_number'])) {
            $issues[] = 'Serial kosong / pendek / placeholder: verifikasi identitas unit';
        }
        if (! ctype_digit((string) $d['quantity']) || (int) $d['quantity'] !== 1) {
            $issues[] = 'Data agregat: identifikasi unit atau tetapkan sebagai fasilitas';
        }
        if ($d['inventory_type'] === 'FACILITY') {
            $issues[] = 'Parameter fasilitas / kapasitas, bukan otomatis unit aktiva';
        }
        if (self::key($d['confidence'] ?? '') !== '' && self::key($d['confidence']) !== 'tinggi') {
            $issues[] = 'Klasifikasi sumber perlu verifikasi';
        }
        if (! empty($d['procurement_year']) && (! ctype_digit((string) $d['procurement_year']) || (int) $d['procurement_year'] < 1970 || (int) $d['procurement_year'] > (int) date('Y') + 1)) {
            $issues[] = 'Tahun pengadaan tidak valid';
        }

        return $issues;
    }

    public function fingerprint(array $d): string
    {
        return hash('sha256', implode('|', array_map(fn ($k) => self::key($d[$k] ?? ''), ['code', 'serial_number', 'name', 'location'])));
    }

    public function duplicates(array $d, ?int $exclude = null): array
    {
        $matches = [];
        $keys = array_filter(self::identityKeys($d));
        if (! $keys) {
            return [];
        }
        $candidate = function ($q) use ($keys) {
            foreach ($keys as $key => $value) {
                $q->orWhere($key, $value);
            }
        };
        foreach (Asset::where($candidate)->pluck('id') as $id) {
            $matches[] = 'master:'.$id;
        }
        foreach (AssetStagingRow::whereNotIn('status', ['SKIPPED', 'FAILED', 'POSTED'])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->where($candidate)->pluck('id') as $id) {
            $matches[] = 'staging:'.$id;
        }

        return $matches;
    }

    public static function identityKeys(array $d): array
    {
        $serial = self::key($d['serial_number'] ?? '');
        $name = self::key($d['name'] ?? '');
        $code = self::key($d['code'] ?? '');
        $location = self::key($d['location'] ?? '');

        return ['serial_key' => $serial !== '' ? hash('sha256', $serial) : null,
            'name_location_key' => $name !== '' && $location !== '' ? hash('sha256', json_encode([$name, $location])) : null,
            'code_location_key' => $code !== '' && $location !== '' ? hash('sha256', json_encode([$code, $location])) : null];
    }

    public function stage(AssetImport $batch, string $sheet, string $source, int $header, array $mapping, int $actor): void
    {
        if (in_array($sheet, ['Ringkasan', 'Perlu Verifikasi', 'Panduan', 'Petunjuk', 'Tatacara pengisian'], true)) {
            throw ValidationException::withMessages(['sheet' => 'Worksheet ringkasan/panduan/verifikasi tidak diimpor sebagai inventaris baru.']);
        }
        $rows = $this->read($batch, $sheet);
        if (! isset($rows[$header])) {
            throw ValidationException::withMessages(['header_row' => 'Baris header tidak ditemukan.']);
        }
        foreach (array_filter($mapping) as $column) {
            if (! array_key_exists($column, $rows[$header])) {
                throw ValidationException::withMessages(['mapping' => 'Kolom mapping tidak ada di worksheet.']);
            }
        }
        DB::transaction(function () use ($batch, $sheet, $source, $header, $mapping, $rows, $actor) {
            $batch = AssetImport::lockForUpdate()->findOrFail($batch->id);
            if (in_array($batch->status, ['APPROVED', 'POSTED'], true)) {
                throw ValidationException::withMessages(['import' => 'Impor sudah disetujui / diposting.']);
            }
            if ($batch->source_type && $batch->source_type !== $source) {
                throw ValidationException::withMessages(['source_type' => 'Jenis sumber satu file harus konsisten.']);
            }
            if ($batch->rows()->where('sheet', $sheet)->exists()) {
                throw ValidationException::withMessages(['sheet' => 'Worksheet sudah distaging. Tinjau baris yang ada; upload ulang tidak menggandakan data.']);
            }
            $location = null;
            foreach ($rows as $number => $raw) {
                if ($number <= $header) {
                    continue;
                }
                $nonempty = array_filter($raw, fn ($v) => self::text($v) !== '');
                if (! $nonempty) {
                    continue;
                }
                $data = [];
                foreach ($mapping as $field => $col) {
                    if ($col && in_array($field, self::FIELDS, true)) {
                        $data[$field] = $raw[$col] ?? null;
                    }
                }
                $skip = false;
                if ($source === 'alkes' && ! is_numeric($raw['A'] ?? null)) {
                    if (self::text($raw['A'] ?? '') === '***') {
                        $location = self::text($raw['D'] ?? '');
                    }
                    $skip = true;
                }
                if ($source === 'it') {
                    if (! empty($data['location'])) {
                        $location = $data['location'];
                    }
                    $data['location'] = $location;
                }
                if ($source === 'alkes' && empty($data['location'])) {
                    $data['location'] = $location;
                }
                $d = $this->normalize($data, $source, $sheet);
                if (empty($d['name']) || str_starts_with($d['name'], '::end::') || ($source === 'it' && preg_match('/^(?:total|jumlah)(?:\s|$)/i', $d['name']))) {
                    $skip = true;
                }
                $unavailable = ! empty($d['availability']) && ! in_array(self::key($d['availability']), ['ya', 'ada', 'yes', '1'], true);
                if ($unavailable && $d['condition'] !== 'BROKEN') {
                    $skip = true;
                }
                $issues = $this->issues($d);
                if ($unavailable && $d['condition'] === 'BROKEN') {
                    $issues[] = 'Sumber menyatakan tidak ada dan rusak: verifikasi keberadaan fisik';
                }
                if (in_array($source, ['classification', 'alkes'], true) && empty($d['availability'])) {
                    $issues[] = 'Ketersediaan tidak diisi pada sumber';
                }
                if (is_numeric($d['funding'] ?? null)) {
                    $issues[] = 'Kode pendanaan numerik belum dipetakan';
                }
                if ($source === 'alkes') {
                    $issues[] = 'Kategori medis / penunjang perlu dicocokkan ke sumber klasifikasi';
                }
                if (! empty($data['source_price']) && $d['source_price'] === null) {
                    $issues[] = 'Harga sumber tidak dapat dibaca; wajib diverifikasi';
                }
                if (collect($raw)->contains(fn ($v) => is_string($v) && str_starts_with($v, '='))) {
                    $issues[] = 'Formula disimpan sebagai teks; perlu nilai tervalidasi';
                }
                $duplicates = $skip ? [] : $this->duplicates($d);
                $status = $skip ? 'SKIPPED' : ($duplicates ? 'DUPLICATE' : ($issues ? 'REVIEW' : 'READY'));
                $batch->rows()->create(['sheet' => $sheet, 'source_row' => $number, 'raw' => $raw, 'normalized' => $d, 'fingerprint' => $this->fingerprint($d), 'status' => $status, 'issues' => $issues, 'duplicates' => $duplicates] + self::identityKeys($d));
            }
            $configs = $batch->mapping ?? [];
            $configs[$sheet] = ['header' => $header, 'columns' => $mapping];
            $batch->update(['source_type' => $source, 'sheet' => $sheet, 'header_row' => $header, 'mapping' => $configs, 'status' => 'STAGED']);
            AssetAudit::record($batch, 'STAGE', $actor, [], ['sheet' => $sheet, 'mapping' => $mapping, 'counts' => $batch->counts()]);
        });
    }

    public function review(AssetStagingRow $row, array $data, string $decision, string $note, int $actor): void
    {
        DB::transaction(function () use ($row, $data, $decision, $note, $actor) {
            $batch = AssetImport::lockForUpdate()->findOrFail($row->asset_import_id);
            $row = AssetStagingRow::lockForUpdate()->findOrFail($row->id);
            if ($batch->status !== 'STAGED' || $row->status === 'POSTED') {
                throw ValidationException::withMessages(['review' => 'Hanya staging yang belum disetujui dapat ditinjau.']);
            }
            $before = $row->toArray();
            $d = array_replace($row->normalized, $data);
            if ($decision === 'READY' && (empty($d['name']) || ! in_array($d['inventory_type'], array_keys(Asset::TYPES), true) || ! ctype_digit((string) $d['quantity']) || (int) $d['quantity'] < 1)) {
                throw ValidationException::withMessages(['review' => 'Nama, jenis inventaris, dan jumlah positif wajib valid.']);
            }
            $row->update(['normalized' => $d, 'status' => $decision, 'review_note' => $note, 'reviewed_by' => $actor, 'reviewed_at' => now(), 'fingerprint' => $this->fingerprint($d), 'duplicates' => $this->duplicates($d, $row->id)] + self::identityKeys($d));
            AssetAudit::record($row, 'REVIEW', $actor, $before, $row->toArray());
        });
    }

    public function approve(AssetImport $batch, int $actor): void
    {
        DB::transaction(function () use ($batch, $actor) {
            $batch = AssetImport::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'STAGED' || ! $batch->rows()->exists() || $batch->rows()->whereIn('status', ['REVIEW', 'DUPLICATE', 'FAILED'])->exists()) {
                throw ValidationException::withMessages(['approval' => 'Selesaikan semua baris perlu periksa, duplikat, dan gagal sebelum approval.']);
            }
            $batch->update(['status' => 'APPROVED', 'approved_by' => $actor, 'approved_at' => now()]);
            AssetAudit::record($batch, 'APPROVE', $actor, [], ['counts' => $batch->counts()]);
        });
    }

    public function post(AssetImport $batch, int $actor): array
    {
        return DB::transaction(function () use ($batch, $actor) {
            // Serialize posting across files so concurrent overlapping sources are rechecked.
            AssetImport::orderBy('id')->lockForUpdate()->first();
            $batch = AssetImport::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'POSTED') {
                return $batch->counts();
            }
            if ($batch->status !== 'APPROVED') {
                throw ValidationException::withMessages(['posting' => 'Approval administrator aset diperlukan.']);
            }
            $ready = $batch->rows()->where('status', 'READY')->lockForUpdate()->get();
            $blocked = false;
            foreach ($ready as $row) {
                $d = $row->normalized;
                $duplicates = $this->duplicates($d, $row->id);
                $newDuplicates = array_diff($duplicates, $row->duplicates ?? []);
                // Master created from an already reviewed staging candidate is the same candidate.
                $newDuplicates = array_filter($newDuplicates, function ($ref) use ($row) {
                    if (! str_starts_with($ref, 'master:')) {
                        return true;
                    }
                    $origin = Asset::find(substr($ref, 7))?->asset_staging_row_id;

                    return ! in_array('staging:'.$origin, $row->duplicates ?? [], true);
                });
                if ($duplicates && (! $row->reviewed_at || $newDuplicates)) {
                    $row->update(['status' => 'DUPLICATE', 'duplicates' => $duplicates]);
                    $blocked = true;
                }
                $rules = ['name' => 'required|string|max:255', 'quantity' => 'required|integer|min:1|max:1000000', 'procurement_year' => 'nullable|integer|min:1970|max:'.(date('Y') + 1), 'source_price' => 'nullable|numeric|min:0|max:1000000000000'];
                foreach (['code', 'serial_number', 'brand', 'model', 'category', 'location', 'condition', 'funding', 'capacity'] as $field) {
                    $rules[$field] = 'nullable|string|max:255';
                }
                $validation = Validator::make($d, $rules);
                if ($validation->fails()) {
                    $row->update(['status' => 'FAILED', 'issues' => $validation->errors()->all()]);
                    $blocked = true;
                }
            }
            if ($blocked) {
                $batch->update(['status' => 'STAGED', 'approved_by' => null, 'approved_at' => null]);
                AssetAudit::record($batch, 'POST_BLOCKED_DUPLICATES', $actor, [], ['counts' => $batch->counts()]);

                return $batch->counts();
            }
            foreach ($ready as $row) {
                $d = $row->normalized;
                $fields = array_intersect_key($d, array_flip(['name', 'code', 'serial_number', 'brand', 'model', 'category', 'location', 'condition', 'procurement_year', 'source_price', 'funding', 'notes', 'quantity', 'capacity', 'inventory_type', 'custodian']));
                $asset = Asset::create($fields + ['qr_token' => (string) Str::uuid(), 'asset_staging_row_id' => $row->id, 'fingerprint' => $row->fingerprint, 'verified_by' => $batch->approved_by ?? $actor, 'verified_at' => now()] + self::identityKeys($d));
                $row->update(['status' => 'POSTED']);
                AssetAudit::record($asset, 'IMPORT_POST', $actor, [], $asset->toArray());
            }
            if ($batch->status === 'APPROVED') {
                $batch->update(['status' => 'POSTED']);
            }
            AssetAudit::record($batch, 'POST', $actor, [], ['counts' => $batch->counts()]);

            return $batch->counts();
        });
    }
}
