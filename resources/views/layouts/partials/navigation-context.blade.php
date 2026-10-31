<nav class="location-path" aria-label="Lokasi halaman" data-pdf-exclude>
    <a href="{{ route('dashboard') }}">Dashboard</a>
    <span data-current-location hidden><span aria-hidden="true"> / </span><span data-location-title aria-current="page"></span></span>
</nav>
<details class="navigation-help" data-pdf-exclude>
    <summary>Butuh bantuan menggunakan halaman?</summary>
    <div><p><strong>Mulai dari menu:</strong> cari nama pekerjaan, misalnya pemasukan atau neraca. Menu yang sedang dibuka diberi tanda warna dan garis.</p>
    <p><strong>Input data:</strong> isi kolom bertanda *, periksa nominal dan tanggal, lalu pilih tombol Simpan atau Posting. Jika ada kesalahan, petunjuknya muncul di atas form.</p>
    @can('viewAny',\App\Models\OpeningBalance::class)<a class="btn" href="{{ route('accounting.workspace') }}">Buka Ruang Kerja Accounting</a>@endcan
    <p><strong>Sulit membaca?</strong> pilih Teks besar atau Kontras tinggi. Gunakan Teks standar untuk tampilan lebih ringkas di laptop.</p></div>
</details>
