@extends('layouts.app')
@section('content')
<div class="card" style="margin-bottom:18px">
  <h2>Neraca Saldo</h2>
  <p class="muted">Saldo berdasarkan jurnal yang sudah diposting. Untuk transaksi historis tanpa pemetaan lengkap, lihat bagian rekonsiliasi di bawah.</p>
  @include('reports.partials_date_filter')
</div>
<div class="card" style="margin-bottom:18px">
  <h3>Status Pencatatan Akuntansi</h3>
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px">
   <div style="flex:1;min-width:150px;padding:15px;border:1px solid #dce9e4;border-radius:12px"><small>Jurnal Diposting</small><h3>{{ number_format($postedCount,0,',','.') }}</h3></div>
   <div style="flex:1;min-width:150px;padding:15px;border:1px solid #dce9e4;border-radius:12px"><small>Penerimaan Belum Dijurnal</small><h3>{{ number_format($unpostedReceipts,0,',','.') }}</h3></div>
   <div style="flex:1;min-width:150px;padding:15px;border:1px solid #dce9e4;border-radius:12px"><small>Pengeluaran Belum Dijurnal</small><h3>{{ number_format($unpostedExpenses,0,',','.') }}</h3></div>
  </div>
  @if($postedCount===0)
   <p style="background:#fff7e8;padding:12px;border-radius:10px">Belum ada jurnal diposting untuk periode ini. Neraca Saldo belum dapat dihitung. Jalankan pratinjau jurnal historis dan perbaiki pemetaan akun sebelum posting.</p>
  @elseif($unpostedReceipts + $unpostedExpenses > 0)
   <p style="background:#fff7e8;padding:12px;border-radius:10px">Sebagian transaksi operasional belum dijurnal; Neraca Saldo berikut belum lengkap.</p>
  @endif
</div>
<div class="card" style="margin-bottom:18px">
  <h3>Saldo Akun (Jurnal Diposting)</h3>
  <div class="table-wrap"><table><thead><tr><th>Kode Akun</th><th>Nama Akun</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead><tbody>
  @forelse($rows as $row)
   <tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td class="money">{{ number_format($row->debit,2,',','.') }}</td><td class="money">{{ number_format($row->credit,2,',','.') }}</td><td class="money">{{ number_format($row->balance,2,',','.') }}</td></tr>
  @empty <tr><td colspan="5">Belum ada saldo akun yang dapat ditampilkan.</td></tr>
  @endforelse
  </tbody><tfoot><tr><th colspan="2">TOTAL</th><th class="money">{{ number_format($totals['debit'],2,',','.') }}</th><th class="money">{{ number_format($totals['credit'],2,',','.') }}</th><th></th></tr></tfoot></table></div>
  {{ $rows->links() }}
</div>
<div class="card"><h3>Rekap Seluruh Transaksi Operasional (Pembanding)</h3>
<p class="muted">Ringkasan semua transaksi operasional termasuk yang sudah dijurnal; bukan angka tambahan Neraca Saldo dan tidak boleh dijumlahkan ke saldo jurnal. Nilai tagihan penjamin tidak membuktikan penerimaan kas.</p>
<div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Akun Operasional</th><th>Jumlah</th><th>Nominal</th></tr></thead><tbody>
@foreach($operational as $item)<tr><td>{{ $item->jenis }}</td><td>{{ $item->nama }}</td><td>{{ number_format($item->jumlah,0,',','.') }}</td><td class="money">Rp {{ number_format($item->nominal,0,',','.') }}</td></tr>@endforeach
</tbody></table></div>
<p><strong>Seluruh Penerimaan Tercatat:</strong> Rp {{ number_format($operationalTotals['penerimaan'],0,',','.') }} | <strong>Seluruh Pengeluaran Tercatat:</strong> Rp {{ number_format($operationalTotals['pengeluaran'],0,',','.') }}</p>
</div>
@endsection
