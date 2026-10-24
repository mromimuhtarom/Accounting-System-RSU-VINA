@extends('layouts.app')
@section('content')
<style>
.rsv-dash-filter{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.rsv-dash-filter label{font-weight:700;color:#31495a;font-size:13px}.rsv-dash-filter select{min-width:115px;padding:10px;border:1px solid #d6e5df;border-radius:11px;background:#fff}.rsv-dash-filter button{padding:10px 18px;border:0;border-radius:11px;background:#079761;color:#fff;font-weight:700;cursor:pointer}
.rsv-note{padding:13px 16px;border:1px solid #e4eadf;background:#f8faf7;border-radius:12px;color:#526978;line-height:1.6;font-size:13px;margin-bottom:16px}.rsv-warning{border-color:#f0dfb8;background:#fffbf2}
.rsv-bars{display:grid;gap:13px;margin-top:20px}.rsv-bar-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;font-size:14px}.rsv-bar-head small{color:#627686}.rsv-track{height:12px;border-radius:999px;background:#e8efeb;overflow:hidden;margin-top:7px}.rsv-fill{height:100%;background:linear-gradient(90deg,#059966,#7acb38);border-radius:999px}.rsv-chart-total{font-size:12px;color:#6a7b88;margin-top:8px}.rsv-stat-note{font-size:12px;color:#697f8d;margin-top:7px}
@media(max-width:900px){.rsv-dash-filter{width:100%}.rsv-dash-filter select{width:auto}}
</style>
<div class="page-head">
 <div><h1 class="page-title">Dashboard</h1><p class="page-sub">Ringkasan penerimaan dan pengeluaran berdasarkan periode transaksi</p></div>
 @if($role !== 'KASIR' && $role)
 <form class="rsv-dash-filter" method="GET" action="{{ route('dashboard') }}">
 <label for="dashboard-year">Periode tahun</label>
 <select id="dashboard-year" name="tahun">@for($y=now()->year+1;$y>=2020;$y--)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endfor</select>
 <button type="submit">Tampilkan</button></form>
 @else <span class="badge">{{ now()->translatedFormat('F Y') }}</span> @endif
</div>
@if(!$role)
<div class="card"><h3 class="section-title">Role Accounting belum terhubung</h3><p class="muted">Hubungkan akun dengan role aplikasi ACCOUNTING melalui Manajemen Akun.</p></div>
@endif
@if($role==='KASIR')
<div class="stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
 <div class="card stat-card"><div class="stat-icon green">＋</div><div class="stat-label">Transaksi Hari Ini</div><div class="stat">{{ number_format($stats['today_count']) }}</div><div class="stat-note">Transaksi aktif oleh Anda</div></div>
 <div class="card stat-card"><div class="stat-icon blue">Rp</div><div class="stat-label">Pembayaran Tercatat Hari Ini</div><div class="stat">Rp {{ number_format($stats['today_income'],0,',','.') }}</div><div class="stat-note">Metode kas, transfer, QRIS, kartu</div></div>
</div>
<div class="card"><h3 class="section-title">Input Penerimaan</h3><a class="btn primary" href="{{ route('cashier.receipts.create') }}">＋ Input Penerimaan</a></div>
@endif
@if(in_array($role,['ACCOUNTING','ADMIN_ACCOUNTING','ATASAN','FINANCE','KEPALA_KEUANGAN','DIREKTUR','OWNER']))
<div class="rsv-note">Ringkasan <strong>{{ $year }}</strong> dihitung dari transaksi berstatus <strong>ACTIVE</strong>. Total Penerimaan Tercatat mencakup semua penerimaan aktif termasuk transaksi historis yang metodenya kosong. Jumlah ini <strong>bukan bukti seluruh uang telah cair</strong>. Penerimaan terindikasi dibayar hanya mengambil metode CASH, TRANSFER, QRIS, dan CARD. Transaksi metode GUARANTOR dan metode kosong <strong>tidak otomatis dianggap uang cair</strong>. Data pembayaran belum direkonsiliasi dengan mutasi bank.</div>
<div class="stats-grid">
 <div class="card stat-card"><div class="stat-icon green">↙</div><div class="stat-label">Total Penerimaan Tercatat</div><div class="stat">Rp {{ number_format($stats['total_records'],0,',','.') }}</div><div class="rsv-stat-note">Seluruh penerimaan ACTIVE pada {{ $year }}</div></div>
 <div class="card stat-card"><div class="stat-icon red">↗</div><div class="stat-label">Total Pengeluaran</div><div class="stat">Rp {{ number_format($stats['expense'],0,',','.') }}</div><div class="rsv-stat-note">Pengeluaran aktif tahun {{ $year }}</div></div>
 <div class="card stat-card"><div class="stat-icon blue">↗</div><div class="stat-label">Selisih Pencatatan – Pengeluaran</div><div class="stat">Rp {{ number_format($stats['total_records']-$stats['expense'],0,',','.') }}</div><div class="rsv-stat-note">Bukan saldo kas dan bukan laba rugi</div></div>
 <div class="card stat-card"><div class="stat-icon purple">▤</div><div class="stat-label">Jumlah Transaksi Penerimaan</div><div class="stat">{{ number_format($stats['total_record_count']) }}</div><div class="rsv-stat-note">Seluruh transaksi penerimaan aktif</div></div>
</div>
<div class="card" style="margin-bottom:18px">
 <h3 class="section-title">Pemeriksaan Data yang Perlu Direkonsiliasi</h3>
 <div class="rsv-note rsv-warning" style="margin-bottom:0">Terindikasi pembayaran (CASH/TRANSFER/QRIS/CARD): <strong>Rp {{ number_format($stats['income'],0,',','.') }}</strong> ({{ number_format($stats['transactions']) }} transaksi). Metode GUARANTOR: <strong>Rp {{ number_format($stats['tagihan'],0,',','.') }}</strong> ({{ number_format($stats['tagihan_count']) }} transaksi). Metode pembayaran kosong: <strong>Rp {{ number_format($stats['unknown_method'],0,',','.') }}</strong> ({{ number_format($stats['unknown_method_count']) }} transaksi). Penjamin belum dipilih pada penerimaan pembayaran: <strong>Rp {{ number_format($stats['unclassified'],0,',','.') }}</strong>. Nilai ini perlu dicek sebelum dijadikan dasar pelaporan kas atau komposisi penjamin final.</div>
</div>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(280px,1fr)">
 <div class="card">
  <h3 class="section-title">Komposisi Penerimaan Berdasarkan Penjamin</h3>
  <p class="page-sub">Grafik menggunakan semua transaksi penerimaan ACTIVE pada tahun terpilih dan dikelompokkan menurut nama penjamin. Nilai ini adalah penerimaan tercatat, bukan bukti pencairan.</p>
  @php($basis = max((float) $stats['total_records'], 1))
  <div class="rsv-bars">
  @forelse($guarantorComposition as $g)
   @php($percent = $stats['total_records'] > 0 ? 100 * (float)$g->total / (float)$stats['total_records'] : 0)
   <div><div class="rsv-bar-head"><strong>{{ $g->guarantor_name }}</strong><span>Rp {{ number_format($g->total,0,',','.') }} <small>({{ number_format($percent,1,',','.') }}%) · {{ number_format($g->transactions) }} trx</small></span></div>
   <div class="rsv-track"><div class="rsv-fill" style="width:{{ number_format(min(100,max(0,$percent)),2,'.','') }}%"></div></div></div>
  @empty
   <p class="muted">Belum ada transaksi penerimaan aktif pada periode ini.</p>
  @endforelse
  </div><div class="rsv-chart-total">Total dasar grafik: Rp {{ number_format($stats['total_records'],0,',','.') }}. Transaksi tanpa penjamin ditampilkan sebagai “Belum ada penjamin”.</div>
 </div>
 <div class="card"><h3 class="section-title">Akses Cepat</h3><div class="quick-grid">
 @if(in_array($role,['ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN']))
 <a class="quick-link" href="{{ route('accounting.receipts.index') }}">↙ Penerimaan</a><a class="quick-link" href="{{ route('accounting.pengeluaran.create') }}">↗ Input Pengeluaran</a>
 @if(in_array($role,['ACCOUNTING','KEPALA_KEUANGAN']))<a class="quick-link" href="{{ route('accounting.jurnal.create') }}">▤ Jurnal Manual</a>@endif
 @endif
 <a class="quick-link" href="{{ route('reports.income') }}">▧ Laporan Penerimaan</a>
 <a class="quick-link" href="{{ route('reports.expense') }}">▧ Laporan Pengeluaran</a>
 </div></div>
</div>
<div class="card"><h3 class="section-title">Transaksi Penerimaan Terbaru — {{ $year }}</h3><div class="table-wrap"><table>
 <thead><tr><th>No Transaksi</th><th>Tanggal</th><th>Pasien</th><th>Unit</th><th>Penjamin</th><th>Metode</th><th>Kasir</th><th>Nominal</th></tr></thead>
 <tbody>@forelse($latestReceipts as $r)
 <tr><td><strong>{{ $r->transaction_no }}</strong></td><td>{{ $r->transaction_date?->format('d M Y') ?? '-' }}</td><td>{{ $r->patient?->name ?? $r->patient_name ?? '-' }}</td><td>{{ $r->serviceUnit?->name ?? '-' }}</td><td>{{ $r->guarantor?->name ?? 'Belum ada penjamin' }}</td><td>{{ $r->payment_method ?? 'Belum diisi' }}</td><td>{{ $r->cashier?->name ?? '-' }}</td><td class="money">Rp {{ number_format($r->amount,0,',','.') }}</td></tr>
 @empty <tr><td colspan="8" class="muted">Tidak ada transaksi aktif pada tahun {{ $year }}.</td></tr>@endforelse</tbody>
 </table></div></div>
@endif
@if(in_array($role,['ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN','OWNER','DIREKTUR']))
@php($claimsByGuarantor=\App\Models\InsuranceMonthlyClaim::with('guarantor')->where('year',request('year',now()->year))->select('guarantor_id')->selectRaw('SUM(claimed_amount) total, SUM(COALESCE(approved_amount,0)) approved, SUM(paid_amount) paid')->groupBy('guarantor_id')->get())
<div class="card" style="margin:18px 0"><div class="actions"><h3 style="flex:1">Grafik Klaim & Piutang Asuransi — {{request('year',now()->year)}}</h3><a class="btn" href="{{route('insurance.index',['year'=>request('year',now()->year)])}}">Lihat Detail Klaim</a></div>
@php($claimMax=max(1,(float)$claimsByGuarantor->max('total')))
@forelse($claimsByGuarantor as $row)<div style="margin:14px 0"><div style="display:flex;justify-content:space-between"><strong>{{$row->guarantor?->name??'Tanpa Penjamin'}}</strong><span>Rp {{number_format($row->total,0,',','.')}} · Sisa Rp {{number_format(max(0,$row->approved-$row->paid),0,',','.')}}</span></div><div style="background:#eaf3ee;height:12px;border-radius:8px;overflow:hidden"><div style="background:#029f6b;width:{{100*$row->total/$claimMax}}%;height:100%"></div></div></div>@empty<p>Belum ada klaim bulanan yang diinput ke modul Asuransi.</p>@endforelse</div>
@endif

@endsection
