@extends('layouts.app')

@section('content')
@php
    $monthShort = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
@endphp

<style>
  .rsuve-year-root{min-width:0;max-width:100%;overflow:hidden}
  .rsuve-year-root .yearly-card{max-width:100%;min-width:0;overflow:hidden}
  .rsuve-year-root .yearly-table-wrap{display:block;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}
  .rsuve-year-root .yearly-table{min-width:1360px;width:max-content;border-collapse:collapse}
  .rsuve-year-root .yearly-table th:first-child,.rsuve-year-root .yearly-table td:first-child{position:sticky;left:0;min-width:210px;max-width:250px;background:#fff;z-index:1;white-space:normal}
  .rsuve-year-root .yearly-table th:first-child{z-index:3;background:#f3f7f5}
  .rsuve-year-root .yearly-table .group-row td:first-child{background:#e8f6ef}
  .rsuve-year-root .yearly-table .subtotal-row td:first-child{background:#eef5f2}
  .rsuve-year-root .yearly-table td.money,.rsuve-year-root .yearly-table th.money{white-space:nowrap;min-width:82px}
  .rsuve-year-root .page-head{flex-wrap:wrap;gap:12px}
  .rsuve-year-root .stat{overflow-wrap:anywhere;font-size:clamp(19px,2vw,30px)}
  .rsuve-year-root .stats-grid{grid-template-columns:repeat(auto-fit,minmax(225px,1fr))}
  .rsuve-year-chart{width:100%;min-width:0;overflow:hidden}
  .rsuve-year-scroll{overflow-x:auto;width:100%;padding-bottom:8px;-webkit-overflow-scrolling:touch}
  .rsuve-year-columns{display:grid;grid-template-columns:repeat(12,minmax(72px,1fr));min-width:910px;gap:8px;align-items:end}
  .rsuve-year-legend{display:flex;flex-wrap:wrap;gap:18px;color:#526679;font-size:13px}
  @media(max-width:680px){.rsuve-year-root .yearly-table th:first-child,.rsuve-year-root .yearly-table td:first-child{min-width:135px;max-width:170px}.rsuve-year-root .page-head,.rsuve-year-root .period-filter{width:100%}}
</style>
<div class="rsuve-year-root" id="report-pdf-content">
<div class="page-head">
    <div>
        <h1 class="page-title">Laporan Keuangan Tahunan</h1>
        <p class="page-sub">Rekap Januari sampai Desember dengan group dan subtotal seperti laporan Excel.</p>
    </div>
    <form method="GET" class="period-filter compact-filter">
        <select name="year">
            @for($y = now()->year + 1; $y >= now()->year - 7; $y--)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endfor
        </select>
        <button class="btn primary" type="submit">Tampilkan</button>
        @include('reports.partials.pdf-export', ['pdfKind' => 'Tahunan', 'pdfPeriod' => (string) $year])
    </form>
</div>

<div class="stats-grid report-stats">
    <div class="card stat-card"><div class="stat-label">Total Pemasukan {{ $year }}</div><div class="stat">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div><div class="stat-icon green">↙</div></div>
    <div class="card stat-card"><div class="stat-label">Total Pengeluaran {{ $year }}</div><div class="stat">Rp {{ number_format($totalExpense, 0, ',', '.') }}</div><div class="stat-icon red">↗</div></div>
    <div class="card stat-card"><div class="stat-label">Surplus / (Defisit)</div><div class="stat {{ $surplus < 0 ? 'text-danger' : 'text-positive' }}">Rp {{ number_format($surplus, 0, ',', '.') }}</div><div class="stat-icon blue">↗</div></div>
</div>

<div class="card rsuve-year-chart" style="margin:18px 0;padding:18px">
<h3>Grafik Pemasukan dan Pengeluaran Bulanan — {{ $year }}</h3>
<div class="rsuve-year-legend"><span>🟢 Pemasukan tercatat</span><span>🟠 Pengeluaran</span></div>
<div class="rsuve-year-scroll"><div class="rsuve-year-columns">
@php($scale=max(1,(float)max($incomeMonths),(float)max($expenseMonths)))
@for($m=1;$m<=12;$m++)
<div style="text-align:center;min-width:0">
 <div style="height:164px;display:flex;align-items:end;justify-content:center;gap:6px">
 <div title="Pemasukan Rp {{number_format($incomeMonths[$m]??0,0,',','.')}}" style="width:28px;height:{{ max(2, 155*($incomeMonths[$m]??0)/$scale) }}px;background:#009c70;border-radius:5px 5px 0 0"></div>
 <div title="Pengeluaran Rp {{number_format($expenseMonths[$m]??0,0,',','.')}}" style="width:28px;height:{{ max(2, 155*($expenseMonths[$m]??0)/$scale) }}px;background:#e47a55;border-radius:5px 5px 0 0"></div>
 </div><div style="padding-top:7px;font-size:12px">{{ $monthShort[$m] }}</div>
</div>
@endfor
</div></div>
</div>
<div class="card yearly-card">
    <div class="report-section-title"><span class="report-dot income"></span> PEMASUKAN TAHUN {{ $year }}</div>
    <div class="table-responsive yearly-table-wrap">
        <table class="yearly-table">
            <thead>
                <tr><th>Uraian</th>@foreach($monthShort as $name)<th class="money">{{ $name }}</th>@endforeach<th class="money total-col">Total</th></tr>
            </thead>
            <tbody>
                @foreach($income as $group)
                    <tr class="group-row"><td>{{ $group['name'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($group['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($group['total'], 0, ',', '.') }}</td></tr>
                    @foreach($group['details'] as $detail)
                        <tr><td class="detail-label">{{ $detail['label'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($detail['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($detail['total'], 0, ',', '.') }}</td></tr>
                    @endforeach
                    <tr class="subtotal-row"><td>Subtotal {{ $group['name'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($group['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($group['total'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="grand-row"><td>TOTAL PEMASUKAN</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($incomeMonths[$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($totalIncome, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card yearly-card">
    <div class="report-section-title"><span class="report-dot expense"></span> PENGELUARAN TAHUN {{ $year }}</div>
    <div class="table-responsive yearly-table-wrap">
        <table class="yearly-table">
            <thead>
                <tr><th>Uraian</th>@foreach($monthShort as $name)<th class="money">{{ $name }}</th>@endforeach<th class="money total-col">Total</th></tr>
            </thead>
            <tbody>
                @foreach($expense as $group)
                    <tr class="group-row expense-group"><td>{{ $group['name'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($group['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($group['total'], 0, ',', '.') }}</td></tr>
                    @foreach($group['details'] as $detail)
                        <tr><td class="detail-label">{{ $detail['label'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($detail['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($detail['total'], 0, ',', '.') }}</td></tr>
                    @endforeach
                    <tr class="subtotal-row"><td>Subtotal {{ $group['name'] }}</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($group['months'][$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($group['total'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="grand-row expense-grand"><td>TOTAL PENGELUARAN</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($expenseMonths[$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($totalExpense, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card yearly-card result-card">
    <div class="report-section-title">RINGKASAN HASIL {{ $year }}</div>
    <div class="table-responsive yearly-table-wrap">
        <table class="yearly-table summary-table">
            <thead><tr><th>Uraian</th>@foreach($monthShort as $name)<th class="money">{{ $name }}</th>@endforeach<th class="money total-col">Total</th></tr></thead>
            <tbody>
                <tr><td>Total Pemasukan</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($incomeMonths[$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($totalIncome, 0, ',', '.') }}</td></tr>
                <tr><td>Total Pengeluaran</td>@foreach(range(1,12) as $m)<td class="money">{{ number_format($expenseMonths[$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col">{{ number_format($totalExpense, 0, ',', '.') }}</td></tr>
                <tr class="grand-result-row"><td>SURPLUS / (DEFISIT)</td>@foreach(range(1,12) as $m)<td class="money {{ ($surplusMonths[$m] ?? 0) < 0 ? 'text-danger' : 'text-positive' }}">{{ number_format($surplusMonths[$m] ?? 0, 0, ',', '.') }}</td>@endforeach<td class="money total-col {{ $surplus < 0 ? 'text-danger' : 'text-positive' }}">{{ number_format($surplus, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

@include('reports.partials.referral-summary')
</div>
@endsection
