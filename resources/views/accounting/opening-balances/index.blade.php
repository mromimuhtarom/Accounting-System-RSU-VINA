@extends('layouts.app')
@section('content')
@include('reports.partials.financial-workflow')
<div class="card"><h2>Saldo Awal Keuangan</h2><p>Masukkan posisi keuangan pada akhir tanggal saldo awal, sebelum transaksi pertama dalam buku ini. Data diambil dari laporan atau rekonsiliasi Accounting, bukan dari jumlah inventaris Excel.</p><div class="actions"><a class="btn" href="{{ route('reports.financial-position') }}">Lihat Neraca</a><a class="btn" href="{{ route('reports.financial-trial-balance') }}">Neraca Saldo</a></div></div>
<div class="card"><h3>Data apa yang disiapkan?</h3><div class="table-wrap"><table><thead><tr><th>Kelompok</th><th>Contoh saldo</th><th>Dokumen pendukung</th></tr></thead><tbody>
<tr><td>Aset</td><td>Kas/bank, piutang, persediaan, harga aset tetap dan akumulasi penyusutannya</td><td>Rekening koran, rekonsiliasi kas, rincian piutang, stok, dan daftar aset</td></tr><tr><td>Kewajiban</td><td>Utang supplier, pinjaman, kewajiban lain yang masih harus dibayar</td><td>Rincian tagihan dan sisa pelunasannya</td></tr><tr><td>Ekuitas / modal</td><td>Saldo ekuitas yang sudah diverifikasi Accounting</td><td>Laporan sebelumnya dan dokumen perubahan ekuitas</td></tr>
</tbody></table></div><p>Saldo pendapatan/beban sebelum tanggal awal direkonsiliasi ke saldo ekuitas sesuai pembukuan sebelumnya. Akun induk tidak dipilih; gunakan akun COA yang paling rinci. Akumulasi penyusutan tetap dicatat pada akun kontra-asetnya.</p>
@if($firstJournalDate)<p><strong>Jurnal pertama yang tersedia:</strong> {{ substr($firstJournalDate,0,10) }}. Pilih tanggal saldo awal sebelumnya setelah memastikan data tidak dihitung dua kali.</p>@else<p>Belum ada jurnal terposting dalam buku ini.</p>@endif
</div>
@if(!$balance || $balance->status==='DRAFT')
@can('manage',\App\Models\OpeningBalance::class)
<div class="card"><h3>{{ $balance ? 'Edit draft saldo awal' : 'Input saldo awal' }}</h3><form method="POST" action="{{ route('opening-balances.store') }}" data-opening-form>@csrf
<input type="hidden" name="version" value="{{ old('version',$balance?->version??0) }}">
<div class="form-grid"><div class="field"><label for="opening-date">Tanggal saldo awal *</label><input id="opening-date" type="date" name="balance_date" value="{{ old('balance_date',$balance?->balance_date?->format('Y-m-d')) }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required><small>Posisi akhir hari, bukan tanggal input ke aplikasi.</small></div><div class="field"><label for="opening-evidence">Referensi dokumen dan hasil rekonsiliasi *</label><textarea id="opening-evidence" name="evidence" minlength="10" maxlength="10000" required>{{ old('evidence',$balance?->evidence) }}</textarea></div></div>
<p>Isi nominal positif. Pilihan <strong>Saldo normal akun</strong> mengikuti COA: sistem menentukan debit/kreditnya. Jika saldonya berlawanan dengan saldo normal, Accounting dapat memilih posisi secara langsung. Baris kosong atau nominal nol dilewati.</p>
<div class="table-wrap"><table><thead><tr><th>Akun</th><th>Nominal saldo (Rp)</th><th>Posisi saldo</th><th>Aksi</th></tr></thead><tbody data-opening-rows>
@foreach(old('lines',$formRows) as $index=>$row) @include('accounting.opening-balances._row') @endforeach
</tbody></table></div><button type="button" class="btn" data-add-opening-row>Tambah akun</button>
<template data-opening-template>@include('accounting.opening-balances._row',['index'=>'__INDEX__','row'=>[]])</template>
<div class="card" style="margin-top:18px" aria-live="polite"><p data-opening-total>Isi saldo sesuai dokumen untuk memeriksa keseimbangan.</p><p>Sistem tidak membuat angka modal atau akun penampung untuk menutup selisih. Simpan setelah total debit dan kredit sama.</p></div>
<button class="btn primary">Simpan draft saldo awal</button></form></div>
@push('scripts')<script src="{{ asset('public/js/opening-balances.js') }}?v=20261029" defer></script>@endpush
@else<div class="card">Draft saldo awal hanya dapat dilengkapi oleh Accounting.</div>@endcan
@endif
@if($balance)
<div class="card"><h3>Status: {{ ['DRAFT'=>'Draft, belum memengaruhi neraca','APPROVED'=>'Disetujui, menunggu posting','POSTED'=>'Terposting'][$balance->status] }}</h3><p>Per {{ $balance->balance_date->format('d/m/Y') }} · {{ $balance->evidence }}</p>
<div class="table-wrap"><table><thead><tr><th>Akun</th><th>Debit</th><th>Kredit</th></tr></thead><tbody>@foreach($balance->lines as $line)<tr><td>{{ $line->account->code }} · {{ $line->account->name }}</td><td>Rp {{ \App\Services\LedgerAmounts::display(\App\Services\LedgerAmounts::cents($line->debit)) }}</td><td>Rp {{ \App\Services\LedgerAmounts::display(\App\Services\LedgerAmounts::cents($line->credit)) }}</td></tr>@endforeach</tbody></table></div>
@if($balance->status==='DRAFT') @can('approve',\App\Models\OpeningBalance::class)<form method="POST" action="{{ route('opening-balances.approve',$balance) }}">@csrf<input type="hidden" name="version" value="{{ $balance->version }}"><p><label><input type="checkbox" name="verified" value="1" required> Saldo telah dicocokkan dengan dokumen, akun dan tanggal benar, serta tidak menggandakan jurnal atau aset lama.</label></p><button class="btn primary">Setujui saldo awal</button></form>@endcan @endif
@if($balance->status==='APPROVED')<div class="actions">@can('manage',\App\Models\OpeningBalance::class)<form method="POST" action="{{ route('opening-balances.post',$balance) }}">@csrf<input type="hidden" name="version" value="{{ $balance->version }}"><button class="btn primary">Posting saldo awal ke jurnal</button></form>@endcan</div>@can('approve',\App\Models\OpeningBalance::class)<details><summary>Kembalikan ke draft untuk koreksi</summary><form method="POST" action="{{ route('opening-balances.draft',$balance) }}">@csrf<input type="hidden" name="version" value="{{ $balance->version }}"><div class="field"><label>Alasan koreksi</label><textarea name="reason" minlength="5" maxlength="2000" required></textarea></div><button class="btn">Kembalikan ke draft</button></form></details>@endcan @endif
@if($balance->status==='POSTED')<p>Jurnal sumber: <strong>{{ $balance->journal->journal_no }}</strong>. Saldo awal ini tidak dapat diposting ulang atau diubah melalui form; koreksi perlu jurnal penyesuaian yang direkonsiliasi Accounting.</p>@endif
</div><div class="card"><details><summary>Riwayat saldo awal</summary>@foreach($audits as $audit)<p>{{ $audit->created_at }} · {{ $audit->action }} · petugas #{{ $audit->actor_id }}</p>@endforeach</details></div>
@endif
@endsection
