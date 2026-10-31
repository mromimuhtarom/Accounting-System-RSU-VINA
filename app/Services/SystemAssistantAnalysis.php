<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Expense;
use App\Models\InsuranceMonthlyClaim;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Descriptive calculations from application records; never infers business causes. */
class SystemAssistantAnalysis
{
    private function periods(array $data): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? $data['as_of'] ?? null;
        $message = mb_strtolower($data['message'] ?? '');
        if (! $from && ! $to) {
            $months = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];
            if (preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $message, $dates) && count($dates[0])) {
                if (count($dates[0]) > 2) {
                    throw ValidationException::withMessages(['message' => 'Gunakan maksimal dua tanggal atau form Analisis Laporan.']);
                }
                $from = $dates[0][0];
                $to = $dates[0][1] ?? $from;
            } elseif (preg_match('/\b('.implode('|', array_keys($months)).')\s+(20\d{2})\b/u', $message, $m)) {
                $start = CarbonImmutable::create((int) $m[2], $months[$m[1]], 1, 0, 0, 0, 'Asia/Jakarta');
                $from = $start->toDateString();
                $to = min($start->endOfMonth()->toDateString(), $today->toDateString());
            } elseif (preg_match('/\btahun\s+(20\d{2})\b/', $message, $m) || preg_match('/\blaporan tahunan\s+(20\d{2})\b/', $message, $m)) {
                $from = $m[1].'-01-01';
                $to = min($m[1].'-12-31', $today->toDateString());
            } elseif (str_contains($message, 'bulan lalu')) {
                $start = $today->subMonthNoOverflow()->startOfMonth();
                $from = $start->toDateString();
                $to = $start->endOfMonth()->toDateString();
            } elseif (str_contains($message, 'hari ini')) {
                $from = $to = $today->toDateString();
            }
        }
        $to ??= $today->toDateString();
        $from ??= CarbonImmutable::parse($to)->startOfMonth()->toDateString();
        validator(['from' => $from, 'to' => $to], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from|before_or_equal:'.$today->toDateString()])->validate();
        $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        if ($days > 366) {
            throw ValidationException::withMessages(['from' => 'Analisis maksimal 366 hari per permintaan.']);
        }
        $previousTo = CarbonImmutable::parse($from)->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        return [$from, $to, $previousFrom->toDateString(), $previousTo->toDateString()];
    }

    private function change(string $label, int $current, int $previous): string
    {
        $delta = $current - $previous;
        $text = $label.' '.($delta === 0 ? 'tetap' : ($delta > 0 ? 'naik' : 'turun')).($delta === 0 ? '' : ' Rp '.LedgerAmounts::display(abs($delta))).' dibanding periode pembanding.';
        if ($previous > 0) {
            $text .= ' Perubahan '.number_format(100 * $delta / $previous, 2, ',', '.').'%.';
        } elseif ($delta !== 0) {
            $text .= ' Persentase tidak dihitung karena nilai pembanding tidak positif.';
        }

        return $text;
    }

    private function chart(string $title, array $values, bool $money = true, string $basis = ''): array
    {
        $items = [];
        foreach ($values as $label => $value) {
            $items[] = ['label' => $label, 'value' => $money ? LedgerAmounts::decimal($value) : (string) $value, 'formatted' => $money ? 'Rp '.LedgerAmounts::display($value) : number_format($value).' catatan'];
        }

        return ['title' => $title, 'basis' => $basis, 'items' => $items];
    }

    private function reply(string $answer, array $steps, array $charts, array $links, string $notice): array
    {
        return compact('answer', 'steps', 'charts', 'links', 'notice') + ['kind' => 'analysis', 'results' => []];
    }

    public function run(User $user, array $data): array
    {
        $message = mb_strtolower($data['message'] ?? '');
        $context = $data['context'] ?? '';
        $resource = $data['resource'] ?? match (true) {
            (bool) preg_match('/inventaris|barang|alat|kalibrasi/u', $message) => 'assets',(bool) preg_match('/klaim|asuransi/u', $message) => 'claims',(bool) preg_match('/neraca|pasiva|ekuitas|saldo akun/u', $message) => 'balance',str_starts_with($context, 'assets.') => 'assets',str_starts_with($context, 'insurance.') && ! str_contains($context, 'receipts') => 'claims',str_contains($context, 'financial-position') || str_contains($context, 'financial-trial') || str_contains($context, 'financial-ledger') || $context === 'accounting.analysis' => 'balance',default => 'transactions'
        };
        abort_unless(isset(app(SystemAssistantAccess::class)->analyses($user)[$resource]), 403, 'Analisis ini tidak tersedia untuk hak akses Anda.');
        if ($resource === 'assets') {
            return $this->assets();
        }
        [$from,$to,$previousFrom,$previousTo] = $this->periods($data);
        if ($resource === 'balance') {
            $a = app(AccountingAnalysis::class)->period($from, $to);
            $p = $a['end'];
            $steps = [];
            foreach (['assets' => 'Aset', 'liabilities' => 'Kewajiban', 'equity' => 'Ekuitas'] as $key => $label) {
                $steps[] = $this->change($label, $p[$key], $a['start'][$key]);
            }
            $steps[] = $p['journal_count'].' jurnal kumulatif sampai '.$to.'. Selisih aset-pasiva Rp '.LedgerAmounts::display($p['difference']).'.';
            if (! $p['opening'] || $p['opening']->status !== 'POSTED') {
                $steps[] = 'Saldo awal belum terposting; posisi keuangan belum lengkap.';
            }
            if ($a['start']['before_opening'] || ! $a['start']['opening'] || $a['start']['opening']->status !== 'POSTED') {
                $steps[] = 'Saldo sebelum periode belum didukung saldo awal terposting pada tanggal tersebut.';
            }
            if ($p['equity'] < 0) {
                $steps[] = 'Ekuitas negatif pada buku ini; periksa kelengkapan saldo awal dan jurnal sebelum menyimpulkan penyebabnya.';
            }

            return $this->reply($p['journal_count'] ? 'Analisis neraca '.$from.' sampai '.$to.'. Pembanding adalah posisi akhir '.$a['before'].'.' : 'Belum ada jurnal terposting sampai '.$to.'; analisis neraca lengkap belum tersedia.', $steps,
                [$this->chart('Posisi akhir', ['Aset' => $p['assets'], 'Kewajiban' => $p['liabilities'], 'Ekuitas' => $p['equity']], true, 'Saldo kumulatif jurnal POSTED, bukan jumlah inventaris.')],
                [['label' => 'Analisis Saldo Akun', 'url' => route('accounting.analysis', compact('from', 'to'))], ['label' => 'Neraca', 'url' => route('reports.financial-position', ['as_of' => $to])]], 'Perubahan mencakup jurnal saldo awal, penyesuaian dan penutupan yang ada dalam periode. Asisten menjelaskan angka, bukan menebak penyebab bisnis.');
        }
        if ($resource === 'claims') {
            return $this->claims($from, $to);
        }
        if (! Schema::hasTable('receipts') || ! Schema::hasTable('expenses')) {
            return $this->reply('Data transaksi belum tersedia.', [], [], [], 'Tidak ada angka yang diperkirakan.');
        }
        $receipt = Receipt::where('status', 'ACTIVE');
        $expense = Expense::where('status', 'ACTIVE');
        $sum = fn ($q, $date, $start, $end) => LedgerAmounts::cents((clone $q)->whereDate($date, '>=', $start)->whereDate($date, '<=', $end)->sum('amount'));
        $income = $sum($receipt, 'transaction_date', $from, $to);
        $out = $sum($expense, 'expense_date', $from, $to);
        $oldIncome = $sum($receipt, 'transaction_date', $previousFrom, $previousTo);
        $oldOut = $sum($expense, 'expense_date', $previousFrom, $previousTo);
        $current = (clone $receipt)->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to);
        $currentOut = (clone $expense)->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to);
        $n = (clone $current)->count();
        $nOut = (clone $currentOut)->count();
        $unknown = Schema::hasColumn('receipts', 'payment_method') ? (clone $current)->whereNull('payment_method')->count() : $n;
        $unposted = (clone $current)->whereDoesntHave('journal')->count();
        $unpostedOut = (clone $currentOut)->whereDoesntHave('journal')->count();
        $steps = ['Penerimaan tercatat Rp '.LedgerAmounts::display($income).' dari '.number_format($n).' transaksi aktif; pengeluaran Rp '.LedgerAmounts::display($out).' dari '.number_format($nOut).' transaksi aktif.',
            $this->change('Penerimaan', $income, $oldIncome), $this->change('Pengeluaran', $out, $oldOut),
            'Selisih penerimaan-pengeluaran Rp '.LedgerAmounts::display($income - $out).'. Ini bukan saldo kas akhir atau laba.',
            $unknown.' penerimaan memiliki metode pembayaran belum diketahui. '.$unposted.' penerimaan dan '.$unpostedOut.' pengeluaran belum memiliki tautan jurnal; rekonsiliasi saldo awal/jurnal manual sebelum memposting ulang.'];
        if ($n + $nOut === 0) {
            $steps[] = 'Tidak ada transaksi aktif pada periode ini. Ketiadaan catatan belum membuktikan tidak adanya aktivitas bisnis.';
        }
        $monthly = (clone $current)->selectRaw('SUBSTR(transaction_date,1,7) AS period, SUM(amount) AS total')->groupBy('period')->orderBy('period')->get()->keyBy('period');
        $monthlyOut = (clone $currentOut)->selectRaw('SUBSTR(expense_date,1,7) AS period, SUM(amount) AS total')->groupBy('period')->orderBy('period')->get()->keyBy('period');
        $values = [];
        $expenseValues = [];
        $cursor = CarbonImmutable::parse($from)->startOfMonth();
        $last = CarbonImmutable::parse($to)->startOfMonth();
        while ($cursor <= $last) {
            $key = $cursor->format('Y-m');
            $values[$key] = LedgerAmounts::cents($monthly->get($key)?->total ?? 0);
            $expenseValues[$key] = LedgerAmounts::cents($monthlyOut->get($key)?->total ?? 0);
            $cursor = $cursor->addMonth();
        }
        $charts = [$this->chart('Perbandingan nilai tercatat', ['Penerimaan pembanding' => $oldIncome, 'Penerimaan periode ini' => $income, 'Pengeluaran pembanding' => $oldOut, 'Pengeluaran periode ini' => $out], true, 'Transaksi ACTIVE; pengeluaran mencakup seluruh jenis, termasuk referal.')];
        if ($values) {
            $charts[] = $this->chart('Penerimaan per bulan dalam periode', $values, true, 'Bulan awal/akhir dapat berupa bulan parsial sesuai filter tanggal.');
            $charts[] = $this->chart('Pengeluaran per bulan dalam periode', $expenseValues, true, 'Termasuk seluruh jenis pengeluaran aktif; bulan tanpa catatan ditampilkan nol.');
        }

        return $this->reply('Analisis laporan transaksi '.$from.' sampai '.$to.'. Pembanding '.$previousFrom.' sampai '.$previousTo.' (durasi sama).', $steps, $charts,
            [['label' => 'Laporan Penerimaan', 'url' => route('reports.income', ['date_from' => $from, 'date_to' => $to])], ['label' => 'Laporan Pengeluaran', 'url' => route('reports.expense', ['date_from' => $from, 'date_to' => $to])], ['label' => 'Periksa Sumber Data', 'url' => route('reports.financial-sources', ['as_of' => $to])]],
            'Cakupan seluruh transaksi aktif sesuai periode; filter akun, penjamin, unit atau kata kunci halaman tidak diterapkan. Bukan neraca atau bukti kas sudah cair. Metode kosong/GUARANTOR tidak otomatis dianggap kas. Periode berjalan bisa belum lengkap. Tidak ada penyebab kenaikan/penurunan yang diasumsikan.');
    }

    private function assets(): array
    {
        if (! Schema::hasTable('assets')) {
            return $this->reply('Inventaris belum tersedia.', [], [], [], 'Tidak ada angka yang diperkirakan.');
        }
        $q = Asset::where('status', 'ACTIVE');
        $count = (clone $q)->count();
        $broken = (clone $q)->where('condition', 'BROKEN')->count();
        $facility = (clone $q)->where('inventory_type', 'FACILITY')->count();
        $units = (int) (clone $q)->where('inventory_type', '!=', 'FACILITY')->sum('quantity');
        $today = CarbonImmutable::now('Asia/Jakarta');
        $due = (clone $q)->where('calibration_required', true)->whereNotNull('calibration_due_at')->whereDate('calibration_due_at', '<=', $today->addDays(30)->toDateString())->count();
        $types = [];
        foreach ((clone $q)->select('inventory_type')->selectRaw('COUNT(*) total')->groupBy('inventory_type')->get() as $row) {
            $types[Asset::TYPES[$row->inventory_type] ?? $row->inventory_type] = (int) $row->total;
        }

        return $this->reply('Analisis inventaris terkini per '.$today->toDateString().'.', ['Terdapat '.number_format($count).' catatan aktif; '.number_format($facility).' di antaranya fasilitas/kapasitas.', 'Jumlah unit nonfasilitas tercatat '.number_format($units).'; unit agregat berbeda dari jumlah catatan.', $broken.' catatan berkondisi rusak. '.$due.' catatan memerlukan kalibrasi dan sudah/mendekati jatuh tempo dalam 30 hari.'], [$this->chart('Inventaris per jenis', $types, false, 'Catatan aktif terkini; bukan posisi historis pada tanggal laporan.')], [['label' => 'Inventaris Barang', 'url' => route('assets.index')]], 'Harga sementara dan jumlah inventaris tidak otomatis menjadi nilai aktiva neraca. Data terhapus/sudah dilepas tidak disertakan.');
    }

    private function claims(string $from, string $to): array
    {
        if (! Schema::hasTable('insurance_monthly_claims')) {
            return $this->reply('Tracking klaim belum tersedia.', [], [], [], 'Tidak ada angka yang diperkirakan.');
        }
        $q = InsuranceMonthlyClaim::whereRaw('year * 100 + month >= ?', [(int) substr($from, 0, 4) * 100 + (int) substr($from, 5, 2)])->whereRaw('year * 100 + month <= ?', [(int) substr($to, 0, 4) * 100 + (int) substr($to, 5, 2)]);
        $claimed = 0;
        $approved = 0;
        $paid = 0;
        $remaining = 0;
        $count = 0;
        $unverified = 0;
        $invalid = 0;
        foreach ($q->cursor() as $c) {
            $count++;
            $cl = LedgerAmounts::cents($c->claimed_amount);
            $ap = LedgerAmounts::cents($c->approved_amount ?? 0);
            $pa = LedgerAmounts::cents($c->paid_amount);
            $claimed += $cl;
            $paid += $pa;
            if ($c->approved_amount === null) {
                $unverified++;
            }if ($pa > $ap || $ap > $cl || min($cl, $ap, $pa) < 0) {
                $invalid++;
            }if ($c->status !== 'DITOLAK' && $c->approved_amount !== null) {
                $approved += $ap;
                $remaining += max(0, $ap - $pa);
            }
        }

        return $this->reply('Analisis tracking terkini untuk periode klaim '.substr($from, 0, 7).' sampai '.substr($to, 0, 7).'.', [$count.' catatan klaim; '.$unverified.' belum memiliki nilai persetujuan.', 'Sisa disetujui belum cair (selain ditolak) Rp '.LedgerAmounts::display($remaining).'.', $invalid.' catatan memiliki anomali nominal yang perlu diperiksa.'], [$this->chart('Nilai tracking', ['Diajukan' => $claimed, 'Disetujui selain ditolak' => $approved, 'Sudah cair (informasi)' => $paid, 'Sisa disetujui' => $remaining], true, 'Ukuran berbeda dan saling berkaitan; bukan komponen yang dijumlahkan.')], [['label' => 'Tracking Klaim Asuransi', 'url' => route('insurance.index')]], 'Nilai/status adalah kondisi terkini pada periode klaim, bukan riwayat piutang atau pencairan per tanggal. Pembayaran tetap dicatat manual di Penerimaan.');
    }
}
