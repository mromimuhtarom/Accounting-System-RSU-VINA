# Hasil pemilahan dan posting inventaris — 29 Oktober 2026

Atas permintaan pengguna agar Codex langsung memilah dan memasukkan data, empat batch sumber telah ditinjau berdasarkan dokumen dan diposting melalui service impor existing dengan akun Admin Accounting (ID 6). Ini merupakan verifikasi dokumen, bukan pemeriksaan fisik. Keterangan tersebut dicatat pada setiap inventaris dan audit `DOCUMENT_RECONCILIATION`.

| Kelompok master | Catatan | Jumlah menurut sumber |
| --- | ---: | ---: |
| Alat medis | 448 | 448 |
| Alat penunjang medis | 152 | 152 |
| IT / hardware | 106 | 193 |
| Software | 64 | 97 instalasi |
| Fasilitas / kapasitas prasarana | 25 | 25 parameter, bukan jumlah unit fisik |
| Total | 795 | Tidak dijumlahkan lintas satuan |

Terdapat **770 catatan inventaris** dan **25 parameter fasilitas**. Catatan hardware/software yang memuat beberapa perangkat tetap agregat; tidak dipecah menjadi unit atau serial rekaan. Tidak ada kapitalisasi, jurnal, atau penyusutan otomatis.

## Dasar keputusan

- Klasifikasi Alat menjadi sumber utama. Dari 602 baris yang menyatakan `Ya`, dipertahankan 600 setelah mengeluarkan satu duplikat identitas persis dan satu pengulangan kode+serial infusion pump pada lokasi berbeda. Lokasi yang bertentangan tetap tercatat pada sumber dan catatan inventaris pertama; lokasi fisik aktual belum ditetapkan oleh proses ini.
- Sebanyak 711 baris alat dengan ketersediaan kosong atau `Tidak` dilewati dari master. Ini termasuk 201 baris `Tidak`/`Rusak`; tidak diasumsikan masih berada secara fisik di rumah sakit. Seluruh data mentah tetap tersimpan.
- Nama/kode sama dengan serial berbeda dipertahankan. Serial pendek atau serial yang berulang lintas jenis alat tidak dianggap bukti bahwa alat berbeda jenis adalah satu aset. Status validitas serial dijelaskan dalam catatan.
- Tahun pengadaan di luar rentang valid dikosongkan pada master dan nilai aslinya dipertahankan di staging/catatan. Tanggal perolehan, masa manfaat, pendanaan, atau harga accounting tidak ditebak.
- IT: tujuh baris berjumlah nol dan dua baris tanda tangan dikeluarkan. Pengulangan Office dengan nama, lokasi, dan jumlah sama pada kelompok yang sama dikeluarkan. Dua kelompok WiFi berbeda bernomor 21 dan 22 dengan lokasi sama digabung menjadi satu catatan agregat berjumlah empat; jejak kedua baris disimpan.
- Seluruh 25 baris prasarana dipertahankan sebagai parameter fasilitas/kapasitas. Subkategori disertakan pada nama supaya parameter seperti kapasitas PABX memiliki konteks. Lokasi fisik yang tidak tersedia tetap kosong.
- Semua salinan alat pada Format Import Alkes dilewati karena asalnya sudah tercakup pada klasifikasi. Baris hierarki/template Alkes juga dilewati.

## Rekonsiliasi staging

| Batch | Sumber | Terposting | Dilewati |
| --- | --- | ---: | ---: |
| 1 | Klasifikasi | 600 | 713 |
| 2 | Hardware / software | 170 | 11 |
| 3 | Prasarana | 25 | 0 |
| 4 | Alkes pembanding | 0 | 1.428 |
| Total | | 795 | 2.152 |

Seluruh 2.947 baris memiliki keputusan. Semua batch berstatus `POSTED`. Menu Import Aktiva kemudian dihapus atas permintaan pengguna dan diganti Input Aktiva. Data yang dilewati, asal Excel, serta laporan keputusan privat tetap tersimpan.

Backup sebelum perubahan dan laporan keputusan lengkap tersimpan pada disk Laravel `local` (privat):

- `asset-imports/backups/pre-reconciliation-20261029050909.json`
- `asset-imports/reconciliation-20261029.json`

Script rekonsiliasi: `scripts/curate-initial-assets.php --user=6` untuk preview, dengan `--apply` untuk menjalankan. Script hanya memproses empat nama file sumber yang ditetapkan, memeriksa SHA256, serta menyimpan review, approval, master, dan audit dalam satu transaksi. Pengulangan setelah semua batch terposting tidak mengubah data.
