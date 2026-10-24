@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Piutang & Klaim BPJS</h1><p class="page-sub">Pengajuan, verifikasi dan pelunasan terpisah dari penerimaan kas</p></div>@if(auth()->user()->hasRole('CASEMIX', 'ACCOUNTING'))<a class="btn primary" href="{{ route('accounting.bpjs.create') }}">+ Klaim Baru</a>@endif</div>
<div class="stats-grid">
<div class="card stat-card"><div class="stat-label">Total Klaim</div><div class="stat">{{ number_format($totals->jumlah,0,',','.') }}</div></div>
<div class="card stat-card"><div class="stat-label">Diajukan</div><div class="stat">Rp {{ number_format($totals->diajukan,0,',','.') }}</div></div>
<div class="card stat-card"><div class="stat-label">Disetujui</div><div class="stat">Rp {{ number_format($totals->disetujui,0,',','.') }}</div></div>
<div class="card stat-card"><div class="stat-label">Sudah Dicairkan</div><div class="stat">Rp {{ number_format($paid,0,',','.') }}</div></div>
</div>
<div class="card"><h3 class="section-title">Daftar Klaim</h3>
<form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px"><input name="search" placeholder="Cari SEP, pasien, batch" value="{{ request('search') }}"><select name="status"><option value="">Semua status</option>@foreach(['DRAFT','DIAJUKAN','VERIFIKASI','PENDING','DISPUTE','DISETUJUI_SEBAGIAN','DISETUJUI','DITOLAK','SELESAI'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><button class="btn primary">Cari</button></form>
<div class="table-wrap"><table><thead><tr><th>SEP</th><th>Pelayanan</th><th>Pasien</th><th>Diajukan</th><th>Disetujui</th><th>Dicairkan</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($claims as $c)<tr><td>{{ $c->sep_no }}</td><td>{{ $c->service_date->format('d M Y') }}<div class="muted">{{ $c->service_type }}</div></td><td>{{ $c->patient_name }}</td><td class="money">Rp {{ number_format($c->claimed_amount,0,',','.') }}</td><td class="money">Rp {{ number_format($c->approved_amount,0,',','.') }}</td><td class="money">Rp {{ number_format($c->paid_amount??0,0,',','.') }}</td><td>{{ $c->status }}</td><td><a href="{{ route('accounting.bpjs.show',$c) }}">Detail</a></td></tr>@empty<tr><td colspan="8">Belum ada klaim BPJS.</td></tr>@endforelse
</tbody></table></div>{{ $claims->links() }}</div>
@endsection
