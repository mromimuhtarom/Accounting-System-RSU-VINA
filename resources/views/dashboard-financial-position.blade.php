@if($financialPosition ?? null)
@php($position=$financialPosition)
@php($ledgerAmount=\App\Services\LedgerAmounts::class)
<section class="card" aria-labelledby="dashboard-position-title">
<div class="page-head"><div><h2 id="dashboard-position-title" class="section-title">Ringkasan Neraca</h2><p class="page-sub">Posisi per {{ \Carbon\Carbon::parse($position['as_of'])->format('d/m/Y') }} dari jurnal terposting, termasuk saldo periode sebelumnya.</p></div><a class="btn" href="{{ route('reports.financial-position',['as_of'=>$position['as_of']]) }}">Lihat Neraca</a></div>
@if(!$position['journal_count'])
<p><strong>Pembukuan neraca belum tersedia.</strong> Belum ada jurnal terposting sampai tanggal ini. Data penerimaan, tracking klaim, dan inventaris tidak otomatis menjadi saldo neraca.</p>
@else
<div class="stats-grid dashboard-position-grid">
@foreach(['assets'=>'Aset','liabilities'=>'Kewajiban','equity'=>'Ekuitas'] as $key=>$label)
<div><div class="stat-label">{{ $label }}</div><strong class="dashboard-position-value">Rp {{ $ledgerAmount::display($position[$key]) }}</strong></div>
@endforeach
</div>
<p>{{ $position['journal_count'] }} jurnal. @if($position['difference']!==0)<strong>Selisih aset dan pasiva Rp {{ $ledgerAmount::display($position['difference']) }} perlu diperiksa.</strong>@else Seimbang secara jurnal; tetap perlu rekonsiliasi dokumen.@endif</p>
@endif
@if(!$position['opening'] || $position['opening']->status!=='POSTED')<p><strong>Saldo awal belum terposting.</strong> Ringkasan ini belum menunjukkan posisi keuangan lengkap.</p>@elseif($position['before_opening'])<p>Tanggal ringkasan mendahului saldo awal. Posisi lengkap periode ini belum tersedia.</p>@endif
<a class="btn" href="{{ route('accounting.workspace',['as_of'=>$position['as_of']]) }}">Ruang Kerja Accounting</a>
</section>
@endif
