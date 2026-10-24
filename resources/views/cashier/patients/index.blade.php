@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><h1 class="page-title">Data Pasien</h1><p class="page-sub">Kelola data pasien sebelum digunakan pada transaksi penerimaan.</p></div>
    <a class="btn primary" href="{{ route('cashier.patients.create') }}">＋ Tambah Pasien</a>
</div>
<div class="card">
    <form method="GET" class="form-grid">
        <div class="field"><label>Cari Pasien</label><input name="search" value="{{ request('search') }}" placeholder="No RM, nama, telepon"></div>
        <div style="align-self:end;display:flex;gap:8px"><button class="btn">Cari</button><a class="btn" href="{{ route('cashier.patients.index') }}">Reset</a></div>
    </form>
</div>
<div class="card">
<div class="table-wrap"><table><thead><tr><th>No RM</th><th>Nama Pasien</th><th>Jenis Kelamin</th><th>Tanggal Lahir</th><th>Telepon</th><th>Aksi</th></tr></thead><tbody>
@forelse($patients as $p)
<tr>
<td><strong>{{ $p->medical_record_no }}</strong></td><td>{{ $p->name }}</td><td>{{ $p->gender==='MALE'?'Laki-laki':($p->gender==='FEMALE'?'Perempuan':'-') }}</td><td>{{ $p->birth_date?->format('d-m-Y') ?? '-' }}</td><td>{{ $p->phone ?? '-' }}</td>
<td><div class="actions"><a class="btn" href="{{ route('cashier.patients.edit',$p) }}">Edit</a><form method="POST" action="{{ route('cashier.patients.destroy',$p) }}" onsubmit="return confirm('Hapus pasien {{ $p->name }} dari daftar aktif? Riwayat transaksi tetap disimpan.')">@csrf @method('DELETE')<button class="btn danger">Delete</button></form></div></td>
</tr>
@empty<tr><td colspan="6" class="muted">Belum ada data pasien.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $patients->links() }}</div>
</div>
@endsection
