# Grafik dan export aktiva

Grafik inventaris dan aktiva tersedia pada dashboard keuangan, dashboard Inventaris & Aktiva, Laporan Bulanan, serta Laporan Tahunan bagi pengguna dengan hak melihat aktiva.

Grafik memuat jumlah catatan menurut jenis, delapan lokasi dengan catatan terbanyak, harga perolehan tervalidasi, akumulasi penyusutan, nilai buku, serta kapitalisasi dan beban penyusutan per bulan. Jumlah catatan dibedakan dari jumlah perangkat/instalasi. Parameter fasilitas ditampilkan terpisah.

**Dasar waktu:** jenis, jumlah, dan lokasi inventaris adalah posisi master saat ini, bukan rekonstruksi kondisi fisik historis. Nilai resmi mengikuti tanggal jurnal kapitalisasi dan tanggal penyusutan sampai akhir periode yang dipilih. Pelepasan mengecualikan nilai aset mulai tanggal pelepasan; laporan periode sebelumnya tetap memasukkan nilai aset tersebut. Saldo akumulasi awal dihitung sejak jurnal kapitalisasi/saldo awal diposting. Harga sementara dan draft kapitalisasi tidak menjadi nilai resmi.

Export PDF bulanan/tahunan menggunakan periode dan grafik yang sama dengan layar. Grafik aktiva dipisahkan menjadi bagian-bagian PDF, dengan pengaturan lebar, skala, dan jarak agar tidak dipotong oleh grid responsif. Data laporan keuangan existing tidak diubah atau ditambahkan dengan harga sumber inventaris.

Tombol **Export Excel** pada Inventaris & Aktiva mengunduh XLSX dengan dua worksheet:

- **Inventaris:** semua hasil filter pencarian, jenis, lokasi, dan kondisi, mencakup seluruh halaman. Kolom meliputi identitas, jumlah, penanggung jawab, kondisi, harga sementara, status kapitalisasi, nilai resmi, kalibrasi, asal Excel, catatan, dan QR.
- **Ringkasan:** jumlah catatan/unit per kelompok sesuai filter. Nilai accounting dijumlahkan hanya dari kapitalisasi terposting pada aktiva aktif. Harga/akumulasi historis aset yang dilepas tetap dapat dilihat di detail, dengan nilai buku nol; tidak ditambahkan ke saldo aktiva aktif pada Ringkasan.

Aktiva yang dihapus tidak ikut export. Kode, nomor seri, dan teks disimpan sebagai cell string eksplisit untuk mempertahankan nol di depan serta mencegah eksekusi teks yang menyerupai formula. Angka uang disimpan numerik dengan format dua desimal. File sementara dihapus setelah pengiriman.

Pengujian mencakup batas periode, penyusutan lintas bulan, pelepasan historis, pemisahan harga sumber/nilai resmi, grafik dashboard/laporan, akses, hasil kosong, filter lintas halaman, nomor seri, serta keamanan cell XLSX. Pengujian backend memeriksa markup PDF dan data grafik; interaksi unduh PDF di browser tetap menggunakan renderer html2pdf existing.
