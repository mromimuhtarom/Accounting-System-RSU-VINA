# Tampilan responsif dan kemudahan penggunaan

Perubahan memakai layout Laravel existing, tanpa mengubah hak akses, validasi, saldo, atau aturan posting accounting.

- Teks utama 16 px, label lebih jelas, serta kontrol form setidaknya 50 px pada mode standar. Tombol utama berukuran minimal 48 px.
- Pengaturan **Teks standar**, **Teks besar** (18 px), dan **Kontras tinggi** tersedia di atas isi halaman. Pilihan disimpan pada perangkat melalui localStorage; halaman tetap berfungsi jika penyimpanan browser dibatasi.
- Form menggunakan satu kolom pada HP, dua pada area menengah, maksimal tiga pada area lebar. Input/edit aktiva dibagi menjadi empat bagian: identitas, lokasi/kondisi, perolehan, dan kalibrasi/keterangan.
- Daftar aktiva menjadi kartu berlabel pada layar hingga 700 px. Tombol Edit dan Hapus tetap mengikuti policy existing. Tanpa JavaScript, tabel tetap dapat digeser.
- Tabel laporan yang lebar memiliki petunjuk geser, area scroll terpisah, dan akses keyboard. Halaman tidak melebar mengikuti tabel.
- Menu konsisten pada breakpoint 1024 px, memiliki tombol **Menu** dan **Tutup menu**. Pada HP, fokus keyboard berada di dalam menu yang terbuka, Tab/Shift+Tab berputar di dalamnya, Escape menutup menu, lalu fokus kembali ke tombol Menu.
- Tautan **Lewati menu, langsung ke isi halaman**, asosiasi label/hint, status sukses, dan alert validasi membantu navigasi keyboard serta pembaca layar. Preferensi reduced motion tetap dihormati.
- Login mendapat input dan tombol lebih besar. Tombol keluar menampilkan teks **Keluar**. Placeholder pencarian dekoratif diganti petunjuk navigasi yang tidak menyerupai input aktif.
- Label kondisi/status aktiva pada daftar menggunakan bahasa Indonesia; nilai database tetap sama.

Aturan CSS baru dibatasi pada antarmuka `.app-shell`; renderer PDF menggunakan layout kertas existing. Petunjuk scroll dan pengaturan tampilan tidak ikut export PDF.

## Verifikasi browser lokal

```powershell
php scripts/check-responsive-ui.php
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check-responsive-ui.ps1 -Port 9332 -CheckPdf
```

Helper PHP hanya dapat dijalankan melalui CLI. Helper membaca database lokal memakai akun Admin Accounting ID 6, tanpa menyimpan transaksi. Fixture HTML, profil Chrome pengujian, screenshot, hasil `checks.json`, dan PDF berada di direktori temporary sistem `rsuve-responsive-ui-rs-vina-accounting1`, di luar webroot. Jalur Chrome dapat disesuaikan pada script PowerShell.

Pemeriksaan browser telah dijalankan pada lebar 320, 390, 768, 1440, dan 1920 px. Form, inventaris, dashboard, serta laporan tahunan tidak menghasilkan overflow horizontal halaman. Pengujian juga memeriksa mode teks besar/kontras tinggi, menu dan putaran fokus keyboard, pemrosesan nominal `2.500.000,50` menjadi `2500000.50`, serta unduhan PDF tahunan melalui html2pdf existing. Screenshot diverifikasi secara visual pada form dan dashboard HP, daftar aktiva HP, dan form desktop. Ini merupakan pemeriksaan praktis, bukan sertifikasi aksesibilitas formal.
# Navigasi untuk Accounting dan kenyamanan membaca

Penyempurnaan 30 Oktober 2026:

- Dashboard tetap tautan pertama, diikuti transaksi harian sesuai role. Accounting melihat **Penerimaan / Uang Masuk** dan **Pengeluaran / Uang Keluar**. Pembukuan, Laporan Transaksi, dan Data Pendukung dikelompokkan; kelompok halaman aktif terbuka otomatis. Hak akses tetap mengikuti role existing.
- **Cari menu** menyaring tautan yang memang tersedia untuk pengguna. Istilah pemasukan menemukan Penerimaan; utang/modal/nonkas menemukan Ruang Kerja. Hasil pada kelompok tertutup dibuka otomatis. Hapus pencarian mengembalikan keadaan menu sebelumnya. Pencarian tidak membuka akses ke modul lain.
- Penanda lokasi halaman dan judul di header membantu orientasi. Bantuan singkat tersedia dalam bagian lipat; tidak memenuhi layar saat tidak diperlukan.
- Teks standar tetap ringkas untuk laptop 13 inci. Teks besar dan kontras tinggi tetap dapat dipilih; **Atur ulang tampilan** mengembalikan teks standar tanpa kontras tambahan. Preferensi disimpan pada browser, bukan mengubah data aplikasi atau zoom browser.
- Menu ponsel mendukung tombol tutup, Escape, dan perpindahan fokus Tab/Shift+Tab. Tautan dalam kelompok tertutup tidak dimasukkan dalam urutan fokus buatan.

Pemeriksaan browser pada `scripts/check-responsive-ui.ps1` mencakup pencarian dengan sinonim, membuka hasil bertingkat, hasil kosong, pemulihan kelompok menu, keyboard, reset tampilan, serta lebar halaman. Fixture tetap baca saja; pengujian tidak mengirim form transaksi.

