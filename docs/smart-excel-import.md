# Smart Excel Import

## Analisis dan rancangan

Pemasukan Accounting menggunakan ReceiptController::validatedData, model Receipt, dan AutomaticJournalService. Pengeluaran umum menggunakan ExpenseController::validated, model Expense, pemetaan COA dari ExpenseCategory, serta observer jurnal dan audit existing. Impor memanggil validasi tersebut tanpa mengubah aturannya. Penyimpanan memakai Eloquent per baris dalam satu transaksi batch agar observer, audit, dan jurnal tetap berjalan; raw bulk insert akan melewati mekanisme ini.

Akses melalui middleware auth/role existing: ACCOUNTING, KEPALA_KEUANGAN, dan ADMIN_ACCOUNTING pada kedua modul; KASIR pada Penerimaan dan Pengeluaran; ASURANSI hanya pada Penerimaan. Validasi Penerimaan Kasir/Asuransi memakai Cashier ReceiptController::validatedData. Asuransi wajib memilih penjamin dari kategori asuransi. Preview terikat konteks role/route selain pengguna dan modul. Tidak ada migrasi atau penulisan master data. Perubahan lokal yang sudah ada sebelum pekerjaan ini dipertahankan.

## Penggunaan

1. Buka Pemasukan/Penerimaan atau Pengeluaran sesuai akses role, klik Import Excel.
2. Unduh template bila diperlukan. Header template di baris 4 dan data mulai baris 5.
3. Unggah .xlsx/.xls. Sistem mendeteksi header di 30 baris pertama; nomor baris bisa diisi manual.
4. Periksa mapping sinonim/nama field dan saran tipe data. Tipe data hanya dipakai bila kandidat tanggal teks atau nominal tunggal; angka serial tanggal tidak ditebak dari header asing.
5. Pilih koreksi master per baris bila diperlukan, lalu Perbarui Preview. Master harus cocok persis berdasarkan ID, kode ternormalisasi, atau nama ternormalisasi yang unik.
6. Centang baris yang ingin dilewati. Konfirmasi menyimpan Valid/Warning, melewati Duplicate/baris dipilih, dan menghitung Error sebagai gagal validasi.

Data dibaca secara literal; rumus tidak dievaluasi. Tanggal teks mendukung YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, YYYY/MM/DD, DD.MM.YYYY dan tanggal serial Excel. Nominal mendukung 150000.50, 150.000,50, 150,000.50, dan awalan Rp/IDR.

## Integritas

Preview disimpan di cache file server selama 30 menit dan terikat pengguna/modul/token. Mapping/koreksi yang berubah harus dipreview kembali. Konfirmasi divalidasi ulang dan diserialkan melalui lock cache file. Seluruh penulisan batch dibatalkan bila terjadi exception penyimpanan. Tidak mengubah transaksi lama.

Duplikat diperiksa dari nomor transaksi, atau kombinasi tanggal/akun/keterangan/nominal untuk pemasukan; tanggal/kategori/keterangan/nominal/kas-bank untuk pengeluaran. Pemeriksaan dilakukan saat preview dan kembali sebelum penyimpanan. Kebijakan ini konservatif: transaksi identik yang sebenarnya sah juga dilewati dan perlu ditinjau admin. Lock melindungi sesama proses impor pada satu server; CRUD manual tidak mengambil lock ini.

Jurnal mengikuti mekanisme existing. Warning pemasukan tanpa metode pembayaran tidak dianggap bukti kas dan jurnal menunggu data lengkap. ID/kode/nama master tidak otomatis menciptakan data baru.

## File pekerjaan ini

Ditambahkan:
- app/Services/SmartExcelImport.php
- app/Http/Controllers/Accounting/ExcelImportController.php
- resources/views/accounting/imports/excel.blade.php
- tests/Feature/SmartExcelImportTest.php
- docs/smart-excel-import.md

Dimodifikasi:
- app/Http/Controllers/Accounting/ReceiptController.php: akses public pada validasi existing untuk dipakai impor.
- app/Http/Controllers/Accounting/ExpenseController.php: akses public pada validasi existing untuk dipakai impor.
- routes/web.php: satu route GET/POST dengan middleware Accounting existing.
- resources/views/accounting/receipts/index.blade.php: tombol impor.
- resources/views/accounting/expenses/index.blade.php: tombol impor Accounting.
- composer.json dan composer.lock: PhpSpreadsheet 5.10 dan dependency transitifnya. InsuranceExcel existing hanya menyediakan penulisan XLSX; tidak memiliki pembaca XLS/XLSX.

Referensi pembaca: https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/

## Batas dan lingkungan

- Worksheet pertama; maksimal 10 MB upload, 5.000 baris data, 80 kolom, dan arsip XLSX maksimal 64 MB setelah ekstraksi.
- Impor sinkron; waktu eksekusi/memori web server harus cukup untuk ukuran batch. Pembacaan 5.000 baris diuji; belum melakukan benchmark penyimpanan 5.000 transaksi pada database produksi.
- Cache file harus writable dan dibagi antar-worker pada server yang sama. Deployment multi-server memerlukan penyimpanan lock/cache bersama sebelum digunakan.
- PhpSpreadsheet memerlukan ekstensi GD untuk pemasangan Composer. DLL GD tersedia di XAMPP ini tetapi tidak aktif secara default di PHP CLI. Pemasangan dijalankan dengan php -d extension=gd; tidak mengubah php.ini global. SQLite untuk tes diaktifkan per perintah.
- Composer audit melaporkan 7 advisory pada 4 paket existing (laravel/framework, league/commonmark, league/flysystem, symfony/http-foundation); tidak memperbarui framework di luar lingkup tugas ini. Tidak ada advisory yang dilaporkan untuk dependency Excel baru pada pemeriksaan ini.

## Pengujian

Perintah:

    php -d extension=gd -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter=test_smart_excel

9 tes impor / 74 assertion lulus (termasuk akses dan impor Kasir di kedua modul, pembatasan Asuransi, penjamin wajib kategori asuransi, serta kepemilikan transaksi): XLSX pengeluaran, XLS pemasukan, urutan/header berbeda, sinonim dan inferensi tipe, mapping manual, koreksi master, tanggal/nominal, master tidak ditemukan, file rusak, baris kosong, duplikat dan konfirmasi ulang, pengecualian baris, otorisasi, template, penjagaan perubahan mapping, rollback transaksi/jurnal, pembacaan 5.000 baris dan penolakan ukuran berlebih.

Suite penuh: 68 tes; 67 lulus, 1 gagal. Kegagalan ExampleTest::test_the_application_returns_a_successful_response mengharapkan GET / = 200; route existing mengarahkan / ke login dengan 302. Tes tersebut tidak diubah.
