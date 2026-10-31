<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    private function lockBook(): void
    {
        DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['opening' => $message]);
    }

    private function audit(OpeningBalance $b, string $action, int $actor, array $before = []): void
    {
        DB::table('opening_balance_audits')->insert(['opening_balance_id' => $b->id, 'action' => $action, 'actor_id' => $actor, 'before' => json_encode($before), 'after' => json_encode($b->fresh()->load('lines')->toArray()), 'created_at' => now()]);
    }

    private function assertDate(string $date): void
    {
        if ($date > now('Asia/Jakarta')->toDateString()) {
            $this->fail('Tanggal saldo awal tidak boleh di masa depan.');
        }
        if (JournalEntry::where('status', 'POSTED')->whereDate('journal_date', '<=', $date)->exists()) {
            $this->fail('Sudah ada jurnal sampai tanggal ini. Saldo awal harus mendahului transaksi pertama agar tidak menggandakan saldo. Rekonsiliasi jurnal existing terlebih dahulu.');
        }
    }

    private function assertAccounts(OpeningBalance $b): void
    {
        foreach ($b->lines()->with('account')->get() as $line) {
            $a = $line->account;
            if (! $a?->is_active || ! in_array($a->type, ['ASSET', 'LIABILITY', 'EQUITY'], true) || $a->children()->exists()) {
                $this->fail('Akun saldo awal tidak aktif, merupakan akun induk, atau bukan akun neraca.');
            }
        }
    }

    public function save(array $data, int $actor): OpeningBalance
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->lockBook();
            $b = OpeningBalance::where('scope', 'INITIAL')->lockForUpdate()->first();
            if ($b && $b->status !== 'DRAFT') {
                $this->fail('Saldo awal yang sudah disetujui tidak dapat diubah melalui form ini.');
            }
            if ((int) ($b?->version ?? 0) !== (int) $data['version']) {
                $this->fail('Draft sudah berubah. Muat ulang halaman sebelum menyimpan.');
            }
            $this->assertDate($data['balance_date']);
            $lines = [];
            $seen = [];
            $debit = 0;
            $credit = 0;
            foreach ($data['lines'] as $i => $row) {
                $amount = LedgerAmounts::cents($row['amount'] ?? 0);
                if ($amount === 0) {
                    continue;
                }
                if ($amount < 0 || empty($row['chart_account_id'])) {
                    $this->fail('Isi akun dan nominal positif pada baris '.($i + 1).'.');
                }
                $a = ChartAccount::findOrFail($row['chart_account_id']);
                if (! $a->is_active || ! in_array($a->type, ['ASSET', 'LIABILITY', 'EQUITY'], true) || $a->children()->exists()) {
                    $this->fail('Gunakan akun neraca aktif yang paling rinci; pendapatan/beban tidak dimasukkan sebagai saldo awal.');
                }
                if (isset($seen[$a->id])) {
                    $this->fail('Akun '.$a->code.' diisi lebih dari sekali. Gabungkan saldonya.');
                }
                $seen[$a->id] = true;
                $side = $row['side'] === 'NORMAL' ? $a->normal_balance : $row['side'];
                $d = $side === 'DEBIT' ? $amount : 0;
                $c = $side === 'CREDIT' ? $amount : 0;
                $debit += $d;
                $credit += $c;
                $lines[] = ['chart_account_id' => $a->id, 'debit' => LedgerAmounts::decimal($d), 'credit' => LedgerAmounts::decimal($c)];
            }
            if (count($lines) < 2 || $debit <= 0 || $debit !== $credit) {
                $this->fail('Saldo awal belum seimbang. Debit Rp '.LedgerAmounts::display($debit).', kredit Rp '.LedgerAmounts::display($credit).'. Periksa dokumen saldo; sistem tidak mengisi selisih ke modal secara otomatis.');
            }
            $before = $b?->load('lines')->toArray() ?? [];
            $b ??= new OpeningBalance(['scope' => 'INITIAL', 'created_by' => $actor]);
            $b->fill(['balance_date' => $data['balance_date'], 'evidence' => $data['evidence'], 'version' => (int) ($b->version ?? 0) + 1]);
            $b->save();
            $b->lines()->delete();
            $b->lines()->createMany($lines);
            $this->audit($b, 'DRAFT_SAVE', $actor, $before);

            return $b;
        });
    }

    public function approve(OpeningBalance $balance, int $version, int $actor): void
    {
        DB::transaction(function () use ($balance, $version, $actor) {
            $this->lockBook();
            $b = OpeningBalance::lockForUpdate()->findOrFail($balance->id);
            if ($b->status !== 'DRAFT' || $b->version !== $version) {
                $this->fail('Status/versi draft berubah. Muat ulang halaman.');
            }
            $this->assertDate($b->balance_date->format('Y-m-d'));
            $this->assertAccounts($b);
            $before = $b->toArray();
            $b->update(['status' => 'APPROVED', 'approved_by' => $actor, 'approved_at' => now(), 'version' => $b->version + 1]);
            $this->audit($b, 'APPROVE', $actor, $before);
        });
    }

    public function post(OpeningBalance $balance, int $version, int $actor): JournalEntry
    {
        return DB::transaction(function () use ($balance, $version, $actor) {
            $this->lockBook();
            $b = OpeningBalance::lockForUpdate()->findOrFail($balance->id);
            if ($b->status === 'POSTED') {
                return $b->journal;
            }
            if ($b->status !== 'APPROVED' || $b->version !== $version) {
                $this->fail('Approval dan versi saldo awal harus valid sebelum posting.');
            }
            $this->assertDate($b->balance_date->format('Y-m-d'));
            $this->assertAccounts($b);
            $debit = 0;
            $credit = 0;
            foreach ($b->lines as $line) {
                $debit += LedgerAmounts::cents($line->debit);
                $credit += LedgerAmounts::cents($line->credit);
            }
            if ($debit <= 0 || $debit !== $credit) {
                $this->fail('Saldo awal tidak seimbang.');
            }
            $j = JournalEntry::create(['journal_no' => 'OPENING-'.$b->id, 'journal_date' => $b->balance_date->format('Y-m-d'), 'reference' => 'OPENING-'.$b->id, 'source_type' => OpeningBalance::class, 'source_id' => $b->id, 'description' => 'Saldo awal terverifikasi per '.$b->balance_date->format('d/m/Y'), 'status' => 'POSTED', 'created_by' => $actor]);
            foreach ($b->lines as $line) {
                $j->lines()->create($line->only(['chart_account_id', 'debit', 'credit']) + ['description' => 'Saldo awal berdasarkan dokumen terverifikasi']);
            }
            $before = $b->toArray();
            $b->update(['status' => 'POSTED', 'journal_entry_id' => $j->id, 'version' => $b->version + 1]);
            $this->audit($b,'POST',$actor,$before);

            return $j;
        });
    }
}
