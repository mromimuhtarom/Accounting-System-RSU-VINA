@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Manajemen Akun</h1><p class="page-sub">Role IT dapat membuat akun, mengubah akun, role, dan status aktif.</p></div><a class="btn primary" href="{{ route('it.users.create') }}">＋ Buat Akun</a></div>
<div class="card"><form method="GET" class="form-grid"><div class="field"><label>Cari</label><input name="search" value="{{ request('search') }}" placeholder="Nama atau email"></div><div style="align-self:end"><button class="btn">Cari</button></div></form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody>
@foreach($users as $u)<tr><td><strong>{{ $u->name }}</strong></td><td>{{ $u->email }}</td><td>@foreach($u->roles as $role)<span class="badge">{{ $role->name }}</span> @endforeach</td><td>@if($u->is_active)<span class="badge" style="background:#ecfdf3;color:#15803d">Aktif</span>@else<span class="badge" style="background:#fff1f2;color:#be123c">Nonaktif</span>@endif</td><td>{{ $u->last_login_at?->format('d-m-Y H:i') ?? '-' }}</td><td><a class="btn" href="{{ route('it.users.edit',$u) }}">Edit Akun</a></td></tr>@endforeach
</tbody></table></div><div class="pagination">{{ $users->links() }}</div></div>
@endsection
