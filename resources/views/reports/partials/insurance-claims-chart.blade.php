@php
    $claimChartSeries = [
        'submitted' => ['label' => 'Diajukan', 'color' => '#245da8'],
        'approved' => ['label' => 'Disetujui', 'color' => '#916000'],
        'paid' => ['label' => 'Dibayar', 'color' => '#14734e'],
    ];
    $claimChartRows = collect($claimsByGuarantor)->map(function ($claim) {
        $services = collect($claim['services']);
        return [
            'name' => $claim['name'],
            'submitted' => $services->sum('submitted'),
            'approved' => $services->sum('approved'),
            'paid' => $services->sum('paid'),
        ];
    });
    $claimChartMax = max(1, $claimChartRows->flatMap(fn ($row) => [$row['submitted'], $row['approved'], $row['paid']])->max() ?? 0);
@endphp

<style>
    .insurance-claims-chart{margin:22px 0 26px;padding:20px;border:1px solid #d5e3dd;border-radius:14px;background:#fbfdfc;color:#203c39}
    .insurance-claims-chart h4{margin:0 0 6px;font-size:18px}
    .insurance-claims-chart .claim-chart-caption{margin:0 0 20px;color:#52635e;font-size:15px;line-height:1.6}
    .insurance-claims-chart .claim-chart-group{padding:18px 0;border-top:1px solid #dce7e1}
    .insurance-claims-chart .claim-chart-group:last-child{padding-bottom:0}
    .insurance-claims-chart .claim-chart-name{margin:0 0 14px;font-size:17px;font-weight:750;overflow-wrap:anywhere}
    .insurance-claims-chart .claim-chart-row{display:grid;grid-template-columns:100px minmax(0,1fr) minmax(130px,max-content);align-items:center;gap:16px;margin:12px 0;font-size:16px}
    .insurance-claims-chart .claim-chart-label{display:flex;align-items:center;gap:8px}
    .insurance-claims-chart .claim-chart-key{width:12px;height:12px;border-radius:3px;flex-shrink:0}
    .insurance-claims-chart .claim-chart-track{height:20px;border-radius:6px;background:#e9efec;overflow:hidden}
    .insurance-claims-chart .claim-chart-bar{height:100%;border-radius:6px}
    .insurance-claims-chart .claim-chart-value{text-align:right;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
    .insurance-claims-chart .claim-chart-empty{margin:0;font-size:16px;color:#52635e}
    @media(max-width:650px){
        .insurance-claims-chart{padding:16px 12px}
        .insurance-claims-chart .claim-chart-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px;margin:16px 0}
        .insurance-claims-chart .claim-chart-track{grid-row:2;grid-column:1/-1}
        .insurance-claims-chart .claim-chart-value{grid-row:1;grid-column:2}
    }
</style>

<section class="insurance-claims-chart" aria-labelledby="insurance-claims-chart-title">
    <h4 id="insurance-claims-chart-title">Grafik perbandingan klaim per penjamin</h4>
    <p class="claim-chart-caption">Total Rawat Jalan dan Rawat Inap untuk setiap penjamin. Semua batang menggunakan skala yang sama agar nominal mudah dibandingkan. Rincian per pelayanan tersedia pada tabel di bawah.</p>
    @forelse($claimChartRows as $claimChartRow)
        <div class="claim-chart-group">
            <h5 class="claim-chart-name">{{ $claimChartRow['name'] }}</h5>
            @foreach($claimChartSeries as $claimChartKey => $claimChartSeriesItem)
                <div class="claim-chart-row">
                    <span class="claim-chart-label"><span class="claim-chart-key" style="background:{{ $claimChartSeriesItem['color'] }}" aria-hidden="true"></span>{{ $claimChartSeriesItem['label'] }}</span>
                    <div class="claim-chart-track" aria-hidden="true">
                        <div class="claim-chart-bar" style="width:{{ min(100, max(0, $claimChartRow[$claimChartKey]) * 100 / $claimChartMax) }}%;background:{{ $claimChartSeriesItem['color'] }}"></div>
                    </div>
                    <strong class="claim-chart-value">Rp {{ number_format($claimChartRow[$claimChartKey], 0, ',', '.') }}</strong>
                </div>
            @endforeach
        </div>
    @empty
        <p class="claim-chart-empty">Grafik akan tampil setelah ada rekap klaim pada bulan yang dipilih.</p>
    @endforelse
</section>
