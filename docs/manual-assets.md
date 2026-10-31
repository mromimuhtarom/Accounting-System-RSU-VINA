# Input Aktiva

Grafik dashboard/laporan dan tombol Export Excel dijelaskan pada [panduan grafik dan export aktiva](asset-reports-and-exports.md).

## Mengecek asal data dan angka per bulan

Input/edit merupakan master satu unit atau catatan inventaris; aktiva tidak diinput ulang setiap bulan. Pada halaman edit terdapat panel **Asal data dan hubungannya dengan laporan bulanan**, berisi file, worksheet, baris Excel, perbandingan nilai asli dan master saat ini, serta status kapitalisasi. Nilai asli dibaca dari sel mentah sesuai mapping, sehingga perubahan harga master tidak menimpa jejak harga Excel. Tautan dari form edit membuka detail di tab baru.

Untuk contoh Emergency Trolley dengan serial `348374JKD`, harga sementara Rp2.500.000 dan tahun 2015 berasal dari `Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx`, worksheet `Klasifikasi Alat`, sel harga `Q485`, dengan rujukan baris Alkes asal 524. Tahun pengadaan bukan bulan jurnal. Pada saat pemeriksaan contoh ini belum mempunyai kapitalisasi.

Klik **Periksa data per bulan**, pilih bulan pada detail aktiva, lalu klik **Tampilkan bulan**. Panel menampilkan nilai buku awal, kapitalisasi bulan itu, akumulasi awal yang dibawa saat kapitalisasi, beban penyusutan, pelepasan, saldo akhir, dan nomor/tanggal jurnal sumbernya. Angka khusus aktiva tersebut; data yang belum dijurnal tetap nol. Riwayat kegiatan operasional tetap tersedia terpisah dan tidak disembunyikan oleh pilihan bulan accounting.

Klik **Buka Kapitalisasi / Penyusutan** untuk melengkapi proses accounting berdasarkan bukti perolehan. Menyimpan edit harga sementara tidak membuat jurnal bulanan. Nilai akhir bulan dihitung dari posting sampai akhir bulan yang dipilih, termasuk transaksi pada hari terakhir bulan, dan memperhitungkan pelepasan aset.

Input baru dilakukan melalui **Input Aktiva** pada sidebar atau **Tambah Aktiva** di daftar inventaris (`/aktiva/input`). Menu dan seluruh endpoint HTTP Import Aktiva telah dihapus. Data Excel yang sudah diposting, staging asal, serta auditnya tetap tersimpan; detail inventaris menampilkan nama file/worksheet/baris sebagai teks.

Migration `2026_10_29_000003_support_manual_asset_entry.php` mengizinkan inventaris manual tanpa staging Excel dan menambahkan pembuat serta token pengiriman form. Migration ini sudah diterapkan pada database lokal tanpa menghapus inventaris yang ada.

Petugas aset, administrator aset, Accounting, Admin Accounting, dan Kepala Keuangan dapat membuat inventaris. Kasir dan pengguna dengan akses baca tidak dapat membuka form atau menyimpan data.

Isian wajib: nama, jenis inventaris, kategori, jumlah positif, kondisi, serta keterangan/dasar pencatatan. Lokasi dan penanggung jawab wajib untuk inventaris; parameter fasilitas memerlukan kapasitas/satuan. Nomor seri, kode, merk, tipe, tahun pengadaan, pendanaan, dan harga sementara dapat dilengkapi sesuai bukti. Tahun tidak boleh melampaui tahun berjalan. Kategori/lokasi dinormalisasi. Jadwal kalibrasi wajib jika kebutuhan kalibrasi dipilih; tanggal lampau dapat dicatat untuk alat yang sudah jatuh tempo.

Nomor seri yang sama atau identitas persis ditolak. Kandidat dengan nama/kode dan lokasi sama tanpa serial pembeda memerlukan konfirmasi unit berbeda beserta alasan yang dicatat di audit. Alat dengan nama/kode sama dan serial berbeda dapat dicatat sebagai unit berbeda. Token unik mencegah pengiriman form berulang menghasilkan data ganda.

Penyimpanan membuat QR dan audit `MANUAL_CREATE`, kemudian membuka detail aktiva. Inventaris berjumlah lebih dari satu tetap merupakan catatan agregat. Penyimpanan manual tidak membuat jurnal, kapitalisasi, atau penyusutan. Kapitalisasi dilakukan melalui proses Accounting existing, dengan approval dan periode terbuka.

## Edit dan hapus

Tombol **Edit** dan **Hapus Aktiva** tersedia pada daftar serta detail aktiva aktif. Hak edit mengikuti hak input. Hak hapus diberikan kepada Accounting, Admin Aset, Admin Accounting, dan Kepala Keuangan. Petugas aset dan pengguna baca tidak memiliki hak hapus. Aktiva yang kapitalisasinya sudah disetujui atau sudah dijurnal tetap memerlukan proses pelepasan.

Edit menggunakan form yang sama dengan input, dengan nilai existing dan alasan perubahan wajib. Asal Excel, pembuat, QR, status, dan identitas sistem dipertahankan. Duplikat diperiksa terhadap aktiva lain; serial baru yang sudah dipakai dan identitas persis ditolak. Serial lama yang sudah berulang pada dokumen historis tidak menghalangi koreksi atribut lain. Perubahan lokasi atau penanggung jawab dicatat sebagai riwayat mutasi. Jika kapitalisasi sudah dibuat, jenis inventaris, jumlah, harga sementara, dan tahun dikunci; nilai accounting dan jurnal tidak diubah melalui form ini.

Hapus memerlukan alasan dan konfirmasi pada form. Sistem melakukan soft delete: data hilang dari daftar, pencarian, dashboard, dan akses detail biasa, sementara sumber, kegiatan, serta audit tetap tersimpan. Aktiva yang sudah disetujui kapitalisasinya, mempunyai jurnal, atau sudah dilepas harus menggunakan proses pelepasan, bukan penghapusan daftar. Draft kapitalisasi yang belum disetujui dapat tetap disimpan sebagai arsip bersama aktiva yang dihapus.

Setiap edit dan hapus memeriksa versi data. Perubahan operasional setelah form dibuka menyebabkan permintaan lama ditolak agar tidak menimpa perubahan pengguna lain. Audit mencatat nilai sebelum/sesudah, petugas, dan alasan (`MANUAL_UPDATE` / `MANUAL_DELETE`). Migration `2026_10_29_000004_support_asset_editing_and_soft_deletes.php` sudah diterapkan pada database lokal; tidak ada data existing yang dihapus saat penerapan.
