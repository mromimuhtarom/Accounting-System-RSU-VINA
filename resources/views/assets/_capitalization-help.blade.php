@if($asset->status!=='ACTIVE')
<div class="asset-report-note"><strong>Pengisian kapitalisasi tidak tersedia.</strong><p>Aktiva ini sudah tidak aktif atau telah dilepas. Riwayat accounting tetap dapat diperiksa pada pilihan bulan di atas.</p></div>
@elseif($asset->inventory_type==='FACILITY')
<div class="asset-report-note"><strong>Ini adalah parameter fasilitas / kapasitas.</strong><p>Catatan ini menjelaskan ketersediaan atau kapasitas fasilitas, sehingga tidak ditampilkan sebagai unit aktiva untuk kapitalisasi. Data unit fisik yang memenuhi syarat perlu diidentifikasi dan dicatat tersendiri.</p><a class="btn" href="#asset-data-origin">Lihat data sumber</a></div>
@elseif($asset->quantity!==1)
<div class="asset-report-note"><strong>Form kapitalisasi belum tersedia untuk catatan agregat.</strong><p>AST-{{ $asset->id }} · {{ $asset->name }} tercatat sebanyak <strong>{{ $asset->quantity }} {{ $asset->inventory_type==='SOFTWARE'?'instalasi':'unit' }}</strong>. Proses kapitalisasi aplikasi menggunakan satu unit yang teridentifikasi per catatan.</p>
@if($asset->inventory_type==='SOFTWARE')<p>Daftar software menunjukkan jumlah instalasi. Accounting perlu memeriksa bukti lisensi atau pembelian, unit lisensi yang diakui, dan harga perolehannya.</p>@else<p>Identifikasi setiap unit beserta nomor inventaris/seri, harga perolehan, dan dokumen pendukungnya.</p>@endif
<p>Pertahankan jumlah sesuai sumber; lakukan identifikasi dan rekonsiliasi unit sebelum kapitalisasi agar inventaris tidak terhitung ganda. Catatan ini tetap tersimpan sebagai inventaris operasional.</p><a class="btn" href="#asset-data-origin">Lihat jumlah dan harga dari sumber</a></div>
@else
@cannot('account',\App\Models\Asset::class)<div class="asset-report-note"><strong>Halaman ini hanya menampilkan informasi accounting untuk akun Anda.</strong><p>Form pengisian kapitalisasi tersedia bagi role Accounting, Admin Accounting, atau Kepala Keuangan. Approval dilakukan oleh administrator aset atau pihak yang berwenang.</p></div>@endcannot
@if(!$cap || $cap->status==='DRAFT')
@can('account',\App\Models\Asset::class)
<details style="margin:12px 0"><summary>Panduan mengisi kapitalisasi</summary><div class="table-wrap"><table><thead><tr><th>Isian</th><th>Data yang digunakan</th></tr></thead><tbody>
<tr><td>Harga perolehan tervalidasi</td><td>Nilai yang sudah dicocokkan dengan faktur, kuitansi, kontrak, atau berita acara perolehan. Harga sementara dari Excel masih perlu diverifikasi.</td></tr>
<tr><td>Tanggal perolehan</td><td>Tanggal pada dokumen perolehan. Tahun pengadaan saja belum menentukan tanggal lengkap.</td></tr>
<tr><td>Tanggal mulai penyusutan dan sisa masa manfaat</td><td>Tanggal serta sisa masa manfaat dalam bulan yang ditetapkan Accounting berdasarkan dokumen dan kebijakan rumah sakit.</td></tr>
<tr><td>Nilai residu</td><td>Nilai residu yang disetujui Accounting.</td></tr>
<tr><td>Akumulasi penyusutan awal</td><td>Saldo penyusutan yang sudah tercatat sebelum proses awal ini, berdasarkan daftar aktiva atau buku besar lama.</td></tr>
<tr><td>Nilai buku awal</td><td>Harga perolehan dikurangi akumulasi penyusutan awal.</td></tr>
<tr><td>Akun dan bukti</td><td>Pilih akun sesuai COA rumah sakit, termasuk akun lawan saldo awal/kapitalisasi. Cantumkan referensi dokumen serta rekonsiliasi saldo sebelumnya.</td></tr>
</tbody></table></div><p>Simpan draft, ajukan approval, kemudian posting pada periode terbuka. Bulan grafik kapitalisasi mengikuti tanggal jurnal posting; penyusutan diposting per bulan setelahnya.</p></details>
@endcan
@endif
@endif
