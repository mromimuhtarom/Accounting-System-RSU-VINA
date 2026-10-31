# Modul Inventaris dan Aktiva RSU Vina Estetica

Menu **Inventaris & Aktiva** (`/aktiva`) dan **Input Aktiva** (`/aktiva/input`) menggunakan layout dan COA existing. Menu, halaman, dan endpoint HTTP Import Aktiva sudah dihapus atas permintaan pengguna; inventaris dan riwayat Excel tetap tersimpan. Lihat [panduan input manual](manual-assets.md). Bagian impor di bawah merupakan arsip proses pemuatan sumber awal. Tidak ada observer yang otomatis membuat jurnal inventaris. Master inventaris, fasilitas/kapasitas, dan kapitalisasi accounting memiliki status dan penyimpanan terpisah.

## Instalasi dan sumber awal

Jalankan hanya dua migration modul apabila terdapat migration lama yang masih perlu ditinjau:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000001_create_fixed_asset_module.php
php artisan migrate --path=database/migrations/2026_10_29_000002_index_asset_duplicate_candidates.php
```

Keduanya telah diterapkan pada database lokal saat implementasi. Role baru tersedia pada pengelolaan akun existing: `PETUGAS_ASET` dan `ADMIN_ASET`. Role tidak otomatis diberikan ke pengguna. `ADMIN_ACCOUNTING` dapat mengoperasikan seluruh alur, sementara `ACCOUNTING` melengkapi kapitalisasi dan posting jurnal; approval memerlukan `ADMIN_ASET`, `ADMIN_ACCOUNTING`, atau `KEPALA_KEUANGAN`. `KEPALA_KEUANGAN` juga memiliki akses accounting. Direktur/owner hanya membaca. Akun nonaktif ditolak.

Sumber awal dapat diunggah melalui menu, atau distaging dengan petugas yang berhak:

```powershell
php artisan assets:stage-sources "C:/Users/ASUS/Downloads" --user=ID_PETUGAS
```

Perintah menyiapkan klasifikasi dahulu, kemudian IT, prasarana, dan Alkes sebagai pembanding. Perintah dapat diulang: file identik dan worksheet yang sudah distaging digunakan kembali. Perintah tidak menyetujui, memposting master, mengkapitalisasi, atau membuat jurnal. File asli telah diuji pada database SQLite terisolasi; tidak ada approval atau jurnal atas file sumber yang dibuat pada database operasional oleh pengujian.

## Analisis seluruh workbook

Profil seluruh worksheet dan seluruh kolom tersedia di [asset-source-analysis.json](asset-source-analysis.json); dapat dibuat ulang menggunakan `php scripts/analyze-assets.php`. Analisis membaca nilai dan formula sebagai data. Teks petunjuk dalam workbook bukan instruksi untuk menjalankan aksi pada aplikasi.

| Workbook / worksheet | Struktur | Perlakuan |
| --- | --- | --- |
| Hardware / Sheet1 | Header baris 4; A nomor kelompok, B posisi unit, C perangkat, D jumlah; sampai baris 151 | IT; lokasi kosong mengikuti kelompok sebelumnya; jumlah tidak dipecah menjadi unit rekaan |
| Hardware / Sheet2 | Header baris 4; struktur sama; sampai baris 106 | Software; inventaris instalasi belum membuktikan kepemilikan lisensi |
| Alkes / Format Import Alkes.xls | Header baris 5; baris data diselingi hierarki `*`, `**`, `***`; 1.313 baris alat | Header ruangan `***` menjadi lokasi; D kode, E nama, F tersedia, G serial, H merk, I tipe, J tahun, K kondisi, L harga, M pendanaan, N distributor, O AKL/AKD, P catatan |
| Alkes / Petunjuk | Aturan pengisian template | Dapat dipreview; bukan unit inventaris |
| Alkes / Tatacara pengisian | Penjelasan merk, tahun, kondisi, harga, pendanaan | Dapat dipreview; bukan unit inventaris |
| Prasarana / Prasarana Ada | Header baris 3; 25 parameter; kategori, subkategori, parameter, tersedia, jumlah/kapasitas, keterangan | Fasilitas/kapasitas dahulu, perlu pemeriksaan apakah merupakan unit prasarana individual |
| Klasifikasi / Ringkasan | Ringkasan dan formula | Dapat dipreview; tidak diimpor sebagai inventaris |
| Klasifikasi / Klasifikasi Alat | Header baris 1; 1.313 baris; 1.026 medis dan 287 penunjang | Sumber utama klasifikasi; menyimpan baris asal dan atribut teknis/accounting mentah |
| Klasifikasi / Perlu Verifikasi | 170 baris yang mengulang sebagian alat | Daftar pemeriksaan; tidak dibuat sebagai unit baru; keyakinan sedang ditandai pada worksheet utama |
| Klasifikasi / Panduan | Definisi operasional dan referensi sumber | Dapat dipreview; tidak diimpor sebagai inventaris |

**Keterkaitan terverifikasi:** seluruh 1.313 baris klasifikasi cocok dengan baris asal, kode, dan serial pada Alkes. Panduan juga menyebut workbook Alkes sebagai sumber. Kedua file tidak boleh dijumlahkan sebagai aset yang berbeda tanpa verifikasi unit.

Kedua sumber alat memiliki 602 baris “Ya”, 201 baris “Tidak” sekaligus “Rusak”, dan 510 baris tanpa ketersediaan. Sebanyak 711 baris tidak memiliki serial maupun harga; merk kosong/placeholder terdapat pada 1.044 baris dan tipe pada 1.225 baris. Terdapat satu pengulangan identitas persis dalam masing-masing sumber. Ada 170 klasifikasi berkeyakinan sedang. Kode pendanaan numerik (`8`/`8.0`) juga ditandai untuk diperiksa. Baris “Tidak” sekaligus “Rusak” memerlukan pemeriksaan keberadaan fisik, bukan otomatis dinyatakan inventaris rusak resmi.

## Import dan verifikasi

1. Upload XLS/XLSX (maksimum 20 MB, 40 worksheet, 500.000 sel). File disimpan privat dengan SHA256 unik; file identik mengarah ke batch yang sama.
2. Preview 30 baris awal worksheet pilihan beserta nomor Excel. Sel formula tidak dihitung atau dieksekusi. Seluruh baris data diproses ketika staging.
3. Pilih sumber, worksheet, baris header, dan mapping. Preset mengikuti struktur di atas dan dapat diubah. Setiap worksheet hanya distaging sekali; kedua worksheet IT dapat berada dalam satu batch.
4. Normalisasi spasi, kapitalisasi nama kategori/lokasi, kondisi `GOOD/BROKEN/INACTIVE/UNKNOWN`, harga desimal, dan jenis inventaris. Nilai mentah lengkap dipertahankan. Harga sumber bukan harga perolehan tervalidasi. Tahun tidak diubah menjadi tanggal perolehan rekaan. Subkategori dan atribut lain yang belum dipetakan tetap tersedia dalam `raw`.
5. Kandidat duplikat dicari terhadap master dan staging lintas file berdasarkan serial, kode+lokasi, atau nama+lokasi. Kunci pencarian dinormalisasi dan diindeks. Kode alat tidak dianggap unik per unit; serial pendek/placeholder tetap memerlukan review. Fingerprint identitas dan file+worksheet+baris mempertahankan asal serta idempotensi.
6. Status `REVIEW`, `DUPLICATE`, dan `FAILED` harus ditinjau administrator. Koreksi nama/kode/serial/lokasi/kategori/jenis/kondisi/tahun/jumlah/harga tersedia per baris. Catatan keputusan wajib, termasuk bukti bahwa kandidat duplikat merupakan unit berbeda. Pilih `SKIPPED` untuk data yang tidak akan diposting.
7. Approval batch hanya setelah tidak ada masalah tertunda. Posting memeriksa ulang kandidat baru dan validitas field sebelum menulis master; jika muncul konflik baru, batch dikembalikan ke staging untuk diverifikasi. Posting lintas file diserialisasi dengan lock, serta insert master dan audit berada dalam satu transaksi. Setiap staging row hanya dapat membuat satu master.
8. Hasil menampilkan berhasil, dilewati, duplikat, gagal, perlu periksa, dan siap. Laporan CSV memuat asal, masalah, kandidat, verifikator, serta alasan keputusan; teks yang berpotensi menjadi formula spreadsheet di-escape.

## Kapitalisasi dan accounting

Kapitalisasi hanya tersedia untuk inventaris aktif berjumlah satu yang telah diverifikasi, selain jenis fasilitas. Petugas mengisi biaya tervalidasi, tanggal perolehan, tanggal mulai penyusutan, **sisa** masa manfaat dalam bulan, residu, akumulasi awal, nilai buku awal, empat akun, dan bukti perolehan/rekonsiliasi. Akun keempat merupakan akun lawan kapitalisasi atau saldo awal; petugas harus memastikan nilai yang sama belum dijurnal melalui pembelian/modul lain.

Nilai buku awal wajib sama dengan biaya dikurangi akumulasi awal dan tidak kurang dari residu. Akun aktiva, beban, akumulasi, dan akun lawan harus aktif dan berbeda; jenis dan saldo normal divalidasi. Draft tidak membuat jurnal. Sesudah approval administrator, Accounting dapat memposting kapitalisasi pada tepat satu periode terbuka dan pada tanggal antara perolehan serta mulai penyusutan. Jurnal: debit aktiva sebesar biaya, kredit akumulasi awal bila ada, kredit akun lawan sebesar nilai buku awal. Referensi unik mencegah posting ulang.

Penyusutan garis lurus dilakukan **secara eksplisit per bulan**, berurutan dari bulan mulai penyusutan. Dasarnya nilai buku awal dikurangi residu selama sisa masa manfaat. Pembulatan kumulatif dalam sen memastikan sisa pembulatan diselesaikan pada bulan terakhir. Jurnal debit beban dan kredit akumulasi; tidak dapat melampaui residu atau masa manfaat. Pengulangan bulan yang sama mengembalikan jurnal existing.

Periode aktiva dibuka/ditutup oleh pengguna yang memiliki hak accounting sekaligus approval. Rentang periode tidak boleh tumpang tindih. Periode ini khusus jurnal modul aktiva; belum menggantikan kontrol periode pada modul accounting lain. Penutupan menghalangi jurnal aktiva baru bertanggal periode tersebut.

## Operasional dan dashboard

- QR dihasilkan lokal sebagai SVG QR Model 2 versi 3-L, berisi `RSUVE:UUID`. Scan dan masukkan hasilnya ke pencarian inventaris. Tidak mengirim identitas inventaris ke layanan QR eksternal.
- Mutasi menyimpan lokasi dan penanggung jawab lama/baru. Riwayat awal juga tersedia pada audit posting dan sumber impor.
- Pemeliharaan mencatat tanggal, penyedia, dan bukti/catatan. Kalibrasi ditetapkan sesuai kebutuhan unit, dengan penyedia, sertifikat, dan jatuh tempo berikutnya.
- Stock opname menyimpan lokasi yang diharapkan dan ditemukan serta kondisi, termasuk penanda kecocokan lokasi. Perbedaan lokasi tidak otomatis memutasi master.
- Pelepasan dimulai dari permintaan operasional. Persetujuan penghapusan tanpa penerimaan memerlukan hak accounting dan approval; aktiva resmi menghasilkan debit akumulasi dan rugi nilai buku, kredit aktiva. Inventaris operasional tidak menghasilkan jurnal. Penjualan aset dengan penerimaan belum memiliki alur khusus; perlu jurnal penjualan tersendiri dan rekonsiliasi sebelum menggunakan penghapusan ini.
- Audit aplikasi menyimpan aktor, waktu, aksi, nilai sebelum/sesudah di tabel khusus. Tidak tersedia endpoint untuk mengubah atau menghapus audit. Riwayat impor juga tersedia di staging dan laporan verifikasi.
- Dashboard menampilkan jumlah catatan inventaris, unit agregat per lokasi, aktiva tetap resmi, biaya tervalidasi, akumulasi, nilai buku, rusak, dan kalibrasi jatuh tempo/30 hari. Nilai resmi hanya dari kapitalisasi terposting pada aset aktif; staging dan fasilitas ditampilkan terpisah.

## Pengujian

### Status pemuatan sumber awal — 29 Oktober 2026

Keempat file sudah dimasukkan ke database lokal `accounting_rs_vina` melalui perintah staging dengan akun Admin Accounting (ID 6). Terdapat 4 batch berstatus `STAGED` dan 2.947 baris staging, termasuk header/baris struktural Alkes yang dilewati:

| Batch | Sumber | Baris | Siap | Perlu periksa | Kandidat duplikat | Dilewati |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| 1 | Klasifikasi alat | 1.313 | 367 | 841 | 105 | 0 |
| 2 | Hardware dan software | 181 | 0 | 178 | 3 | 0 |
| 3 | Prasarana | 25 | 0 | 25 | 0 | 0 |
| 4 | Format Import Alkes | 1.428 | 0 | 0 | 1.313 | 115 |

Integritas seluruh file tersimpan sudah diperiksa terhadap SHA256. Perintah diulang dan jumlah batch/baris tetap sama. Angka di atas merupakan snapshot staging awal. Setelah pengguna meminta pemilahan langsung, **795 catatan telah diposting ke master** (770 inventaris dan 25 parameter fasilitas), sementara 2.152 baris dilewati dengan alasan tercatat. Lihat [hasil rekonsiliasi](asset-reconciliation-2026-10-29.md). Kapitalisasi dan jurnal tetap belum dibuat.

```powershell
# SQLite CLI dapat diaktifkan per proses tanpa mengubah konfigurasi XAMPP.
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-progress

# Tambahkan pengujian keempat workbook asli di database terisolasi.
$env:ASSET_SOURCE_FIXTURE_DIR='C:/Users/ASUS/Downloads'
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/FixedAssetTest.php --no-progress
```

Pengujian workbook asli memverifikasi staging semua sumber dan kedua worksheet IT, pengulangan perintah tanpa tambahan baris, deteksi overlap, serta tidak terbentuknya master atau jurnal. Test ini dilewati pada CI yang tidak menyediakan fixture directory. Pengujian utama memakai workbook sintetis XLS/XLSX yang dibuat saat test, database in-memory, dan storage palsu.
