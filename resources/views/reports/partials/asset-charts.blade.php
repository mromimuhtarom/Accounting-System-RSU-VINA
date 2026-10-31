@if(!empty($assetReport))
<section class="asset-report-section" data-pdf-flatten aria-label="Grafik inventaris dan aktiva">
<div class="card asset-report-card">
    <h3>Grafik Inventaris &amp; Aktiva — {{ $assetReport['period'] }}</h3>
    <p class="asset-report-caption">Jumlah inventaris dan unit/lokasi menunjukkan posisi master saat ini. Nilai accounting dihitung dari jurnal terposting sampai {{ \Carbon\Carbon::parse($assetReport['as_of'])->format('d/m/Y') }}; harga sementara tidak ditambahkan ke nilai resmi.</p>
    <div class="asset-report-stat-grid">
        <div class="asset-report-stat"><span>Catatan inventaris saat ini</span><strong>{{ number_format($assetReport['inventory_count'],0,',','.') }}</strong></div>
        <div class="asset-report-stat"><span>Parameter fasilitas terpisah</span><strong>{{ $assetReport['facility_count'] }}</strong></div>
        <div class="asset-report-stat"><span>Aktiva resmi akhir periode</span><strong>{{ $assetReport['position']['fixed_assets'] }}</strong></div>
        <div class="asset-report-stat"><span>Inventaris belum dikapitalisasi saat ini</span><strong>{{ $assetReport['uncapitalized'] }}</strong></div>
    </div>
</div>
<div class="asset-chart-grid" data-pdf-flatten>
    <div class="card asset-report-card">
        <h3>Inventaris menurut jenis</h3><p class="asset-report-caption">Skala berdasarkan jumlah catatan. Jumlah perangkat atau instalasi sumber ditampilkan terpisah; parameter fasilitas tidak dihitung sebagai inventaris unit.</p>
        @php($typeMax=max(1,max(array_column($assetReport['types'],'records'))))
        @foreach($assetReport['types'] as $item)
        <div class="asset-chart-label"><a href="{{ route('assets.index',['type'=>$item['type']]) }}">{{ $item['label'] }}</a><span><strong>{{ $item['records'] }}</strong> catatan <small>· {{ $item['units'] }} {{ $item['type']==='SOFTWARE'?'instalasi':'unit sumber' }}</small></span></div>
        <div class="asset-chart-track" role="img" aria-label="{{ $item['label'] }}: {{ $item['records'] }} catatan"><div class="asset-chart-bar" style="width:{{ 100*$item['records']/$typeMax }}%"></div></div>
        @endforeach
    </div>
    <div class="card asset-report-card">
        <h3>Nilai aktiva resmi akhir periode</h3><p class="asset-report-caption">Per {{ \Carbon\Carbon::parse($assetReport['as_of'])->format('d/m/Y') }}. Saldo aset yang sudah dilepas pada periode tersebut dikeluarkan.</p>
        @php($valueMax=max(1,$assetReport['position']['cost'],$assetReport['position']['accumulated'],$assetReport['position']['book_value']))
        @foreach(['cost'=>['Harga perolehan tervalidasi',''],'accumulated'=>['Akumulasi penyusutan','secondary'],'book_value'=>['Nilai buku','book']] as $key=>[$label,$color])
        <div class="asset-chart-label"><span>{{ $label }}</span><strong>Rp {{ number_format($assetReport['position'][$key],2,',','.') }}</strong></div>
        <div class="asset-chart-track" role="img" aria-label="{{ $label }}: Rp {{ number_format($assetReport['position'][$key],2,',','.') }}"><div class="asset-chart-bar {{ $color }}" style="width:{{ max(0,100*$assetReport['position'][$key]/$valueMax) }}%"></div></div>
        @endforeach
        @if(!$assetReport['position']['fixed_assets'])<p class="asset-report-note">Belum ada aktiva dengan kapitalisasi terposting sampai akhir periode ini. Inventaris yang sudah tercatat tetap ditampilkan pada grafik jenis.</p>@endif
        <p class="asset-report-caption">Kapitalisasi dalam periode: Rp {{ number_format($assetReport['flow']['additions'],2,',','.') }}<br>Beban penyusutan dalam periode: Rp {{ number_format($assetReport['flow']['depreciation'],2,',','.') }}</p>
    </div>
    <div class="card asset-report-card">
        <h3>Inventaris per unit / lokasi saat ini</h3><p class="asset-report-caption">Delapan lokasi dengan catatan terbanyak. Catatan agregat tidak dipecah menjadi aset individual.</p>
        @php($unitMax=max(1,collect($assetReport['units'])->max('records') ?? 0))
        @forelse($assetReport['units'] as $unit)
        <div class="asset-chart-label"><span>{{ $unit['location'] ?: 'Belum diketahui' }}</span><strong>{{ $unit['records'] }} catatan</strong></div>
        <div class="asset-chart-track"><div class="asset-chart-bar book" style="width:{{ 100*$unit['records']/$unitMax }}%"></div></div>
        @empty<p>Belum ada inventaris aktif.</p>@endforelse
    </div>
    <div class="card asset-report-card">
        <h3>Kapitalisasi &amp; penyusutan per bulan — {{ substr($assetReport['period'],0,4) }}</h3>
        <div class="asset-report-legend"><span><i class="asset-report-key" style="background:#168162"></i>Kapitalisasi terposting</span><span><i class="asset-report-key" style="background:#d78938"></i>Beban penyusutan terposting</span></div>
        @php($trendMax=max(1,collect($assetReport['trend'])->max('additions') ?? 0,collect($assetReport['trend'])->max('depreciation') ?? 0))
        @php($assetMonths=[1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'])
        @foreach($assetReport['trend'] as $m=>$values)
        <div class="asset-trend-row"><div class="asset-chart-label"><strong>{{ $assetMonths[$m] }}</strong><span>Rp {{ number_format($values['additions'],2,',','.') }} / Rp {{ number_format($values['depreciation'],2,',','.') }}</span></div>
        <div class="asset-chart-track"><div class="asset-chart-bar" style="width:{{ 100*$values['additions']/$trendMax }}%"></div></div><div class="asset-chart-track"><div class="asset-chart-bar secondary" style="width:{{ 100*$values['depreciation']/$trendMax }}%"></div></div></div>
        @endforeach
    </div>
</div>
</section>
@endif
