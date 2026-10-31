<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCapitalization;
use App\Models\AssetPeriod;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAccounting
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['accounting' => $message]);
    }

    private function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function period(string $date): void
    {
        $periods = AssetPeriod::whereDate('starts_at', '<=', $date)->whereDate('ends_at', '>=', $date)->lockForUpdate()->get();
        if ($periods->count() !== 1 || $periods->first()->status !== 'OPEN') {
            $this->fail('Tanggal jurnal harus berada pada tepat satu periode aktiva yang terbuka.');
        }
    }

    private function accounts(array $d): void
    {
        $ids = array_map(fn ($f) => (int) $d[$f], ['asset_account_id', 'expense_account_id', 'accumulated_account_id', 'offset_account_id']);
        if (count(array_unique($ids)) !== 4) {
            $this->fail('Keempat akun harus berbeda.');
        }
        foreach (['asset_account_id' => ['ASSET', 'DEBIT'], 'expense_account_id' => ['EXPENSE', 'DEBIT'], 'accumulated_account_id' => ['ASSET', 'CREDIT'], 'offset_account_id' => [null, 'CREDIT']] as $f => [$type,$balance]) {
            $account = ChartAccount::find($d[$f]);
            if (! $account || ! $account->is_active || ($type && $account->type !== $type) || $account->normal_balance !== $balance) {
                $this->fail('Jenis / saldo normal akun '.$f.' tidak valid.');
            }
        }
    }

    public function draft(Asset $asset, array $d, int $actor): AssetCapitalization
    {
        return DB::transaction(function () use ($asset, $d, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            if ($asset->status !== 'ACTIVE' || $asset->inventory_type === 'FACILITY' || $asset->quantity !== 1) {
                $this->fail('Kapitalisasi hanya untuk satu unit inventaris aktif yang teridentifikasi.');
            }
            $existing = $asset->capitalization()->first();
            if ($existing && $existing->status !== 'DRAFT') {
                $this->fail('Kapitalisasi yang sudah disetujui tidak dapat diubah.');
            }
            $this->accounts($d);
            $cost = $this->cents($d['cost']);
            $acc = $this->cents($d['opening_accumulated']);
            $res = $this->cents($d['residual_value']);
            if ($cost <= 0 || $acc < 0 || $res < 0 || $acc > $cost - $res || $cost - $acc !== $this->cents($d['opening_book_value'])) {
                $this->fail('Nilai buku harus sama dengan harga perolehan dikurangi akumulasi, dan tidak kurang dari residu.');
            }
            $before = $existing?->toArray() ?? [];
            $cap = $asset->capitalization()->updateOrCreate(['asset_id' => $asset->id], $d + ['created_by' => $actor]);
            AssetAudit::record($cap, 'CAPITALIZATION_DRAFT', $actor, $before, $cap->toArray());

            return $cap;
        });
    }

    public function approve(Asset $asset, int $actor): void
    {
        DB::transaction(function () use ($asset, $actor) {
            Asset::lockForUpdate()->findOrFail($asset->id);
            $cap = $asset->capitalization()->lockForUpdate()->firstOrFail();
            if ($cap->status !== 'DRAFT') {
                $this->fail('Status kapitalisasi harus DRAFT.');
            }
            $this->accounts($cap->toArray());
            $cap->update(['status' => 'APPROVED', 'approved_by' => $actor, 'approved_at' => now()]);
            AssetAudit::record($cap, 'CAPITALIZATION_APPROVE', $actor);
        });
    }

    private function journal(Asset $asset, string $ref, string $date, string $description, array $lines, int $actor): JournalEntry
    {
        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));
        if (round($debit, 2) !== round($credit, 2) || $debit <= 0) {
            $this->fail('Jurnal tidak seimbang.');
        }
        $journal = JournalEntry::create(['journal_no' => $ref, 'journal_date' => $date, 'reference' => $ref, 'source_type' => Asset::class, 'source_id' => $asset->id, 'description' => $description, 'status' => 'POSTED', 'created_by' => $actor]);
        $journal->lines()->createMany($lines);

        return $journal;
    }

    public function post(Asset $asset, string $date, int $actor): JournalEntry
    {
        return DB::transaction(function () use ($asset, $date, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $cap = $asset->capitalization()->lockForUpdate()->firstOrFail();
            if ($cap->journal_entry_id) {
                return JournalEntry::findOrFail($cap->journal_entry_id);
            }
            if ($cap->status !== 'APPROVED' || $asset->status !== 'ACTIVE') {
                $this->fail('Kapitalisasi harus disetujui dan aset aktif.');
            }
            if ($date < $cap->acquired_at->format('Y-m-d') || $date > $cap->depreciation_starts_at->format('Y-m-d')) {
                $this->fail('Tanggal jurnal harus antara tanggal perolehan dan mulai penyusutan.');
            }
            $this->period($date);
            $this->accounts($cap->toArray());
            $lines = [['chart_account_id' => $cap->asset_account_id, 'debit' => $cap->cost, 'credit' => 0]];
            if ($this->cents($cap->opening_accumulated) > 0) {
                $lines[] = ['chart_account_id' => $cap->accumulated_account_id, 'debit' => 0, 'credit' => $cap->opening_accumulated];
            }
            if ($this->cents($cap->opening_book_value) > 0) {
                $lines[] = ['chart_account_id' => $cap->offset_account_id, 'debit' => 0, 'credit' => $cap->opening_book_value];
            }
            $j = $this->journal($asset, 'AST-CAP-'.$asset->id, $date, 'Kapitalisasi / saldo awal '.$asset->name, $lines, $actor);
            $cap->update(['status' => 'POSTED', 'journal_entry_id' => $j->id]);
            AssetAudit::record($cap, 'CAPITALIZATION_POST', $actor, [], ['journal_id' => $j->id]);

            return $j;
        });
    }

    public function accumulated(Asset $asset): int
    {
        return $this->cents($asset->capitalization?->opening_accumulated ?? 0) + $asset->events()->where('type', 'DEPRECIATION')->get()->sum(fn ($e) => $this->cents($e->data['amount']));
    }

    public function depreciate(Asset $asset, string $month, int $actor): JournalEntry
    {
        return DB::transaction(function () use ($asset, $month, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $cap = $asset->capitalization;
            $ref = 'AST-DEP-'.$asset->id.'-'.$month;
            if ($j = JournalEntry::where('journal_no', $ref)->first()) {
                return $j;
            }
            if (! $cap || $cap->status !== 'POSTED' || $asset->status !== 'ACTIVE') {
                $this->fail('Hanya aktiva resmi aktif dengan kapitalisasi terposting dapat disusutkan.');
            }
            $count = $asset->events()->where('type', 'DEPRECIATION')->count();
            $expected = $cap->depreciation_starts_at->copy()->startOfMonth()->addMonths($count)->format('Y-m');
            if ($month !== $expected || $count >= $cap->useful_life_months) {
                $this->fail('Posting penyusutan berurutan. Bulan berikutnya: '.$expected);
            }
            $date = Carbon::createFromFormat('!Y-m', $month)->endOfMonth()->format('Y-m-d');
            $this->period($date);
            $this->accounts($cap->toArray());
            $remaining = $this->cents($cap->cost) - $this->accumulated($asset) - $this->cents($cap->residual_value);
            $base = $this->cents($cap->opening_book_value) - $this->cents($cap->residual_value);
            $half = intdiv($cap->useful_life_months, 2);
            $amount = min($remaining, intdiv($base * ($count + 1) + $half, $cap->useful_life_months) - intdiv($base * $count + $half, $cap->useful_life_months));
            if ($amount <= 0) {
                $this->fail('Tidak ada nilai yang dapat disusutkan.');
            }
            $value = $this->money($amount);
            $j = $this->journal($asset, $ref, $date, 'Penyusutan garis lurus '.$asset->name, [['chart_account_id' => $cap->expense_account_id, 'debit' => $value, 'credit' => 0], ['chart_account_id' => $cap->accumulated_account_id, 'debit' => 0, 'credit' => $value]], $actor);
            $asset->events()->create(['type' => 'DEPRECIATION', 'event_date' => $date, 'data' => ['amount' => $value, 'journal_id' => $j->id], 'created_by' => $actor]);
            AssetAudit::record($asset, 'DEPRECIATION_POST', $actor, [], ['month' => $month, 'amount' => $value, 'journal_id' => $j->id]);

            return $j;
        });
    }

    public function dispose(Asset $asset, string $date, int $lossAccount, string $note, int $actor): void
    {
        DB::transaction(function () use ($asset, $date, $lossAccount, $note, $actor) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            if ($asset->status === 'DISPOSED') {
                return;
            }
            if (! $asset->events()->where('type', 'DISPOSAL_REQUEST')->exists()) {
                $this->fail('Permintaan pelepasan inventaris diperlukan.');
            }
            $cap = $asset->capitalization;
            if ($cap && $cap->status !== 'POSTED') {
                $this->fail('Selesaikan kapitalisasi sebelum pelepasan.');
            }
            $journal = null;
            if ($cap) {
                $this->period($date);
                $this->accounts($cap->toArray());
                $latest = JournalEntry::where('source_type', Asset::class)->where('source_id', $asset->id)->max('journal_date');
                if ($date < substr($latest, 0, 10)) {
                    $this->fail('Tanggal pelepasan mendahului jurnal aset terakhir.');
                }
                $account = ChartAccount::find($lossAccount);
                if (! $account?->is_active || $account->type !== 'EXPENSE') {
                    $this->fail('Akun rugi pelepasan tidak valid.');
                }
                $acc = $this->accumulated($asset);
                $book = $this->cents($cap->cost) - $acc;
                $lines = [['chart_account_id' => $cap->asset_account_id, 'debit' => 0, 'credit' => $cap->cost]];
                if ($acc) {
                    $lines[] = ['chart_account_id' => $cap->accumulated_account_id, 'debit' => $this->money($acc), 'credit' => 0];
                }
                if ($book) {
                    $lines[] = ['chart_account_id' => $lossAccount, 'debit' => $this->money($book), 'credit' => 0];
                }
                $journal = $this->journal($asset, 'AST-DIS-'.$asset->id, $date, 'Penghapusan tanpa penerimaan '.$asset->name, $lines, $actor);
            }
            $asset->update(['status' => 'DISPOSED']);
            $asset->events()->create(['type' => 'DISPOSAL', 'event_date' => $date, 'data' => ['notes' => $note, 'journal_id' => $journal?->id], 'created_by' => $actor]);
            AssetAudit::record($asset, 'DISPOSE', $actor, [], ['notes' => $note, 'journal_id' => $journal?->id]);
        });
    }
}
