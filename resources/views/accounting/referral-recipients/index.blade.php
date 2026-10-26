@extends('layouts.app')
@section('content')
<div class="card actions"><h2 style="flex:1">Master Data Penerima Referral</h2><a class="btn primary" href="{{ route('accounting.referral-recipients.create') }}">Tambah Penerima</a><a class="btn" href="{{ route('accounting.referrals.index') }}">Pengeluaran Referral</a></div>
<div class="card"><form method="GET" class="form-grid">
    <div class="field"><label>Cari nama / ID / kontak</label><input name="q" value="{{ request('q') }}"></div>
    <div class="field"><label>Status</label><select name="active"><option value="">Semua</option><option value="1" @selected(request('active')==='1')>Aktif</option><option value="0" @selected(request('active')==='0')>Nonaktif</option></select></div>
    <div class="actions"><button class="btn">Cari</button><a class="btn" href="{{ route('accounting.referral-recipients.index') }}">Reset</a></div>
</form></div>
<div class="card"><details><summary>Import Excel Penerima</summary><p>Nama yang sama akan menggunakan data existing. Nama mirip perlu diperiksa melalui form tambah penerima. Data existing tidak ditimpa.</p><a class="btn" href="{{ route('accounting.referral-recipients.template') }}">Download Template</a><form method="POST" enctype="multipart/form-data" action="{{ route('accounting.referral-recipients.import') }}">@csrf<div class="field"><label>File Excel</label><input type="file" name="file" accept=".xlsx,.xls" required></div><button class="btn">Import Penerima</button></form></details></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>ID Penerima</th><th>Nama</th><th>Kontak</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($recipients as $recipient)<tr><td>{{ $recipient->code }}</td><td>{{ $recipient->name }}</td><td>{{ $recipient->phone ?: '-' }}</td><td>{{ $recipient->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><div class="actions"><a class="btn" href="{{ route('accounting.referral-recipients.show',$recipient) }}">Detail / Rekap</a><a class="btn" href="{{ route('accounting.referral-recipients.edit',$recipient) }}">Edit</a><form method="POST" action="{{ route('accounting.referral-recipients.destroy',$recipient) }}" onsubmit="return confirm('Hapus penerima yang belum digunakan? Jika sudah digunakan, penerima akan dinonaktifkan.')">@csrf @method('DELETE')<button class="btn danger">Hapus / Nonaktifkan</button></form></div></td></tr>
@empty<tr><td colspan="5">Belum ada penerima referral.</td></tr>@endforelse
</tbody></table></div>{{ $recipients->links() }}</div>
@endsection
