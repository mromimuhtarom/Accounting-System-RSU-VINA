<?php

namespace App\Models;

use App\Services\OpeningLedgerGuard;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected static function booted(): void
    {
        $guard = function (self $line) {
            app(OpeningLedgerGuard::class)->check(JournalEntry::findOrFail($line->journal_entry_id));
            $original = $line->getRawOriginal('journal_entry_id');
            if ($original && (int) $original !== (int) $line->journal_entry_id) {
                app(OpeningLedgerGuard::class)->check(JournalEntry::findOrFail($original));
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

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
