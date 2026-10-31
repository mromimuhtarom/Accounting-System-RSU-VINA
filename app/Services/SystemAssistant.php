<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\OpeningBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Local intent matching and a fixed help catalogue; no generative model or external data transfer. */
class SystemAssistant
{
    private function response(string $answer, array $steps = [], array $links = []): array
    {
        return ['kind' => 'guide', 'answer' => $answer, 'steps' => $steps, 'links' => $links, 'results' => [], 'notice' => 'Panduan lokal aplikasi. Asisten tidak mengubah atau memposting data.'];
    }

    public function answer(User $user, array $data): array
    {
        $access = app(SystemAssistantAccess::class);
        $message = mb_strtolower(trim($data['message'] ?? ''));
        $message = preg_replace('/^(?:tolong|mohon|bisakah|bisa)\s+/u', '', $message);
        $message = preg_replace('/^berapa(?:\s+total)?\s+/u', 'total ', $message);
        $mode = $data['mode'];
        if ($mode === 'analysis' || $mode === 'auto' && preg_match('/^(analisis|analisa|bandingkan|grafik|laporan|baca laporan|baca grafik|lihat grafik|jelaskan laporan|jelaskan grafik|ringkasan laporan)\b/u', $message)) {
            return app(SystemAssistantAnalysis::class)->run($user, array_replace($data, ['message' => $message]));
        }
        if (in_array($mode, ['search', 'summary'], true)) {
            return app(SystemAssistantSearch::class)->run($user, $data);
        }
        if (preg_match('/\b(hapuskan|delete|jalankan sql|drop table|update database|postingkan)\b/u', $message)) {
            return $this->response('Asisten hanya membaca data dan memberi arahan. Pengubahan, penghapusan, atau posting harus melalui halaman aplikasi dan kewenangan petugas.');
        }
        if ($mode === 'auto' && preg_match('/^(cari(?:kan)?|temukan|tampilkan data|lihat data|total|ringkasan)\b/u', $message)) {
            $resource = match (true) {
                (bool) preg_match('/\b(penerimaan|pemasukan|uang masuk)\b/u', $message) => 'receipts',
                (bool) preg_match('/\b(pengeluaran|uang keluar)\b/u', $message) => 'expenses',
                (bool) preg_match('/\b(inventaris|barang|alat|aktiva)\b/u', $message) => 'assets',
                (bool) preg_match('/\b(klaim|asuransi)\b/u', $message) => 'claims',
                (bool) preg_match('/\b(akun|coa)\b/u', $message) => 'accounts',
                (bool) preg_match('/\b(neraca|aset|pasiva)\b/u', $message) => 'balance',
                default => null,
            };
            if ($resource) {
                $parsed = $data;
                $parsed['resource'] = $resource;
                $parsed['mode'] = preg_match('/^(total|ringkasan)\b/u', $message) ? 'summary' : 'search';
                $term = preg_replace('/^(carikan|cari|temukan|tampilkan data|lihat data|total|ringkasan)(?:\s+data)?\s*/u', '', $message);
                $term = preg_replace('/\b(penerimaan|pemasukan|uang masuk|pengeluaran|uang keluar|inventaris barang|inventaris|barang|alat|aktiva|klaim asuransi|klaim|asuransi|akun|coa|neraca|aset|pasiva)\b/u', '', $term, 1);
                $today = CarbonImmutable::now('Asia/Jakarta');
                $months = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];
                if (preg_match('/\b('.implode('|', array_keys($months)).')\s+(20\d{2})\b/u', $term, $month)) {
                    $start = CarbonImmutable::create((int) $month[2], $months[$month[1]], 1, 0, 0, 0, 'Asia/Jakarta');
                    $parsed['from'] = $start->toDateString();
                    $parsed['to'] = $start->endOfMonth()->toDateString();
                    $term = str_replace($month[0], '', $term);
                }
                if (preg_match('/\b(hari ini|bulan ini|bulan lalu)\b/u', $term, $m)) {
                    $start = match ($m[1]) {
                        'hari ini' => $today,'bulan ini' => $today->startOfMonth(),default => $today->subMonthNoOverflow()->startOfMonth()
                    };
                    $parsed['from'] = $start->toDateString();
                    $parsed['to'] = $m[1] === 'bulan lalu' ? $start->endOfMonth()->toDateString() : $today->toDateString();
                    $term = str_replace($m[1], '', $term);
                }
                if (preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $term, $dates)) {
                    if (count($dates[0]) > 2) {
                        throw ValidationException::withMessages(['message' => 'Gunakan satu tanggal atau rentang dua tanggal YYYY-MM-DD.']);
                    }
                    validator(['dates' => $dates[0]], ['dates.*' => 'date_format:Y-m-d'])->validate();
                    $parsed['from'] = $dates[0][0];
                    $parsed['to'] = $dates[0][1] ?? $dates[0][0];
                    if ($parsed['from'] > $parsed['to']) {
                        throw ValidationException::withMessages(['message' => 'Tanggal awal harus sebelum atau sama dengan tanggal akhir.']);
                    }
                    $term = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', '', $term);
                }
                $parsed['term'] = trim(preg_replace('/\b(dari|sampai|hingga|tanggal)\b/u', '', $term), " \t\n\r\0\x0B:,.\"'");
                if (mb_strlen($parsed['term']) > 120) {
                    throw ValidationException::withMessages(['message' => 'Kata pencarian maksimal 120 karakter.']);
                }
                if ($resource === 'balance' && isset($parsed['to'])) {
                    if ($parsed['to'] > $today->toDateString()) {
                        throw ValidationException::withMessages(['message' => 'Neraca tidak dapat diminta untuk tanggal masa depan.']);
                    }
                    $parsed['as_of'] = $parsed['to'];
                }

                return app(SystemAssistantSearch::class)->run($user, $parsed);
            }

