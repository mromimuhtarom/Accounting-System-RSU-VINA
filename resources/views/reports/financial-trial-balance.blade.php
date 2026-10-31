@extends('layouts.app')
@section('content')
@php($amount=\App\Services\LedgerAmounts::class)
<div id="report-pdf-content"><div class="page-head"><div><h1 class="page-title">Neraca Saldo</h1><p class="page-sub">Periksa saldo debit/kredit setiap akun dan telusuri jurnal sumbernya.</p></div><form method="GET" class="period-filter"><label for="trial-date">Per tanggal</label><input id="trial-date" type="date" name="as_of" value="{{ $position['as_of'] }}" required><button class="btn primary">Tampilkan</button>@include('reports.partials.financial-pdf-export',['filename'=>'Neraca_Saldo','date'=>$position['as_of']])</form></div>
<div class="card actions" data-pdf-exclude><a class="btn" href="{{ route('reports.financial-position',['as_of'=>$position['as_of']]) }}">Kembali ke Neraca</a></div>
@include('reports.partials.financial-position-status')
<div class="card"><div class="table-wrap"><table><thead><tr><th>Akun</th><th>Jenis</th><th>Saldo Debit</th><th>Saldo Kredit</th></tr></thead><tbody>
@forelse($position['rows'] as $row)<tr><td><a href="{{ route('reports.financial-ledger',['account'=>$row['id'],'as_of'=>$position['as_of']]) }}">{{ $row['code'] }} · {{ $row['name'] }}</a></td><td>{{ ['ASSET'=>'Aset','LIABILITY'=>'Kewajiban','EQUITY'=>'Ekuitas','REVENUE'=>'Pendapatan','EXPENSE'=>'Beban'][$row['type']] }}</td><td class="money">{{ $amount::display($row['trial_debit']) }}</td><td class="money">{{ $amount::display($row['trial_credit']) }}</td></tr>@empty<tr><td colspan="4">Belum ada saldo dari jurnal terposting.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="2">Total saldo</th><th class="money">{{ $amount::display($position['trial_debit']) }}</th><th class="money">{{ $amount::display($position['trial_credit']) }}</th></tr></tfoot></table></div></div>
</div>
@endsection
