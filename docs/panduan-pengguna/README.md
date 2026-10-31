# Panduan pengguna sistem Accounting

Dokumen terbaru untuk dibagikan: [PDF panduan lengkap 17 halaman, termasuk praktik neraca](../Panduan-Penggunaan-Sistem-RSU-Vina-Estetica-Lengkap-Neraca.pdf). Halaman 15–17 menjelaskan cara input agar neraca terisi, contoh hasil, serta batas pemetaan jurnal Penerimaan pada form saat ini.

Sumber yang dapat diedit: [panduan.html](panduan.html). Buka melalui browser untuk membaca atau memperbarui materi. Daftar isi memiliki tautan internal. Semua angka contoh bersifat ilustrasi; gambar menggunakan form kosong tanpa data pasien.

Untuk membangun ulang PDF pada lingkungan Windows dengan Chrome yang digunakan proyek:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/print-user-guide.ps1 -OutputName Panduan-Penggunaan-Sistem-RSU-Vina-Estetica-Lengkap-Neraca.pdf
```

Script memeriksa gambar, jumlah halaman, dan potensi isi yang menimpa footer. PDF memakai ukuran A4, teks yang dapat dipilih, serta struktur heading/tag dari Chrome. Proses tidak membaca atau mengubah database aplikasi. Gambar ada di folder `assets`; logo menggunakan berkas logo existing pada `public/images`.
