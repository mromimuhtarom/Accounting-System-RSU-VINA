<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance_date' => 'date', 'approved_at' => 'datetime', 'version' => 'integer'];
    }

    public function lines()
    {
        return $this->hasMany(OpeningBalanceLine::class);
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
