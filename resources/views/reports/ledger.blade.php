@extends('layouts.app')
@section('content')
<div class="card"><h2>Buku Besar / Riwayat Akun</h2>
<p class="muted">Pilih akun dan periode. Jika belum ada jurnal yang diposting, tampilkan riwayat transaksi operasional sebagai informasi, bukan jurnal resmi.</p>
<form method="GET" action="{{ route('reports.ledger') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end">
<div class="field"><label>Akun</label><select name="account_id"><option value="">Otomatis pilih akun yang memiliki transaksi</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(($account?->id ?? 0)==$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
<div class="field"><label>Dari</label><input type="date" name="date_from" value="{{ request('date_from') }}"></div>
<div class="field"><label>Sampai</label><input type="date" name="date_to" value="{{ request('date_to') }}"></div>
<div><button type="submit" class="btn">Tampilkan</button></div></form></div>
@if($account)
<div class="card"><h3>{{ $account->code }} — {{ $account->name }}</h3>
@if($source === 'operasional')<p class="muted" style="padding:12px;border:1px solid #e4d6a4;border-radius:8px">Belum ada jurnal POSTED untuk akun ini pada periode terpilih. Data berikut merupakan <b>riwayat transaksi operasional</b>, tidak dihitung sebagai saldo debit/kredit buku besar.</p>@endif
<div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>No Transaksi</th><th>Jenis / Referensi</th><th>Keterangan</th>@if($source==='jurnal')<th>Debit</th><th>Kredit</th><th>Saldo</th>@else<th>Nominal Transaksi</th>@endif</tr></thead><tbody>
@forelse($lines as $l)<tr><td>{{ $l->display_date instanceof \Carbon\CarbonInterface ? $l->display_date->format('d M Y') : $l->display_date }}</td><td>{{ $l->display_no }}</td><td>{{ $l->display_reference }}</td><td>{{ $l->description ?: '-' }}</td>
@if($source==='jurnal')<td class="money">{{ number_format($l->debit,2,',','.') }}</td><td class="money">{{ number_format($l->credit,2,',','.') }}</td><td class="money">{{ number_format($l->running_balance,2,',','.') }}</td>
@else<td class="money">Rp {{ number_format($l->display_amount,0,',','.') }}</td>@endif
</tr>@empty<tr><td colspan="{{ $source==='jurnal' ? 7 : 5 }}">Tidak ada transaksi akun tersebut pada periode ini.</td></tr>@endforelse
</tbody></table></div>{{ $lines->links() }}</div>
@else<div class="card">Belum ditemukan akun dengan transaksi aktif pada periode ini.</div>@endif
@endsection
