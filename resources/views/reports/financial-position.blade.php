@extends('layouts.app')
@section('content')
@php($amount=\App\Services\LedgerAmounts::class)
<div id="report-pdf-content">
<div class="page-head"><div><h1 class="page-title">Neraca · Aset dan Pasiva</h1><p class="page-sub">Posisi keuangan per tanggal, dihitung dari saldo akun. Pasiva = kewajiban + ekuitas.</p></div><form method="GET" class="period-filter"><label for="position-date">Per tanggal</label><input id="position-date" type="date" name="as_of" value="{{ $position['as_of'] }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required><button class="btn primary">Tampilkan</button>@include('reports.partials.financial-pdf-export',['filename'=>'Neraca','date'=>$position['as_of']])</form></div>
<div class="card actions" data-pdf-exclude><a class="btn" href="{{ route('opening-balances.index') }}">Input / Periksa Saldo Awal</a><a class="btn" href="{{ route('reports.financial-trial-balance',['as_of'=>$position['as_of']]) }}">Periksa Neraca Saldo</a></div>
@include('reports.partials.financial-position-status')
<div class="asset-report-stat-grid"><div class="card"><h3>Total Aset</h3><strong>Rp {{ $amount::display($position['assets']) }}</strong></div><div class="card"><h3>Kewajiban</h3><strong>Rp {{ $amount::display($position['liabilities']) }}</strong></div><div class="card"><h3>Ekuitas termasuk hasil belum ditutup</h3><strong>Rp {{ $amount::display($position['equity']) }}</strong></div><div class="card"><h3>Selisih Aset − Pasiva</h3><strong>Rp {{ $amount::display($position['difference']) }}</strong></div></div>
<div class="asset-chart-grid" data-pdf-flatten>
@foreach(['ASSET'=>'Aset / Aktiva','LIABILITY'=>'Kewajiban','EQUITY'=>'Ekuitas / Modal'] as $type=>$label)
<div class="card"><h3>{{ $label }}</h3><div class="table-wrap"><table><thead><tr><th>Akun</th><th>Saldo (Rp)</th></tr></thead><tbody>
@forelse($position['groups'][$type] as $row)<tr><td><a href="{{ route('reports.financial-ledger',['account'=>$row['id'],'as_of'=>$position['as_of']]) }}">{{ $row['code'] }} · {{ $row['name'] }}</a>@if(!$row['active']) <small>(akun nonaktif, saldo historis tetap ditampilkan)</small>@endif</td><td class="money">{{ $amount::display($row['balance']) }}</td></tr>@empty<tr><td colspan="2">Belum ada saldo pada kelompok ini.</td></tr>@endforelse
@if($type==='EQUITY')<tr><td>Hasil usaha belum ditutup sampai tanggal laporan</td><td class="money">{{ $amount::display($position['unclosed_result']) }}</td></tr>@endif
</tbody><tfoot><tr><th>Total {{ $label }}</th><th class="money">{{ $amount::display($position[$type==='ASSET'?'assets':($type==='LIABILITY'?'liabilities':'equity')]) }}</th></tr></tfoot></table></div>
@if($type==='ASSET')<p>Akun kontra-aset, seperti akumulasi penyusutan bersaldo kredit, mengurangi total aset.</p>@endif
@if($type==='EQUITY')<p>Hasil usaha belum ditutup berasal dari saldo akun pendapatan dikurangi beban pada buku ini, termasuk periode sebelumnya yang belum ditutup. Angka ini dihitung dari jurnal, bukan dibuat untuk menutup selisih.</p>@endif
</div>@endforeach
</div><div class="card"><h3>Aset = Kewajiban + Ekuitas</h3>@if($position['difference']===0)<p>Rp {{ $amount::display($position['assets']) }} = Rp {{ $amount::display($position['liabilities']) }} + Rp {{ $amount::display($position['equity']) }}</p>@else<p>Aset Rp {{ $amount::display($position['assets']) }} belum sama dengan total kewajiban dan ekuitas Rp {{ $amount::display($position['liabilities_equity']) }}. Selisih Rp {{ $amount::display($position['difference']) }} perlu diperiksa melalui neraca saldo dan jurnal sumber.</p>@endif</div>
</div>
@endsection
