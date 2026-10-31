# Sukoco — Asisten Sistem

Tombol **Sukoco** ada di kanan bawah halaman setelah login. Panel menyediakan panduan tetap, tautan ke modul, dan pencarian terstruktur pada database lokal. Implementasi ini bukan AI generatif dan tidak membutuhkan kunci API. Tidak ada pesan atau data yang dikirim ke penyedia AI eksternal.

## Penggunaan

1. Klik Sukoco. Pilih contoh pertanyaan atau ketik pada kolom Pertanyaan Anda.
2. Contoh panduan: `cara input pemasukan`, `bagaimana agar neraca muncul`, `cara saldo awal`, `cara inventaris`, `cara ekspor PDF`, atau `bantu saya menggunakan halaman ini`.
3. Contoh pencarian: `cari pemasukan RCV-001`, `cari inventaris AST-768`, `cari klaim asuransi BPJS`, `cari pengeluaran listrik 2026-01-01 2026-01-31`, atau `tolong cari pemasukan januari 2026`. Nama bulan lengkap dengan tahun, hari ini, bulan ini, dan bulan lalu didukung.
4. Untuk pilihan yang pasti, buka **Cari Data**, pilih sumber, kata kunci dan tanggal, lalu Cari Data. Kata kunci kosong menampilkan paling banyak lima catatan pertama/terbaru sesuai urutan sumber.
5. Contoh ringkasan: `total pemasukan bulan ini`, `total pengeluaran bulan lalu`, `total neraca`, `ringkasan neraca 2026-01-31`. Ringkasan neraca hanya bagi role pembukuan.
6. Klik tautan hasil untuk memeriksa halaman sumber. Tautan penerimaan/pengeluaran membuka daftar pada tanggal transaksi; laporan tertentu tidak mempunyai filter kata kunci sehingga perlu mencocokkan referensi pada daftar.

Enter mengirim pertanyaan; Shift+Enter membuat baris baru. Tutup atau Escape menutup panel. Bersihkan percakapan menghapus pesan pada panel dan membatalkan permintaan aktif. Riwayat hanya berada di memori halaman, tidak disimpan ke localStorage/database dan tidak dibawa saat berpindah halaman. Pertanyaan diproses mandiri, dengan konteks nama halaman; asisten tidak memahami semua variasi bahasa atau percakapan bebas berantai. Bila tidak cocok dengan pola yang didukung, gunakan Cari Data atau halaman modul.

## Batas data dan akses

### Mengganti nama dan foto (khusus IT)

Login sebagai IT → **Profil Asisten**. Isi nama (2–60 karakter), pilih foto JPG/PNG/WebP maksimal 2 MB dengan dimensi 32–2048 piksel pada setiap sisi, lalu **Simpan Profil Asisten**. Pratinjau tidak mengubah profil sampai form disimpan. Foto persegi disarankan. Untuk menghapus foto, pilih Hapus foto tanpa mengunggah foto baru; tampilan kembali ke inisial nama.

Nama dipakai pada tombol, judul, sapaan dan label balasan. Foto dipakai pada tombol/panel. Pengaturan berlaku global saat halaman pengguna dimuat kembali; sesi chat pada halaman lama tetap menggunakan profil saat halaman tersebut dibuka. Hak akses, panduan dan kemampuan analisis tidak diubah. Nama default Sukoco dipakai bila pengaturan belum tersedia.

Migration `2026_10_30_000001_create_assistant_profiles_table.php` sudah diterapkan pada database lokal. Foto disimpan pada disk local privat (`assistant-profiles`), dilayani melalui `/asisten/foto` bagi akun aktif yang login sehingga tidak membutuhkan storage symlink. Perubahan dicatat di Log Perubahan Data. Versi pengaturan menolak penyimpanan dari tab yang sudah tertinggal; foto lama dibersihkan setelah commit. Pengaturan foto tidak bergantung pada ekstensi GD.

### Analisis laporan dan grafik

Buka **Analisis Laporan & Grafik** pada panel, pilih jenis analisis dan rentang tanggal (maksimal 366 hari). Tombol **Analisis Periode Halaman Ini** mengambil parameter tanggal/tahun/bulan dari halaman yang sedang dibuka bila tersedia; pada Dashboard/tahunan tanpa parameter menggunakan tahun berjalan. Filter akun, penjamin, unit, dan kata kunci halaman **tidak** diterapkan. Jawaban selalu menuliskan periode dan cakupan.

Contoh pertanyaan: `analisa laporan januari 2026`, `baca grafik neraca 2026-01-01 2026-01-31`, `analisa inventaris`, `analisa klaim januari 2026`, `analisis laporan tahun 2026`.

