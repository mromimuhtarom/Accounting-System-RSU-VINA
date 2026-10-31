<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCapitalization extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['acquired_at' => 'date', 'depreciation_starts_at' => 'date', 'approved_at' => 'datetime', 'cost' => 'decimal:2', 'opening_accumulated' => 'decimal:2', 'opening_book_value' => 'decimal:2', 'residual_value' => 'decimal:2'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
