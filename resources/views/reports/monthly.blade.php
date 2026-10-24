@extends('layouts.app')

@section('content')
@php
    $monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
@endphp

<div class="page-head">
    <div>
        <h1 class="page-title">Laporan Keuangan Bulanan</h1>
        <p class="page-sub">Ringkasan pemasukan, pengeluaran, dan subtotal berdasarkan group laporan.</p>
    </div>
    <form method="GET" class="period-filter compact-filter">
        <select name="month">
            @foreach($monthNames as $number => $name)
                <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="year">
            @for($y = now()->year + 1; $y >= now()->year - 7; $y--)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endfor
        </select>
        <button class="btn primary" type="submit">Tampilkan</button>
    </form>
</div>

<div class="stats-grid report-stats">
    <div class="card stat-card">
        <div class="stat-label">Total Pemasukan</div>
        <div class="stat">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div>
        <div class="stat-note">{{ $monthNames[$month] }} {{ $year }}</div>
        <div class="stat-icon green">↙</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Total Pengeluaran</div>
        <div class="stat">Rp {{ number_format($totalExpense, 0, ',', '.') }}</div>
        <div class="stat-note">{{ $monthNames[$month] }} {{ $year }}</div>
        <div class="stat-icon red">↗</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Surplus / (Defisit)</div>
        <div class="stat {{ $surplus < 0 ? 'text-danger' : 'text-positive' }}">Rp {{ number_format($surplus, 0, ',', '.') }}</div>
        <div class="stat-note">Pemasukan dikurangi pengeluaran</div>
        <div class="stat-icon blue">↗</div>
    </div>
</div>

<div class="card" style="margin:16px 0;padding:20px"><h3>Grafik Perbandingan Bulanan</h3>
<p class="page-sub">Klik batang Penerimaan atau Pengeluaran untuk membuka diagram dan seluruh rincian transaksi bulan ini.</p>
@php($maxVal=max(1,$totalIncome,$totalExpense))
@foreach([['label'=>'Penerimaan','kind'=>'income','amount'=>$totalIncome,'color'=>'#059669'],['label'=>'Pengeluaran','kind'=>'expense','amount'=>$totalExpense,'color'=>'#e47a55']] as $bar)
<a class="rsuve-detail-link" href="{{ route('reports.monthly.detail', ['month'=>$month,'year'=>$year,'kind'=>$bar['kind']]) }}" aria-label="Lihat rincian {{ $bar['label'] }}">
 <span class="rsuve-row"><strong>{{ $bar['label'] }} ↗</strong><strong>Rp {{number_format($bar['amount'],0,',','.')}}</strong></span>
 <span class="rsuve-track"><span class="rsuve-bar" style="display:block;width:{{min(100,100*$bar['amount']/$maxVal)}}%;background:{{$bar['color']}}"></span></span>
