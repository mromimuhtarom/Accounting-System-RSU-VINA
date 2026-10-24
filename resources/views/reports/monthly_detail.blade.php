@extends('layouts.app')
@section('content')
@php
 $monthNames=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
 $largest=max(1,(float)$chartRows->max('amount'));
@endphp
<style>
 .rsd-wrap{max-width:100%;min-width:0}.rsd-heading{display:flex;justify-content:space-between;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:20px}
 .rsd-back{display:inline-flex;padding:11px 14px;border:1px solid #d8e6e0;border-radius:11px;text-decoration:none;color:#174537;background:white;font-weight:700}
 .rsd-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}.rsd-card{background:white;border:1px solid #dce9e3;border-radius:17px;padding:20px;min-width:0;box-shadow:0 7px 21px rgba(9,60,46,.04)}
 .rsd-kpi{font-size:clamp(20px,2.4vw,31px);font-weight:800;color:#133347;overflow-wrap:anywhere}.rsd-sub{color:#647b88;font-size:13px;margin-top:5px}
 .rsd-bar-link{display:block;text-decoration:none;color:inherit}.rsd-row{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px}.rsd-track{background:#eaf1ee;height:15px;border-radius:50px;margin:8px 0 18px;overflow:hidden}.rsd-fill{height:100%;border-radius:50px}.rsd-bar-label{font-weight:700}
 .rsd-table-wrap{overflow-x:auto;width:100%;-webkit-overflow-scrolling:touch}.rsd-table{min-width:850px;border-collapse:collapse;width:100%;font-size:13px}.rsd-table th{text-align:left;background:#f0f6f3;padding:13px;color:#36586a;white-space:nowrap}.rsd-table td{padding:13px;border-bottom:1px solid #e7efea;vertical-align:top}.rsd-table td.money{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.rsd-foot{font-weight:800;background:#eef8f3}.rsd-footer{margin-top:16px}.rsd-pages{overflow-x:auto;margin-top:15px}
 @media(max-width:860px){.rsd-summary{grid-template-columns:1fr 1fr}}@media(max-width:540px){.rsd-summary{grid-template-columns:1fr}.rsd-card{padding:15px}.rsd-heading h1{font-size:23px}}
</style>
<div class="rsd-wrap">
 <div class="rsd-heading"><div><h1 class="page-title">Rincian {{ $title }}</h1><p class="page-sub">{{ $monthNames[$month] }} {{ $year }} · {{ $isIncome ? 'Pemasukan' : 'Pengeluaran' }} · Data transaksi aktif</p></div><a class="rsd-back" href="{{route('reports.monthly',['month'=>$month,'year'=>$year])}}">← Kembali ke Laporan Bulanan</a></div>
 <div class="rsd-summary"><div class="rsd-card"><div class="rsd-sub">Total {{ $title }}</div><div class="rsd-kpi">Rp {{number_format($total,0,',','.')}}</div></div><div class="rsd-card"><div class="rsd-sub">Jumlah transaksi</div><div class="rsd-kpi">{{number_format($rows->count(),0,',','.')}}</div></div><div class="rsd-card"><div class="rsd-sub">Periode</div><div class="rsd-kpi">{{ $monthNames[$month] }}</div><div class="rsd-sub">{{ $year }}</div></div></div>
 <div class="rsd-card" style="margin-bottom:18px"><h3>Diagram Batang — {{ $title }}</h3><p class="rsd-sub">Menurut {{ $selectedGroup ? 'rincian akun / jenis pelayanan / kategori' : 'kelompok laporan' }}. Setiap nominal berasal dari transaksi aktif bulan terpilih.</p>
 @forelse($chartRows as $bar)
  <div class="rsd-row"><span class="rsd-bar-label">{{ $bar['label'] }}</span><strong>Rp {{number_format($bar['amount'],0,',','.')}} · {{ $bar['count'] }} transaksi</strong></div>
  <div class="rsd-track"><div class="rsd-fill" style="width:{{min(100,100*$bar['amount']/$largest)}}%;background:{{ $isIncome ? '#039d71' : '#e87955' }}"></div></div>
 @empty <p>Belum ada transaksi untuk pilihan ini.</p> @endforelse
 </div>
 <div class="rsd-card"><h3>Daftar Transaksi {{ $title }}</h3><p class="rsd-sub">Semua hasil dihitung dalam total; tabel ditampilkan 30 transaksi per halaman.</p><div class="rsd-table-wrap"><table class="rsd-table"><thead><tr><th>Tanggal</th><th>No. Transaksi</th><th>Kategori / Akun</th><th>Keterangan</th><th>Penjamin / Metode</th><th style="text-align:right">Nominal</th></tr></thead><tbody>
 @forelse($paged as $item)
 <tr><td>{{ $isIncome ? optional($item->transaction_date)->format('d/m/Y') : optional($item->expense_date)->format('d/m/Y') }}</td>
 <td>{{ $isIncome ? $item->transaction_no : $item->expense_no }}</td>
 <td>{{ $isIncome ? ($item->account?->name ?? 'Tanpa akun') : ($item->category?->name ?? 'Tanpa kategori') }}</td>
 <td>{{ $isIncome ? ($item->notes ?: ($item->patient_name ?: '-')) : $item->description }}</td>
 <td>{{ $isIncome ? ($item->guarantor?->name ?? 'Belum ada penjamin') : $item->payment_method }}</td>
 <td class="money">Rp {{number_format((float)$item->amount,0,',','.')}}</td></tr>
 @empty <tr><td colspan="6" style="text-align:center">Tidak ada transaksi untuk periode dan kelompok ini.</td></tr> @endforelse
 </tbody><tfoot><tr class="rsd-foot"><td colspan="5">TOTAL KESELURUHAN (SEMUA HALAMAN)</td><td class="money">Rp {{number_format($total,0,',','.')}}</td></tr></tfoot></table></div>
 <div class="rsd-pages">{{ $paged->links() }}</div>
 <p class="rsd-sub">Catatan: Nilai Penerimaan adalah transaksi tercatat; klaim penjamin yang belum cair tidak otomatis berarti uang telah diterima.</p></div>
</div>
@endsection
