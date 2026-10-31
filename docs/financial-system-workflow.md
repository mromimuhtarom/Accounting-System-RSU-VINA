# Alur keuangan mengikuti data aplikasi

Input terpandu nonkas, ruang kerja, dan analisis perubahan saldo: [panduan Ruang Kerja Accounting](accounting-workspace.md).

Penyesuaian 30 Oktober 2026 mengikuti penegasan pengguna: **klaim asuransi hanya tracking; pencairan tetap diinput manual pada Penerimaan**. Tidak ada jurnal, kas masuk, atau pengakuan piutang otomatis dari rekap tersebut.

## Menu dan pemisahan input

Dashboard berada paling atas di sidebar. Neraca, Neraca Saldo, Saldo Awal, serta Sumber Data & Bantuan berada dalam menu lipat **Pembukuan**. Menu **Inventaris Barang** hanya satu; tombol tambah barang ada di halaman daftar, tanpa menu input terpisah. Data dan riwayat inventaris tetap disimpan.

Dashboard menampilkan ringkasan neraca untuk role yang memiliki akses laporan keuangan. Tanggal ringkasan adalah akhir tahun pilihan untuk tahun lampau, atau hari ini untuk tahun berjalan. Tahun yang belum dimulai tidak menampilkan ringkasan neraca. Ringkasan bersifat kumulatif dari jurnal sampai tanggal tersebut, bukan hanya mutasi tahun pilihan. Jika belum ada jurnal, ditampilkan status belum tersedia; jika saldo awal belum terposting atau tanggal mendahului saldo awal, keterbatasan tersebut dinyatakan. Klaim tracking tidak ditambahkan ke ringkasan.

- **Aset & Pasiva: Data Sistem** (`/reports/sumber-keuangan`): ringkasan transaksi aktif sesuai tanggal, inventaris terkini, tracking klaim terkini, panduan input dan kesiapan pembukuan.
- **Inventaris & Aset Tetap**: hanya barang, software, fasilitas/kapasitas, lokasi, kondisi, penanggung jawab, pemeliharaan dan kapitalisasi yang diverifikasi.
- **Penerimaan**: uang benar-benar masuk, termasuk pencairan asuransi yang dicatat manual. Jangan membuat transaksi kedua dari nilai tracking.
- **Pengeluaran**: uang benar-benar dibayar. Tagihan belum dibayar bukan otomatis pengeluaran kas.
- **Saldo Awal Akun**: posisi awal kas/bank, persediaan, aset, kewajiban dan ekuitas berdasarkan dokumen. Pilihan akun dipisahkan ke kelompok aset, kewajiban, ekuitas. Approval/posting existing tetap digunakan.
- **Neraca / Neraca Saldo (Jurnal)**: saldo dari jurnal terposting, bukan penjumlahan rekap operasional.
- **Jurnal Manual**: penyesuaian Accounting berdasarkan bukti setelah tanggal saldo awal, misalnya pengakuan utang dan perubahan modal.

## Kenapa laporan operasional bisa berisi data, tetapi neraca kosong?

Buku penerimaan/pengeluaran lama belum tentu mempunyai pasangan akun dan jurnal. Halaman sumber menampilkan jumlah serta nominal transaksi aktif yang belum dijurnal, dengan daftar rinci yang dapat dibuka dari tombol **Periksa ... belum dijurnal**. Daftar dapat disaring ke transaksi yang belum memilih kas/bank, dan Accounting dapat membuka edit transaksi sumber. Pemilihan kas/bank saja belum menjamin semua mapping lengkap.

Daftar tidak memasukkan transaksi bertanggal setelah cutoff, transaksi dibatalkan, transaksi yang sudah mempunyai jurnal, atau transaksi sebelum/sampai saldo awal terposting. Transaksi lama yang telah dicakup saldo awal jangan diposting lagi. Halaman sumber dan drilldown hanya membaca data; tidak melakukan backfill atau posting massal.

Total penerimaan dikurangi pengeluaran bukan saldo kas akhir tanpa saldo awal dan rekonsiliasi. Nominal tersebut juga bukan total aset atau modal rumah sakit. Utang, modal, persediaan, dan aset lama memerlukan bukti serta klasifikasi akun.

## Waktu dan sumber data

Ringkasan transaksi mengikuti tanggal yang dipilih. Inventaris dan tracking asuransi adalah kondisi terkini dan diberi label terpisah. Rekap asuransi hanya menyimpan nilai pencairan kumulatif, sehingga tidak dapat dianggap sebagai riwayat piutang per tanggal. Sisa tracking dihitung per klaim dari nilai disetujui dikurangi pencairan; klaim ditolak tidak dihitung sebagai sisa disetujui. Anomali nominal ditandai.

Nilai tracking tidak pernah ditambahkan ke neraca atau neraca saldo. Jika Accounting secara terpisah mengakui piutang dalam jurnal berdasarkan dokumen, pencairannya perlu direkonsiliasi terhadap piutang yang sudah diakui agar tidak menjadi pendapatan kedua. Sistem tidak menebak perlakuan ini dari status tracking.

## Implementasi

`FinancialSources` membaca sumber existing; `FinancialPosition` tetap membaca jurnal resmi. Gate `OpeningBalancePolicy` mengatur halaman ringkasan dan drilldown. Pemilik/direktur/finance membaca sesuai role existing; hanya Accounting yang mendapat tautan input/edit. Tidak ada migration tambahan, perubahan saldo, penghapusan data, jurnal buatan, atau penggabungan sumber Excel dalam penyesuaian ini.

Audit baca agregat lokal dapat dijalankan dengan `php scripts/inspect-financial-sources.php` (CLI saja). Panduan saldo awal dan pembatasan posting: [saldo-awal-dan-neraca.md](saldo-awal-dan-neraca.md).