            return $this->response('Sebutkan jenis data yang dicari, atau gunakan bagian Cari Data.', ['Contoh: cari pemasukan XLS-IN-20260731', 'Contoh: cari inventaris AST-768', 'Untuk tanggal, gunakan YYYY-MM-DD atau kolom Dari/Sampai pada Cari Data.']);
        }
        $context = $data['context'] ?? '';
        if (preg_match('/teks besar|tulisan|kontras|ukuran teks|navigasi|cari menu/u', $message)) {
            return $this->response('Pengaturan Tampilan ada di atas halaman. Pilihan ini tersimpan pada browser Anda.', ['Pilih Teks besar bila sulit membaca, atau Teks standar untuk layar laptop yang lebih ringkas.', 'Pilih Kontras tinggi jika warna kurang jelas. Atur ulang tampilan mengembalikan pilihan standar.', 'Gunakan Cari menu di kiri, misalnya ketik pemasukan atau neraca. Hapus pencarian untuk menampilkan semua menu.', 'Di ponsel tekan Menu, lalu Tutup menu untuk kembali ke halaman.'], [['label' => 'Dashboard', 'url' => route('dashboard')]]);
        }
        if (preg_match('/export|ekspor|pdf|excel|laporan bulanan|laporan tahunan/u', $message)) {
            $links = [['label' => 'Panduan PDF', 'url' => route('assistant.guide')]];
            if ($user->can('viewAny', OpeningBalance::class)) {
                $links[] = ['label' => 'Ruang Kerja Accounting', 'url' => route('accounting.workspace')];
            }

            return $this->response('Gunakan tombol ekspor pada halaman data atau laporan yang boleh Anda buka. Tidak semua halaman menyediakan format yang sama.', ['Pilih periode dan filter terlebih dahulu, lalu Tampilkan.', 'Neraca, Neraca Saldo, dan Analisis Saldo menyediakan Export PDF.', 'Daftar Penerimaan dan Inventaris menyediakan Export Excel sesuai filter yang tersedia.', 'Setelah diunduh, buka file dan periksa periode, nominal, serta kelengkapan halaman sebelum dibagikan.'], $links);
        }
        if (preg_match('/halaman ini|bantu saya/u', $message) && ! preg_match('/neraca|penerimaan|pemasukan|pengeluaran|inventaris|klaim/u', $message)) {
            $message = match (true) {
                str_contains($context, 'receipts') => 'cara penerimaan',str_contains($context, 'pengeluaran') || str_contains($context, 'expenses') => 'cara pengeluaran',str_starts_with($context, 'assets.') => 'cara inventaris',str_starts_with($context, 'insurance.') => 'cara klaim',str_starts_with($context, 'opening-balances.') => 'cara saldo awal',str_contains($context, 'financial-') || $context === 'accounting.analysis' => 'cara neraca',default => 'cara ruang kerja'
            };
        }
        if (preg_match('/saldo awal/u', $message)) {
            abort_unless($user->can('viewAny', OpeningBalance::class), 403);

            return $this->response('Saldo awal masuk neraca setelah disetujui dan diposting. Draft saja belum memengaruhi neraca.', ['Buka Ruang Kerja Accounting → Periksa Saldo Awal.', 'Pilih tanggal sebelum jurnal pertama; isi akun dan saldo sesuai bukti, bukan angka contoh.', 'Pastikan debit = kredit, lalu simpan draft.', 'Administrator Accounting/Kepala Keuangan menyetujui; Accounting memposting.', 'Buka neraca pada atau setelah tanggal saldo awal. Jangan mengulang saldo awal setiap bulan.'], [['label' => 'Periksa Saldo Awal', 'url' => route('opening-balances.index')]]);
        }
        if (preg_match('/neraca|pasiva|ekuitas|jurnal|nonkas|utang|modal|analisis/u', $message)) {
            abort_unless($user->can('viewAny', OpeningBalance::class), 403);

            return $this->response('Neraca membaca jurnal POSTED. Aset = Kewajiban + Ekuitas; pasiva mencakup kewajiban dan ekuitas.', ['Mulai dari Ruang Kerja Accounting: input, periksa kelengkapan, lalu analisis.', 'Saldo awal perlu approval dan posting. Transaksi yang hanya tersimpan belum selalu masuk neraca.', 'Pilih tanggal neraca pada atau setelah tanggal jurnal; klik akun untuk melihat sumbernya.', 'Form Pemasukan buku kas saat ini belum menyediakan pilihan metode pembayaran dan kas/bank; minta pemeriksaan mapping bila jurnal belum terbentuk.', 'Klaim tracking dan harga sementara inventaris tidak ditambahkan langsung. Jangan memposting ulang pencatatan lama.'], [['label' => 'Ruang Kerja Accounting', 'url' => route('accounting.workspace')], ['label' => 'Buka Neraca', 'url' => route('reports.financial-position')], ['label' => 'Analisis Saldo Akun', 'url' => route('accounting.analysis')]]);
        }
        if (preg_match('/penerimaan|pemasukan|uang masuk/u', $message)) {
            abort_unless(isset($access->resources($user)['receipts']), 403);
            if (! $access->role($user, ['KASIR', 'ASURANSI', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN'])) {
                return $this->response('Akun Anda dapat membaca laporan penerimaan. Input atau perubahan dilakukan petugas yang berwenang.', ['Buka laporan penerimaan dan pilih rentang tanggal.', 'Periksa referensi, nominal, dan periode; cocokkan dengan bukti.', 'Hubungi Accounting untuk input atau koreksi.'], [['label' => 'Laporan Penerimaan', 'url' => route($access->receiptRoute($user))]]);
            }

            return $this->response('Penerimaan digunakan untuk uang yang benar-benar masuk, termasuk pencairan asuransi yang diinput manual.', ['Cari dahulu nomor bukti/tanggal agar tidak mencatat dua kali.', 'Buka Penerimaan, pilih Input Pemasukan; isi tanggal, COA, penjamin, keterangan, dan nominal sesuai form.', 'Periksa bukti, lalu Simpan Pemasukan dan pastikan pesan berhasil muncul.', 'Tersimpan belum selalu berarti dijurnal. Mapping pembayaran/akun yang belum tersedia perlu diperiksa Accounting.'], [['label' => 'Buka Penerimaan', 'url' => route($access->receiptRoute($user))]]);
        }
        if (preg_match('/pengeluaran|uang keluar|biaya/u', $message)) {
            abort_unless(isset($access->resources($user)['expenses']), 403);
            if (! $access->role($user, ['KASIR', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN'])) {
                return $this->response('Akun Anda dapat membaca laporan pengeluaran. Input atau perubahan dilakukan petugas yang berwenang.', ['Pilih rentang tanggal pada laporan.', 'Cocokkan kategori, nominal dan bukti; minta Accounting menindaklanjuti bila ada perbedaan.'], [['label' => 'Laporan Pengeluaran', 'url' => route($access->expenseRoute($user))]]);
            }

            return $this->response('Pengeluaran digunakan untuk uang yang sudah dibayarkan. Tagihan belum dibayar adalah pencatatan nonkas yang perlu verifikasi Accounting.', ['Buka Pengeluaran lalu Tambah Pengeluaran.', 'Isi tanggal, kategori, keterangan, nominal, cara pembayaran dan sumber Kas/Bank.', 'Cocokkan dengan bukti, lalu Simpan Pengeluaran.', 'Periksa status jurnal sebelum menganggapnya masuk neraca; hindari mencatat pelunasan utang sebagai beban kedua.'], [['label' => 'Buka Pengeluaran', 'url' => route($access->expenseRoute($user))]]);
        }
        if (preg_match('/inventaris|barang|aktiva|kalibrasi|aset tetap/u', $message)) {
            abort_unless($user->can('viewAny', Asset::class), 403);

            return $this->response('Inventaris untuk identitas barang, lokasi, kondisi, penanggung jawab dan pemeliharaan. Nilai sementara bukan otomatis aset neraca.', ['Cari barang melalui nama, kode, serial, lokasi atau AST-ID.', 'Petugas berwenang memilih Tambah Barang atau Edit pada daftar/detail.', 'Lengkapi identitas, lokasi, kondisi, jumlah, dasar pencatatan, dan kalibrasi bila perlu.', 'Kapitalisasi/penyusutan diproses terpisah oleh Accounting setelah verifikasi, approval dan periode valid.', 'Hapus/edit mengikuti status dan hak akses; asisten tidak dapat melakukannya.'], [['label' => 'Inventaris Barang', 'url' => route('assets.index')]]);
        }
        if (preg_match('/klaim|asuransi|piutang/u', $message)) {
            abort_unless(isset($access->resources($user)['claims']), 403);
            if (! $access->role($user, ['ASURANSI', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN'])) {
                return $this->response('Akun Anda dapat memantau klaim. Perubahan dilakukan petugas Asuransi/Accounting.', ['Filter tahun, bulan dan penjamin.', 'Nilai tracking bukan penerimaan kas atau piutang resmi yang otomatis masuk neraca.', 'Minta petugas mencocokkan pencairan dengan Penerimaan manual.'], [['label' => 'Tracking Klaim', 'url' => route('insurance.index')]]);
            }

            return $this->response('Klaim asuransi hanya tracking. Memperbarui “sudah cair” tidak membuat Penerimaan atau jurnal otomatis.', ['Pilih penjamin, tahun/bulan dan jenis pelayanan pada Tracking Klaim Asuransi.', 'Isi diajukan/disetujui/sudah cair berdasarkan bukti, lalu simpan.', 'Jika dana benar-benar masuk, catat manual satu kali di Penerimaan.', 'Cocokkan kedua catatan; nilai tracking bukan saldo piutang historis dalam neraca.'], [['label' => 'Tracking Klaim', 'url' => route('insurance.index')]]);
        }

        return $this->response('Saya adalah asisten panduan dan pencarian lokal aplikasi. Saya menggunakan panduan tetap dan pencarian terstruktur; saya tidak mengubah data.', ['Tanyakan “cara input pemasukan” atau “agar neraca muncul”.', 'Ketik “cari inventaris AST-768” atau gunakan Cari Data untuk memilih jenis, kata kunci dan tanggal.', 'Ketik “total pemasukan bulan ini” untuk total catatan yang diizinkan, atau “total neraca” untuk ringkasan jurnal jika Anda berwenang.', 'Pilihan Teks besar/Kontras tinggi membantu membaca. Cari menu dan penanda lokasi membantu navigasi.', 'Untuk pertanyaan di luar topik yang tersedia, hubungi penanggung jawab modul.'], [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Panduan PDF', 'url' => route('assistant.guide')]]);
    }
}