- **Laporan transaksi:** seluruh penerimaan dan pengeluaran ACTIVE (termasuk referal), sesuai basis laporan transaksi existing. Pembanding otomatis adalah periode tepat sebelumnya dengan jumlah hari yang sama, dan tanggalnya disebutkan. Grafik menunjukkan nilai pembanding, tren penerimaan bulanan, dan tren pengeluaran bulanan. Bulan tanpa catatan ditampilkan nol. Persentase hanya dihitung bila nilai pembanding positif; periode parsial diberi keterangan. Nilai nominal memakai perhitungan sen integer, grafik memakai proporsi visual dengan label nominal dari server.
- **Neraca:** `AccountingAnalysis`/`FinancialPosition` membandingkan posisi akhir sehari sebelum awal periode dengan akhir periode, mencakup jurnal POSTED. Peringatan saldo awal, ekuitas negatif, dan selisih ditampilkan. Grafik memperlihatkan aset, kewajiban, ekuitas; nilai negatif dilabeli dan panjang batang memakai nilai absolut.
- **Inventaris:** catatan aktif terkini per jenis, jumlah unit nonfasilitas, fasilitas/kapasitas, kondisi rusak dan kalibrasi jatuh tempo sampai 30 hari. Bukan rekonstruksi inventaris historis.
- **Klaim:** kondisi tracking terkini untuk periode bulan terpilih, nilai diajukan/disetujui/cair/sisa, jumlah belum diverifikasi dan anomali nominal. Klaim ditolak tidak menambah sisa disetujui. Bukan arus kas atau riwayat piutang pada tanggal lampau.

Analisis transaksi dan neraca mengikuti policy laporan keuangan; kasir/asuransi tidak memperoleh akses laporan keuangan global hanya karena dapat mencari sebagian transaksi. Analisis inventaris/claim mengikuti policy/role modulnya. Narasi merupakan deskripsi perhitungan, tidak menebak sebab bisnis atau menilai kesehatan rumah sakit. Selisih penerimaan-pengeluaran tidak disebut laba atau saldo kas. Tautan laporan sumber disertakan untuk pemeriksaan lebih lanjut.

Analisis dihitung dari data di balik laporan, bukan membaca gambar/screenshot atau file PDF yang diunggah. Tidak ada layanan AI generatif tambahan, perubahan data, ataupun jurnal yang dibuat oleh analisis.

- Endpoint selalu memeriksa login, akun aktif, role/policy dan sumber yang diizinkan. Pilihan dari browser dan nama halaman tidak memberikan hak akses tambahan.
- Penerimaan hanya transaksi ACTIVE. Asuransi dibatasi penjamin kategori BPJS, PRIVATE_INSURANCE, CORPORATE, OTHER seperti modul penerimaan asuransi existing. Role kasir tetap mengikuti cakupan daftar penerimaan existing, yang dapat membaca seluruh penerimaan.
- Pengeluaran hanya ACTIVE jenis GENERAL; bukan pencarian pengeluaran referal.
- Inventaris memakai policy Asset, tidak termasuk soft-deleted. Ini kondisi terkini; tidak menerima filter tanggal historis.
- Klaim mengikuti role halaman klaim. Filter tanggal menjadi rentang periode tahun/bulan; nominal/status tetap kondisi tracking terkini, bukan saldo piutang pada tanggal lampau. Tidak membuat penerimaan atau jurnal.
- COA hanya akun aktif bagi pengguna yang boleh membaca master akun atau laporan keuangan.
- Neraca memakai `FinancialPosition`, jurnal POSTED, cutoff tanggal yang valid, serta peringatan saldo awal/selisih. Total penerimaan/pengeluaran tercatat bukan saldo kas, laba, atau total aset.
- Hasil pencarian paling banyak lima baris dan jumlah kecocokan sesuai cakupan pengguna. Nomor rekam medis/nama pasien tidak disertakan sebagai rincian jawaban; transaksi ditampilkan dengan referensi, tanggal dan nominal. Periksa catatan lengkap di modul yang berwenang.

Tidak ada fungsi tambah/edit/hapus, posting, SQL bebas, akses file bebas, atau pencarian internet. Nama sumber, kolom dan tautan ditentukan server; pencarian memakai parameter terikat dan wildcard literal. Jawaban dirender sebagai teks, tautan dibatasi origin aplikasi. Endpoint dibatasi 30 permintaan/menit, menggunakan CSRF untuk POST, dan jawaban tidak di-cache. Aktivitas asisten tidak mengubah tabel bisnis.

`GET /asisten/panduan` menayangkan PDF yang sudah tersedia melalui autentikasi. Panduan umum dapat dibaca akun aktif; tombol aksi modul tetap mengikuti kewenangan masing-masing.

Komponen: `SystemAssistant`, `SystemAssistantSearch`, `SystemAssistantAccess`, `SystemAssistantRequest`, `SystemAssistantController`, partial `system-assistant`, CSS/JS tersendiri. Profil memakai `AssistantProfile`, policy/request/service dan halaman IT tersendiri beserta migration di atas. Tidak ada konfigurasi layanan AI tambahan. Uji backend mencakup pembatasan role, lingkup asuransi, tanggal, jumlah hasil, escaping query, ringkasan jurnal, profil/foto dan audit. Uji browser memakai balasan tiruan lokal agar tidak mengirim form maupun membaca transaksi nyata dari fixture HTML.
