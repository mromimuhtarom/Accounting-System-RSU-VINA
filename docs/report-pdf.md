# Export PDF laporan

Tombol Export PDF pada laporan bulanan/tahunan memakai data yang sedang ditampilkan,
bukan nilai filter yang belum dikirim melalui tombol Tampilkan. Tidak ada endpoint,
query, migrasi, atau perubahan transaksi baru.

`reports/partials/pdf-export.blade.php` memuat bundle html2pdf.js lokal dan
`public/js/report-pdf.js`. Export membuat salinan tampilan, mempertahankan grafik
HTML/CSS dan warna existing, menambahkan identitas serta periode, lalu merendernya
pada resolusi 2x. A4 portrait dipakai untuk bulanan dan landscape untuk tahunan.
Tabel panjang dipisahkan pada batas baris dan mengulang header. Grafik klaim panjang
dipisahkan per penjamin. Nomor halaman ditambahkan setelah seluruh bagian selesai.
Jika satu baris melebihi satu halaman, export menampilkan kegagalan agar konten tidak
terpotong diam-diam. Proses berlangsung di browser; teks PDF berupa gambar.

Nama file: `Laporan_Bulanan_YYYY-MM.pdf` / `Laporan_Tahunan_YYYY.pdf`.
Pustaka dan aset logo disajikan dari aplikasi sendiri; tidak ada pengiriman data laporan
ke layanan PDF eksternal.

Pengujian server dengan SQLite sementara (tanpa mengubah php.ini):

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter SmartExcelImportTest
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter ReportPdfTest
```

Untuk pemeriksaan visual, buka laporan, pilih periode, tekan Tampilkan, lalu Export PDF.
Bandingkan total, tabel, grafik, warna, header berulang, nomor halaman, dan batas kertas.
Ulangi dengan periode kosong dan tabel panjang.

Verifikasi implementasi: SmartExcelImportTest lulus 27 tes / 265 assertion;
ReportPdfTest lulus 21 tes / 220 assertion (termasuk tes akses yang diwarisi).
Chrome headless menjalankan export dari HTML laporan hasil fixture tes, dengan tambahan
45 baris per tabel untuk simulasi laporan panjang: bulanan menghasilkan PDF valid
18 halaman (~4,7 MB), tahunan 9 halaman (~5,6 MB). Ini memverifikasi proses render,
pembuatan file dan pagination, bukan persetujuan visual hasil cetak secara manual.

Regresi posisi render: buka `tests/Browser/report-pdf-crop.html` melalui server lokal
dengan viewport desktop lebar. Tambahkan `?yearly` untuk landscape. Fixture menjalankan
export setelah scroll dan memeriksa piksel penanda di kedua tepi canvas; hasil `passed`
harus `true`. Pada viewport 1918 px, kode lama kehilangan kedua tepi, sedangkan posisi
container yang dikunci ke kiri atas mempertahankan keduanya pada lebar render
1000 px dan 1600 px. Fixture mencegat unduhan dan menampilkan cuplikan canvas.
