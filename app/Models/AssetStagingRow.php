<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetStagingRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['raw' => 'array', 'normalized' => 'array', 'issues' => 'array', 'duplicates' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function import()
    {
        return $this->belongsTo(AssetImport::class, 'asset_import_id');
    }
}
