# Saldo awal dan neraca

Alur operasional terbaru: [Keuangan mengikuti data aplikasi](financial-system-workflow.md). Klaim asuransi hanya tracking; pencairan diinput manual pada Penerimaan. Rekap klaim tidak ditambahkan otomatis ke saldo awal atau neraca. Mulai pemeriksaan dari menu **Aset & Pasiva: Data Sistem**.

Menu **Posisi Keuangan** memisahkan inventaris operasional dari laporan keuangan:

- **Saldo Awal**: saldo akun pada tanggal awal pembukuan, berdasarkan dokumen yang diverifikasi Accounting.
- **Neraca**: aset, liabilitas, dan ekuitas per tanggal, dari jurnal berstatus POSTED.
- **Neraca Saldo**: saldo debit/kredit seluruh akun. Klik akun untuk melihat jurnal sumbernya.

Persamaan neraca: **Aset = Liabilitas + Ekuitas**. Istilah pasiva mencakup liabilitas dan ekuitas. Hasil pendapatan dikurangi beban yang belum ditutup disajikan dalam ekuitas; saldo yang sudah ditutup tidak dihitung lagi.

## Menyiapkan data

Accounting perlu mengumpulkan rekening koran dan kas opname untuk kas/bank; rincian tagihan untuk piutang; stok opname dan nilai persediaan; register aset dengan biaya tervalidasi dan akumulasi penyusutan; daftar utang; serta dokumen modal dan saldo laba. Jangan menjadikan nilai ekuitas sebagai angka pengisi selisih tanpa dasar.

MFK/unit terkait mengidentifikasi barang fisik, lokasi, kondisi, penanggung jawab, pemeliharaan, dan kalibrasi. Accounting memverifikasi nilai, akun, utang, modal, dan penyusutan sesuai pembagian kewenangan rumah sakit.

Harga sementara Excel dan jumlah inventaris **tidak otomatis masuk neraca**. Data fasilitas/kapasitas bukan otomatis aset individual. Tahun pengadaan lama juga bukan tanggal pembelian periode sekarang.

## Alur

1. Buka Saldo Awal sebagai Accounting. Pilih tanggal sebelum jurnal pertama yang sudah terposting. Saldo awal hanya dibuat sekali untuk awal pembukuan, bukan diulang setiap bulan/tahun.
2. Pilih akun rincian yang aktif, masukkan nominal positif dan sisi saldo. Pilihan Normal mengikuti sifat akun; akumulasi penyusutan biasanya bersaldo kredit sebagai pengurang aset.
3. Lengkapi dasar pencatatan. Total debit dan kredit harus sama sebelum draf dapat disimpan. Tidak ada penyeimbang otomatis.
4. Administrator Accounting/Kepala Keuangan memverifikasi dan menyetujui. Draf disetujui dapat dikembalikan untuk koreksi sebelum posting.
5. Accounting memposting. Jurnal saldo awal dibuat sekali; pengulangan permintaan tidak membuat jurnal ganda. Perubahan dan persetujuan tercatat dalam audit.
6. Buka Neraca dan pilih tanggal. Klik akun untuk memeriksa jurnal sumber; PDF tersedia dari halaman laporan.

Saldo awal terposting dan tanggal sebelumnya dikunci terhadap pembuatan/perubahan/penghapusan jurnal melalui model aplikasi. Koreksi memerlukan jurnal penyesuaian setelah tanggal saldo awal beserta rekonsiliasi. Riwayat sebelum saldo awal tidak direkonstruksi oleh modul ini. Akses SQL langsung berada di luar pengamanan model aplikasi.

## Batas dan rekonsiliasi

Laporan hanya mencakup jurnal yang telah terposting. Peringatan menunjukkan penerimaan/pengeluaran aktif tanpa jurnal setelah tanggal saldo awal; transaksi lama yang sudah dicakup saldo awal tidak dihitung sebagai transaksi periode berjalan. Selisih neraca ditampilkan apa adanya.

Penerapan saldo awal tidak otomatis merekonsiliasi subledger piutang, stok, bank, dan register aktiva. Aset yang nilainya sudah masuk saldo awal jangan diposting kembali sebagai pembelian baru melalui kapitalisasi. Rekonsiliasikan pencatatan aset lama sebelum membuat jurnal tambahan. Penutupan buku tahunan dan rekonsiliasi otomatis bukan bagian dari penambahan ini.

Migration: `2026_10_29_000005_create_opening_balances.php`. Migration sudah dijalankan pada database lokal; tidak ada saldo keuangan nyata yang dibuat atau diposting oleh implementasi ini.
