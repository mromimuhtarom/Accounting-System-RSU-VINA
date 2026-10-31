<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OpeningLedgerGuard
{
    public function check(JournalEntry $journal): void
    {
        if (! Schema::hasTable('opening_balances')) {
            return;
        }
        // Same lock as opening approval/posting; existing journal services run inside transactions.
        DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
        $opening = OpeningBalance::where('status', 'POSTED')->first();
        if (! $opening) {
            return;
        }
        if ((int) $journal->id === (int) $opening->journal_entry_id) {
            throw ValidationException::withMessages(['journal' => 'Jurnal saldo awal terposting dikunci. Koreksi menggunakan jurnal penyesuaian setelah tanggal saldo awal.']);
        }
        $cutoff = $opening->balance_date->format('Y-m-d');
        $date = $journal->journal_date?->format('Y-m-d');
        $original = $journal->getRawOriginal('journal_date');
        if ($date && $date <= $cutoff || $original && substr((string) $original, 0, 10) <= $cutoff) {
            throw ValidationException::withMessages(['journal_date' => 'Tanggal sampai '.$cutoff.' sudah dicakup saldo awal. Gunakan tanggal setelah saldo awal dan rekonsiliasi koreksinya.']);
        }
    }
}
