<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    /**
     * Laravel would normally infer `journal_lines` from the model name.
     * The migration intentionally creates `journal_entry_lines`, so the
     * table name must be declared explicitly.
     */
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'chart_account_id',
        'description',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }
}
