# Optimasi request transaksi dan login

Perubahan ini mengurangi pembacaan database berulang, tanpa menonaktifkan audit,
validasi, transaksi database, penguncian jurnal/saldo awal, atau hashing password.

## Pengukuran

Skenario SQLite terisolasi: kirim 20 pengeluaran sekaligus, termasuk jurnal dan audit.

| Ukuran | Sebelum | Sesudah |
|---|---:|---:|
| Query database | 1.084 | 535 |
| Pemeriksaan metadata/schema | 322 | 8 |

Jumlah query berkurang sekitar 51%. Ini bukan klaim waktu respons turun 51%:
latensi aktual bergantung pada jumlah data, database, PHP/Apache, disk, dan jaringan.
`TransactionPerformanceTest` membatasi regresi query; login dan heartbeat juga
memiliki pemeriksaan batas query melalui `AuthenticationSessionTest`.

## Perubahan

- Metadata tabel dan label audit digunakan ulang selama satu HTTP request saja.
  Jika master diubah dalam request yang sama, label terkait dibatalkan agar audit
  tetap membaca nama terbaru. Tidak ada cache lintas pengguna/request untuk data ini.
- Role pengguna yang sudah dibaca digunakan ulang. Perubahan role sendiri memuat
  ulang relasi agar identitas audit sesuai keadaan terbaru.
- Heartbeat hanya membaca/memperbarui sesi perangkat saat ini; pembaruan bersyarat
  tidak menghidupkan kembali sesi yang sudah logout, dan tidak memakai transaksi
  serta SELECT FOR UPDATE pada setiap halaman.
- Request biasa tidak menyapu semua sesi kedaluwarsa. Batas idle perangkat sendiri
  tetap diperiksa setiap request. Pencatatan global memakai scheduler yang sudah ada;
  halaman Log Login / Logout juga menyegarkan sesi kedaluwarsa saat dibuka.
- Indeks ditambahkan untuk tanggal/jenis transaksi, riwayat pengguna, nama dokter,
  referensi jurnal, dan pencarian sesi. Indeks baru tidak mengganti data/transaksi.
- Filter daftar membandingkan langsung kolom DATE sehingga database dapat memakai
  indeks tanggal tanpa membungkus kolom dengan fungsi DATE().

## Operasional

Jalankan migrasi indeks saat deploy:

```powershell
php artisan migrate --path=database/migrations/2026_11_13_000002_add_transaction_performance_indexes.php
```

Jalankan scheduler Laravel setiap menit agar logout otomatis perangkat yang browsernya
sudah ditutup tetap dicatat tepat waktu. Jadwal `sessions:expire-log` sudah terdaftar
di `routes/console.php`. Pada Windows gunakan Task Scheduler untuk menjalankan
`php artisan schedule:run` dari folder aplikasi setiap menit; pada server gunakan
scheduler/cron yang sudah disediakan hosting. Untuk menjalankan manual:

```powershell
php artisan sessions:expire-log
```

Pada deployment production dengan konfigurasi final, cache konfigurasi, route, dan
view menggunakan `php artisan optimize`. Setelah mengubah `.env`/route, perbarui
cache deployment. Jangan menyalakan cache konfigurasi production sebelum menjalankan
pengujian dengan database testing yang terisolasi. Cache production tidak diaktifkan
otomatis di workspace pengembangan ini agar perubahan kode dan `.env` tetap terbaca.
