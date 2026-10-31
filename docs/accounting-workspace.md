# Ruang kerja Accounting

Navigasi disederhanakan: sidebar Pembukuan berisi Ruang Kerja Accounting, Analisis Saldo Akun, Neraca, dan Neraca Saldo. Saldo Awal serta Sumber Data & Bantuan tetap dapat dibuka dari Ruang Kerja, tanpa menu sidebar terpisah. Petunjuk panjang berulang pada laporan diganti tautan kembali ke Ruang Kerja. Semua data dan fungsi pembukuan tetap tersedia.

Menu **Pembukuan → Ruang Kerja Accounting** menyediakan tiga langkah: input transaksi, pemeriksaan transaksi belum dijurnal, dan analisis saldo. Dashboard tetap menu pertama dan menyediakan tautan ruang kerja. Gate existing `OpeningBalancePolicy` membatasi input ke Accounting/Admin Accounting/Kepala Keuangan; pengguna laporan seperti Owner/Finance membaca sesuai kewenangannya.

## Input

- **Penerimaan** dan **Pengeluaran** tetap menggunakan modul existing beserta ekspor dan validasinya. Klaim asuransi hanya tracking; uang cair diinput manual sebagai Penerimaan satu kali.
- **Pencatatan Nonkas** (`/accounting/pencatatan-nonkas`) menyediakan pilihan aset secara kredit, beban yang belum dibayar, penyesuaian penyusutan, dan reklasifikasi ekuitas. Petugas memilih akun debit, kredit, nominal, tanggal, referensi bukti dan dasar pencatatan. Preview memperlihatkan pasangan jurnal. Konfirmasi verifikasi diperlukan sebelum posting.
- Akun wajib aktif, merupakan akun rincian, sesuai tipe template, serta berbeda antara debit dan kredit. Mapping rekening kas/bank tidak ditawarkan sebagai akun aset pada form nonkas. Penyusutan menggunakan akun kontra-aset bersaldo normal kredit. Pencatatan aset/penyusutan mengikuti satu periode aktiva OPEN jika modul periodenya terpasang.
- Form nonkas membuat jurnal dua baris dengan nominal sama. `entry_token` menjadi nomor jurnal unik `ACC-{uuid}`, sehingga pengiriman ulang form yang sama tidak menggandakan jurnal. Token yang sudah dipakai dengan isi atau petugas berbeda ditolak. Seluruh penulisan berada dalam transaksi; penguncian tanggal saldo awal tetap berlaku. Observer audit jurnal existing tetap digunakan.
- Jurnal manual existing tetap tersedia untuk transaksi lebih dari dua akun. Saldo awal tetap melalui persetujuan/posting existing, bukan diisi ulang bulanan.

**Tidak ada uang kas, transaksi Penerimaan/Pengeluaran, atau register inventaris yang dibuat oleh form nonkas.** Form ini untuk pengakuan/penyesuaian nonkas berdasarkan dokumen. Pelunasan utang, setoran modal tunai, atau jurnal kompleks yang belum didukung template perlu prosedur Accounting dan rekonsiliasi terhadap buku transaksi, bukan dipaksakan menjadi template lain. Modul ini belum menyediakan subledger utang atau alokasi pembayaran utang.

Jangan memasukkan aset/penyusutan yang sama melalui form nonkas lalu memposting ulang kapitalisasi/penyusutan dari register aset. Tidak ada deteksi lintas dokumen yang dapat memastikan dua referensi berbeda sebenarnya transaksi yang sama; verifikasi bukti dan rekonsiliasi tetap diperlukan.

## Analisis

**Pembukuan → Analisis Saldo Akun** (`/reports/analisis-saldo`) menerima rentang tanggal. Saldo awal tabel adalah posisi akhir hari sebelum tanggal mulai; saldo akhir adalah posisi akhir tanggal selesai. Laporan menampilkan perubahan aset/kewajiban/ekuitas dan saldo per akun, mutasi debit/kredit di dalam periode, serta tautan jurnal sumber. PDF memakai komponen existing dengan orientasi landscape.

Angka dihitung dari jurnal POSTED, termasuk jurnal penyesuaian dan penutupan bila ada. Mutasi pendapatan/beban bukan otomatis arus kas atau laba rugi operasional murni. Jurnal saldo awal yang jatuh di dalam periode juga termasuk mutasi, dan ketiadaan saldo awal untuk pembanding diberi peringatan. Akun nonaktif dengan saldo historis tetap ditampilkan. Rasio kewajiban/aset hanya dihitung bila aset positif dan kewajiban tidak negatif; rasio bukan penilaian otomatis kesehatan keuangan.

Rekap klaim asuransi dan harga sementara inventaris tidak ditambahkan ke jurnal. Ringkasan transaksi operasional tetap berbeda dari posisi akun. Halaman sumber dan daftar transaksi belum dijurnal membantu menelusuri perbedaannya.

Tidak ada migration tambahan, backfill, atau saldo keuangan nyata yang diposting saat implementasi. Tes menggunakan database SQLite terisolasi.
