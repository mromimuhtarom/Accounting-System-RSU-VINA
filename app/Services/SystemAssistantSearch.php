<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\Guarantor;
use App\Models\InsuranceMonthlyClaim;
use App\Models\OpeningBalance;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SystemAssistantSearch
{
    private function like($query, array $columns, string $term): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $query->where(function ($q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhereRaw('LOWER('.$column.') LIKE ? ESCAPE \'!\' ', [$pattern]);
            }
        });
    }

    public function run(User $user, array $data): array
    {
        $access = app(SystemAssistantAccess::class);
        $resource = $data['resource'] ?? '';
        if ($resource === 'balance') {
            abort_unless($user->can('viewAny', OpeningBalance::class), 403, 'Ringkasan neraca tidak tersedia untuk akun ini.');
            $date = $data['as_of'] ?? now('Asia/Jakarta')->toDateString();
            $p = app(FinancialPosition::class)->at($date);
            $answer = $p['journal_count'] ? 'Neraca per '.$date.': Aset Rp '.LedgerAmounts::display($p['assets']).'; Kewajiban Rp '.LedgerAmounts::display($p['liabilities']).'; Ekuitas Rp '.LedgerAmounts::display($p['equity']).'.' : 'Belum ada jurnal terposting sampai '.$date.'. Angka neraca belum tersedia; ini bukan berarti rumah sakit tidak memiliki aset.';
            $notice = (! $p['opening'] || $p['opening']->status !== 'POSTED') ? 'Saldo awal belum terposting; posisi keuangan belum lengkap.' : ($p['before_opening'] ? 'Tanggal ini mendahului saldo awal. Posisi lengkap belum tersedia.' : 'Saldo dari jurnal terposting; cocokkan dengan dokumen.');
            if ($p['difference'] !== 0) {
                $notice .= ' Ada selisih Rp '.LedgerAmounts::display($p['difference']).'.';
            }

            return ['kind' => 'summary', 'answer' => $answer, 'notice' => $notice, 'links' => [['label' => 'Buka Neraca', 'url' => route('reports.financial-position', ['as_of' => $date])]], 'results' => []];
        }
        abort_unless(isset($access->resources($user)[$resource]), 403, 'Pencarian data ini tidak tersedia untuk akun Anda.');
        $term = trim($data['term'] ?? '');
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        if (in_array($resource, ['assets', 'accounts'], true) && ($from || $to)) {
            throw ValidationException::withMessages(['from' => 'Inventaris dan COA adalah data terkini. Gunakan nama, kode atau lokasi tanpa filter tanggal.']);
        }
        $results = [];
        $notice = 'Maksimal 5 hasil. Asisten tidak mengubah data.';
        $sum = null;
        if (in_array($resource, ['receipts', 'expenses'], true)) {
            $receipt = $resource === 'receipts';
            $table = $receipt ? 'receipts' : 'expenses';
            if (! Schema::hasTable($table)) {
                return $this->empty();
            }
            $q = $receipt ? Receipt::query() : Expense::query();
            $q->where('status', 'ACTIVE');
            if (! $receipt) {
                $q->where('expense_type', 'GENERAL');
            }
            if ($receipt && $user->accountingRole() === 'ASURANSI') {
                $q->whereHas('guarantor', fn ($g) => $g->whereIn('category', Guarantor::INSURANCE_CATEGORIES));
            }
            $dateColumn = $receipt ? 'transaction_date' : 'expense_date';
            if ($from) {
                $q->whereDate($dateColumn, '>=', $from);
            }
            if ($to) {
                $q->whereDate($dateColumn, '<=', $to);
            }
            if ($term !== '') {
                $this->like($q, $receipt ? ['transaction_no', 'income_code', 'notes'] : ['expense_no', 'description', 'reference_no'], $term);
            }
            $count = (clone $q)->count();
            if (($data['mode'] ?? '') === 'summary') {
                $sum = LedgerAmounts::cents((clone $q)->sum('amount'));
            }
            foreach ($q->orderByDesc($dateColumn)->orderByDesc('id')->limit(5)->get() as $r) {
                $ref = $receipt ? $r->transaction_no : $r->expense_no;
                $date = $r->{$dateColumn}->format('Y-m-d');
                $route = $receipt ? $access->receiptRoute($user) : $access->expenseRoute($user);
                $params = ['date_from' => $date, 'date_to' => $date, ($receipt ? 'search' : 'q') => $ref];
                $results[] = ['title' => $ref, 'detail' => $date.' · Rp '.LedgerAmounts::display(LedgerAmounts::cents($r->amount)).' · Aktif', 'url' => route($route, $params)];
            }
            $notice = 'Hanya transaksi aktif'.($receipt ? ' penerimaan' : ' pengeluaran umum').'.'.($user->accountingRole() === 'ASURANSI' ? ' Dibatasi penjamin kategori asuransi.' : '').' Nominal tercatat bukan saldo kas atau laba. Tautan membuka daftar pada tanggal transaksi; hasil mengikuti filter halaman tujuan.';
        } elseif ($resource === 'assets') {
            if (! Schema::hasTable('assets')) {
                return $this->empty();
            }
            $q = Asset::query();
            if (preg_match('/^AST-(\d+)$/i', $term, $m)) {
                $q->whereKey((int) $m[1]);
            } elseif ($term !== '') {
                $this->like($q, ['name', 'code', 'serial_number', 'location'], $term);
            }
            $count = (clone $q)->count();
            foreach ($q->latest('id')->limit(5)->get() as $a) {
                $results[] = ['title' => 'AST-'.$a->id.' · '.$a->name, 'detail' => ($a->location ?: 'Lokasi belum diketahui').' · '.(Asset::CONDITIONS[$a->condition] ?? $a->condition), 'url' => route('assets.show', $a)];
            }
            $notice = 'Inventaris terkini, tidak termasuk data yang sudah dihapus. Harga sementara tidak ditambahkan ke neraca.';
        } elseif ($resource === 'claims') {
            if (! Schema::hasTable('insurance_monthly_claims')) {
                return $this->empty();
            }
            $q = InsuranceMonthlyClaim::with('guarantor');
            if ($from) {
                $q->whereRaw('year * 100 + month >= ?', [(int) substr($from, 0, 4) * 100 + (int) substr($from, 5, 2)]);
            }
            if ($to) {
                $q->whereRaw('year * 100 + month <= ?', [(int) substr($to, 0, 4) * 100 + (int) substr($to, 5, 2)]);
            }
            if ($term !== '') {
                $q->where(function ($s) use ($term) {
                    $this->like($s, ['status'], $term);
                    $s->orWhereHas('guarantor', function ($g) use ($term) {
                        $this->like($g, ['name', 'code'], $term);
                    });
                });
            }
            $count = (clone $q)->count();
            foreach ($q->orderByDesc('year')->orderByDesc('month')->orderByDesc('id')->limit(5)->get() as $c) {
                $results[] = ['title' => ($c->guarantor?->name ?? 'Penjamin tidak ditemukan').' · '.sprintf('%02d/%d', $c->month, $c->year), 'detail' => $c->service_type.' · '.$c->status.' · Diajukan Rp '.LedgerAmounts::display(LedgerAmounts::cents($c->claimed_amount)), 'url' => route('insurance.index', ['year' => $c->year, 'month' => $c->month, 'guarantor_id' => $c->guarantor_id])];
            }
            $notice = 'Tracking kondisi terkini untuk periode klaim terpilih, bukan saldo piutang historis. Pencairan tetap diinput manual di Penerimaan.';
        } else {
            $q = ChartAccount::where('is_active', true);
            if ($term !== '') {
                $this->like($q, ['code', 'name'], $term);
            }
            $count = (clone $q)->count();
            foreach ($q->orderBy('code')->limit(5)->get() as $a) {
                $url = $user->can('viewAny', OpeningBalance::class) ? route('reports.financial-ledger', ['account' => $a->id]) : route('accounting.master.index', 'accounts');
                $results[] = ['title' => $a->code.' · '.$a->name, 'detail' => $a->type.' · Saldo normal '.$a->normal_balance, 'url' => $url];
            }
            $notice = 'Akun aktif. Buka akun untuk menelusuri saldo/jurnal jika akun Anda memiliki akses pembukuan.';
        }
        $period = in_array($resource, ['receipts', 'expenses', 'claims'], true) ? ' Periode: '.($from ?? 'awal data').' sampai '.($to ?? 'akhir data').'.' : '';

        return ['kind' => 'search', 'answer' => $count ? 'Ditemukan '.number_format($count).' catatan; ditampilkan '.count($results).' hasil pertama'.($term !== '' ? ' untuk “'.$term.'”' : ' terbaru').'.'.$period.($sum !== null ? ' Total tercatat sesuai filter: Rp '.LedgerAmounts::display($sum).'.' : '') : 'Tidak ada data sesuai pencarian dan hak akses ini. Coba kode/nama lain atau ubah periode.',
            'notice' => $notice, 'results' => $results, 'links' => [], 'total' => $count];
    }

    private function empty(): array
    {
        return ['kind' => 'search', 'answer' => 'Sumber data belum tersedia pada instalasi ini.', 'results' => [], 'links' => []];
    }
}
