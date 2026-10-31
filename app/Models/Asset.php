<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (self $asset) {
            $asset->lock_version = (int) $asset->getOriginal('lock_version') + 1;
        });
    }

    public const TYPES = ['MEDICAL' => 'Alat medis', 'MEDICAL_SUPPORT' => 'Penunjang medis', 'IT' => 'IT / Hardware', 'SOFTWARE' => 'Software', 'INFRASTRUCTURE' => 'Prasarana', 'FACILITY' => 'Fasilitas / Kapasitas'];

    public const CONDITIONS = ['GOOD' => 'Baik', 'BROKEN' => 'Rusak', 'INACTIVE' => 'Tidak beroperasi', 'UNKNOWN' => 'Belum diketahui'];

    public const STATUSES = ['ACTIVE' => 'Aktif', 'DISPOSED' => 'Sudah dilepas'];

    public const CAPITALIZATION_STATUSES = ['DRAFT' => 'Draft', 'APPROVED' => 'Disetujui', 'POSTED' => 'Terposting'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'quantity' => 'integer', 'verified_at' => 'datetime', 'calibration_due_at' => 'date', 'calibration_required' => 'boolean', 'source_price' => 'decimal:2'];
    }

    public function stagingRow()
    {
        return $this->belongsTo(AssetStagingRow::class, 'asset_staging_row_id');
    }

    public function capitalization()
    {
        return $this->hasOne(AssetCapitalization::class);
    }

    public function events()
    {
        return $this->hasMany(AssetEvent::class);
    }
}