</a>
@endforeach</div>
<style>
  .rsuve-detail-link{display:block;color:inherit;text-decoration:none;padding:9px;border-radius:12px;transition:background .2s}.rsuve-detail-link:hover,.rsuve-detail-link:focus-visible{background:#eaf6ef;outline:2px solid #16a36b}.rsuve-detail-link .rsuve-row{display:flex;flex-wrap:wrap}
  .rsuve-month-chart{display:grid;grid-template-columns:repeat(auto-fit,minmax(255px,1fr));gap:16px;margin:18px 0}
  .rsuve-month-chart .card{min-width:0;padding:18px}
  .rsuve-track{display:block;width:100%;background:#edf4f0;height:14px;border-radius:20px;overflow:hidden;margin:7px 0 15px}
  .rsuve-bar{display:block;height:100%;min-height:14px;border-radius:20px;transition:width .2s ease}
  .rsuve-row{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px}
  .rsuve-claim-table{overflow-x:auto;max-width:100%}
  .rsuve-claim-table table{width:100%;border-collapse:collapse;min-width:590px}
  .rsuve-claim-table th,.rsuve-claim-table td{padding:12px 10px;border-bottom:1px solid #e6edeb;text-align:left;font-size:13px}
  .rsuve-claim-table th{background:#f3f8f6;color:#284f42}
  .rsuve-claim-table td:last-child,.rsuve-claim-table th:last-child{text-align:right}
  .rsuve-ins-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
  @media(max-width:850px){.report-grid{display:grid;grid-template-columns:minmax(0,1fr)}.rsuve-month-chart{grid-template-columns:minmax(0,1fr)}}
</style>
{{-- Grafik memakai data grup yang langsung tersedia dari ReportController. --}}
<div class="rsuve-month-chart">
 @foreach([['title'=>'Pemasukan menurut kelompok','items'=>$income,'color'=>'#009c70'],['title'=>'Pengeluaran menurut kelompok','items'=>$expense,'color'=>'#e47756']] as $chart)
 <div class="card">
  <h3>{{ $chart['title'] }}</h3>
  @forelse($chart['items'] as $v)
   <a class="rsuve-row rsuve-detail-link" href="{{ route('reports.monthly.detail', ['month'=>$month,'year'=>$year,'kind'=>$chart['color']==='#009c70' ? 'income' : 'expense','group'=>$v['code']]) }}">
    <span>{{ $v['name'] ?? '-' }} ↗</span>
    <strong>Rp {{ number_format((float) ($v['total'] ?? 0),0,',','.') }}</strong>
   </a>
   <div class="rsuve-track">
    <div class="rsuve-bar" style="width:{{ min(100, max(0, (float) ($v['total'] ?? 0)) * 100 / max(1, (float) $chart['items']->max('total'))) }}%;background:{{ $chart['color'] }}"></div>
   </div>
  @empty
   <p class="page-sub">Belum ada transaksi pada periode ini.</p>
  @endforelse
 </div>
 @endforeach
</div>
<div class="card" style="padding:18px;margin-bottom:18px">
 <h3>Rekap Klaim Asuransi per Penjamin</h3>
 <p class="page-sub">Klaim bulan {{ $monthNames[$month] }} {{ $year }} dari modul Klaim & Piutang Asuransi. Nilai klaim tidak ditambahkan kembali ke pendapatan transaksi.</p>
 <div class="rsuve-claim-table"><table><thead><tr><th>Perusahaan / Penjamin</th><th>Jenis pelayanan</th><th>Jumlah rekap</th><th>Nilai diajukan</th><th>Disetujui</th><th>Dibayar</th></tr></thead><tbody>
 @forelse($claimsByGuarantor as $claim)
  @foreach(['RAWAT_JALAN'=>'Rawat Jalan','RAWAT_INAP'=>'Rawat Inap'] as $key=>$label)
   <tr><td>{{ $claim['name'] }}</td><td>{{ $label }}</td><td>{{ $claim['services'][$key]['count'] }}</td><td>Rp {{number_format($claim['services'][$key]['submitted'],0,',','.')}}</td><td>Rp {{number_format($claim['services'][$key]['approved'],0,',','.')}}</td><td>Rp {{number_format($claim['services'][$key]['paid'],0,',','.')}}</td></tr>
  @endforeach
 @empty
  <tr><td colspan="6">Belum ada rekap klaim pada bulan ini. Data transaksi Penerimaan tetap tampil di bagian pemasukan.</td></tr>
 @endforelse
 </tbody></table></div>
</div>
<div class="report-grid">
    <section>
        <div class="report-section-title"><span class="report-dot income"></span> PEMASUKAN</div>
        @foreach($income as $group)
            <div class="card report-group-card">
                <div class="report-group-head">
                    <div>
                        <div class="group-eyebrow">Pemasukan</div>
                        <h3><a class="rsuve-detail-link" href="{{route('reports.monthly.detail',['month'=>$month,'year'=>$year,'kind'=>'income','group'=>$group['code']])}}">{{ $group['name'] }} ↗</a></h3>
                    </div>
                    <div class="group-total">Rp {{ number_format($group['total'], 0, ',', '.') }}</div>
                </div>
                <div class="table-responsive">
                    <table class="report-table compact">
                        <thead><tr><th>Uraian</th><th class="money">Nominal</th></tr></thead>
                        <tbody>
                            @forelse($group['details'] as $detail)
                                <tr><td>{{ $detail['label'] }}</td><td class="money">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="empty-cell">Belum ada transaksi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr><th>Subtotal {{ $group['name'] }}</th><th class="money">Rp {{ number_format($group['total'], 0, ',', '.') }}</th></tr></tfoot>
                    </table>
                </div>
            </div>
        @endforeach
    </section>

    <section>
        <div class="report-section-title"><span class="report-dot expense"></span> PENGELUARAN</div>
        @foreach($expense as $group)
            <div class="card report-group-card">
                <div class="report-group-head">
                    <div>
                        <div class="group-eyebrow expense-text">Pengeluaran</div>
                        <h3><a class="rsuve-detail-link" href="{{route('reports.monthly.detail',['month'=>$month,'year'=>$year,'kind'=>'expense','group'=>$group['code']])}}">{{ $group['name'] }} ↗</a></h3>
                    </div>
                    <div class="group-total">Rp {{ number_format($group['total'], 0, ',', '.') }}</div>
                </div>
                <div class="table-responsive">
                    <table class="report-table compact">
                        <thead><tr><th>Uraian</th><th class="money">Nominal</th></tr></thead>
                        <tbody>
                            @forelse($group['details'] as $detail)
                                <tr><td>{{ $detail['label'] }}</td><td class="money">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="empty-cell">Belum ada transaksi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr><th>Subtotal {{ $group['name'] }}</th><th class="money">Rp {{ number_format($group['total'], 0, ',', '.') }}</th></tr></tfoot>
                    </table>
                </div>
            </div>
        @endforeach
    </section>
</div>

<div class="card financial-summary">
    <div><span>Total Pemasukan</span><strong>Rp {{ number_format($totalIncome, 0, ',', '.') }}</strong></div>
    <div><span>Total Pengeluaran</span><strong>Rp {{ number_format($totalExpense, 0, ',', '.') }}</strong></div>
    <div class="grand-result"><span>Surplus / (Defisit)</span><strong class="{{ $surplus < 0 ? 'text-danger' : 'text-positive' }}">Rp {{ number_format($surplus, 0, ',', '.') }}</strong></div>
</div>
@endsection
