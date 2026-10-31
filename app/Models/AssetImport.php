<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['worksheets' => 'array', 'mapping' => 'array', 'approved_at' => 'datetime'];
    }

    public function rows()
    {
        return $this->hasMany(AssetStagingRow::class);
    }

    public function counts(): array
    {
        return $this->rows()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
    }
}
