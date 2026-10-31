<div class="card" id="asset-monthly-history">
<h3>Cek kapitalisasi dan penyusutan per bulan</h3>
<form method="GET" action="{{ route('assets.show',$asset) }}#asset-monthly-history" class="actions"><div class="field"><label for="asset-report-month">Bulan yang diperiksa</label><input id="asset-report-month" type="month" name="month" value="{{ $monthlyHistory['month'] }}" required></div><button class="btn">Tampilkan bulan</button></form>
<p>Nilai berikut khusus aktiva AST-{{ $asset->id }}, berdasarkan jurnal terposting. Mengedit harga sementara pada master tidak membuat transaksi bulanan.</p>
<div class="table-wrap"><table><tbody>
@foreach(['Nilai buku awal bulan'=>$monthlyHistory['opening']['book'],'Kapitalisasi diposting bulan ini'=>$monthlyHistory['capitalization'],'Akumulasi awal pada kapitalisasi bulan ini'=>$monthlyHistory['opening_accumulated_added'],'Beban penyusutan bulan ini'=>$monthlyHistory['depreciation'],'Nilai buku dilepas bulan ini'=>$monthlyHistory['released_book_value'],'Akumulasi penyusutan akhir bulan'=>$monthlyHistory['closing']['accumulated'],'Nilai buku akhir bulan'=>$monthlyHistory['closing']['book']] as $label=>$value)<tr><th>{{ $label }}</th><td>Rp {{ number_format($value,2,',','.') }}</td></tr>@endforeach
</tbody></table></div>
<h4>Jurnal sumber angka bulan {{ $monthlyHistory['month'] }}</h4><div class="table-wrap"><table><thead><tr><th>Tanggal jurnal</th><th>Nomor jurnal</th><th>Keterangan</th><th>Total debit</th></tr></thead><tbody>
@forelse($monthlyHistory['journals'] as $journal)<tr><td>{{ $journal->journal_date->format('d/m/Y') }}</td><td>{{ $journal->journal_no }}</td><td>{{ $journal->description }}</td><td>Rp {{ number_format($journal->lines_sum_debit,2,',','.') }}</td></tr>@empty<tr><td colspan="4">Belum ada jurnal aktiva terposting pada bulan ini. Tidak perlu menginput ulang inventaris; lengkapi atau periksa proses kapitalisasi dan penyusutan di bawah.</td></tr>@endforelse
</tbody></table></div>
</div>
