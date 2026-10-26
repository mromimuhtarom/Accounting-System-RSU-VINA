<div class="card referral-summary">
<h3>Rekap Pengeluaran Referral per Penerima</h3>
<p class="muted">Rincian pengeluaran aktif pada periode terpilih; nominal sudah termasuk total pengeluaran. SC/RI menunjukkan jumlah transaksi.</p>
<div class="table-wrap"><table class="report-table"><thead><tr><th>Nama Penerima</th><th>Jumlah Transaksi</th><th>SC</th><th>RI</th><th>Total Referral</th></tr></thead><tbody>
@forelse($referralSummary as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['transactions'] }}</td><td>{{ $row['sc'] }}</td><td>{{ $row['ri'] }}</td><td class="money">Rp {{ number_format($row['total'],0,',','.') }}</td></tr>@empty<tr><td colspan="5">Belum ada pengeluaran referral pada periode ini.</td></tr>@endforelse
</tbody><tfoot><tr><th>Total</th><th>{{ $referralSummary->sum('transactions') }}</th><th>{{ $referralSummary->sum('sc') }}</th><th>{{ $referralSummary->sum('ri') }}</th><th class="money">Rp {{ number_format($referralSummary->sum('total'),0,',','.') }}</th></tr></tfoot></table></div>
@if($referralSummary->sum('unclassified'))<p class="muted">{{ $referralSummary->sum('unclassified') }} transaksi historis belum memiliki klasifikasi SC/RI.</p>@endif
</div>
