<?php

namespace App\Services;

use App\Models\AssetPeriod;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Two-sided, non-cash entries. Cash receipt/expense entry stays in its existing module. */
class AccountingEntry
{
    public const TYPES = [
        'asset_credit' => ['label' => 'Perolehan aset secara kredit', 'debit' => 'ASSET', 'credit' => 'LIABILITY', 'help' => 'Menambah aset dan utang. Bukan pembayaran kas. Cocokkan dengan register aset sebelum kapitalisasi agar tidak dijurnal dua kali.'],
        'expense_accrual' => ['label' => 'Beban yang masih harus dibayar', 'debit' => 'EXPENSE', 'credit' => 'LIABILITY', 'help' => 'Mencatat beban dan kewajiban yang belum dibayar; tidak membuat transaksi Pengeluaran kas.'],
        'depreciation' => ['label' => 'Penyesuaian penyusutan', 'debit' => 'EXPENSE', 'credit' => 'ASSET', 'help' => 'Koreksi yang didukung perhitungan Accounting. Pilih akun akumulasi penyusutan. Jangan ulangi penyusutan yang sudah diposting dari modul aset.'],
        'equity_transfer' => ['label' => 'Reklasifikasi antar akun ekuitas', 'debit' => 'EQUITY', 'credit' => 'EQUITY', 'help' => 'Memindahkan saldo antar akun modal/saldo laba berdasarkan dokumen; tidak menciptakan modal untuk menutup selisih.'],
    ];

    public function accounts(string $kind, string $side)
    {
        $type = self::TYPES[$kind][$side] ?? null;
        $q = ChartAccount::where('is_active', true)->where('type', $type)->whereDoesntHave('children');
        if ($kind === 'depreciation' && $side === 'credit') {
            $q->where('normal_balance', 'CREDIT');
        }
        if ($kind === 'asset_credit' && $side === 'debit') {
            $q->where('normal_balance', 'DEBIT');
        }
        if ($type === 'ASSET' && Schema::hasTable('cash_bank_accounts')) {
            $q->whereDoesntHave('cashBankAccounts');
        }

        return $q->orderBy('code')->get();
    }

    public function post(array $data, int $actor): JournalEntry
    {
        return DB::transaction(function () use ($data, $actor) {
            DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
            $kind = $data['kind'];
            if (! isset(self::TYPES[$kind])) {
                $this->fail('Jenis pencatatan tidak dikenal.');
            }
            if ($data['journal_date'] > now('Asia/Jakarta')->toDateString()) {
                $this->fail('Tanggal tidak boleh di masa depan.');
            }
            $cents = LedgerAmounts::cents($data['amount']);
            if ($cents <= 0 || $cents > 100000000000000) {
                $this->fail('Nominal harus positif dan maksimal Rp 1 triliun.');
            }
            if ((int) $data['debit_account_id'] === (int) $data['credit_account_id']) {
                $this->fail('Akun debit dan kredit harus berbeda.');
            }
            $description = self::TYPES[$kind]['label'].': '.$data['description'];
            $lines = [['chart_account_id' => (int) $data['debit_account_id'], 'debit' => LedgerAmounts::decimal($cents), 'credit' => '0.00'],
                ['chart_account_id' => (int) $data['credit_account_id'], 'debit' => '0.00', 'credit' => LedgerAmounts::decimal($cents)]];
            $number = 'ACC-'.$data['entry_token'];
            if ($existing = JournalEntry::where('journal_no', $number)->first()) {
                $actual = $existing->lines()->orderBy('id')->get()->map(fn ($l) => ['chart_account_id' => (int) $l->chart_account_id, 'debit' => $l->debit, 'credit' => $l->credit])->all();
                if ((int) $existing->created_by !== $actor || $existing->journal_date->format('Y-m-d') !== $data['journal_date'] || ($existing->reference ?? '') !== ($data['reference'] ?? '') || $existing->description !== $description || $actual !== $lines) {
                    $this->fail('Form ini sudah diposting dengan data berbeda. Buka form baru untuk transaksi lain.');
                }

                return $existing;
            }
            foreach (['debit', 'credit'] as $side) {
                if (! $this->accounts($kind, $side)->contains('id', (int) $data[$side.'_account_id'])) {
                    $this->fail('Akun '.$side.' harus aktif, merupakan akun rincian, dan sesuai jenis pencatatan. Rekening kas/bank dicatat melalui Penerimaan/Pengeluaran.');
                }
            }
            if (in_array($kind, ['asset_credit', 'depreciation'], true) && Schema::hasTable('asset_periods')) {
                $periods = AssetPeriod::whereDate('starts_at', '<=', $data['journal_date'])->whereDate('ends_at', '>=', $data['journal_date'])->lockForUpdate()->get();
                if ($periods->count() !== 1 || $periods->first()->status !== 'OPEN') {
                    $this->fail('Pencatatan aset memerlukan satu periode accounting aktiva berstatus OPEN pada tanggal jurnal. Periksa periode di Inventaris Barang.');
                }
            }
            $journal = JournalEntry::create(['journal_no' => $number, 'journal_date' => $data['journal_date'], 'reference' => $data['reference'] ?? null, 'description' => $description, 'status' => 'POSTED', 'created_by' => $actor]);
            $journal->lines()->createMany($lines);

            return $journal;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['entry' => $message]);
    }
}
