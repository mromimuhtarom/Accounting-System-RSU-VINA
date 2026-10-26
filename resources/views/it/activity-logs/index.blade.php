@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">{{ $isSessionLog ? 'Log Login / Logout' : 'Log Perubahan Data' }}</h1><p class="page-sub">{{ $isSessionLog ? 'Riwayat akses akun, perangkat, IP, login, logout manual, dan logout otomatis.' : 'Riwayat penambahan, pengeditan, penghapusan, dan pemulihan data beserta nilai sebelum dan sesudah.' }} Waktu ditampilkan dalam WIB.</p></div></div>
<div class="card">
    <form class="form-grid" method="GET" action="{{ route($logRoutePrefix . '.index') }}">
        <div class="field"><label for="audit-actor">Akun pelaku</label><select id="audit-actor" name="actor_id"><option value="">Semua akun</option>@foreach($actors as $actor)<option value="{{ $actor->actor_id }}" @selected((string)request('actor_id') === (string)$actor->actor_id)>{{ $actor->actor_name }} (#{{ $actor->actor_id }})</option>@endforeach</select></div>
        @unless($isSessionLog)
        <div class="field"><label for="audit-menu">Menu</label><select id="audit-menu" name="menu"><option value="">Semua menu</option>@foreach($menus as $menu)<option @selected(request('menu') === $menu)>{{ $menu }}</option>@endforeach</select></div>
        <div class="field"><label for="audit-form">Nama form</label><select id="audit-form" name="form_name"><option value="">Semua form</option>@foreach($forms as $form)<option @selected(request('form_name') === $form)>{{ $form }}</option>@endforeach</select></div>
        @endunless
        <div class="field"><label for="audit-action">Aktivitas</label><select id="audit-action" name="action"><option value="">Semua aktivitas</option>@foreach(($isSessionLog ? ['login'=>'Login','logout'=>'Logout','logout_auto'=>'Logout Otomatis'] : ['created'=>'Tambah (Insert)','updated'=>'Edit','deleted'=>'Hapus (Delete)','restored'=>'Pulihkan']) as $value=>$label)<option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="audit-from">Dari tanggal (WIB)</label><input id="audit-from" type="date" name="date_from" value="{{ request('date_from') }}"></div>
        <div class="field"><label for="audit-to">Sampai tanggal (WIB)</label><input id="audit-to" type="date" name="date_to" value="{{ request('date_to') }}"></div>
        <div class="field"><label for="audit-search">Cari akun / transaksi / perangkat / IP</label><input id="audit-search" name="q" value="{{ request('q') }}" placeholder="Nama, email, nomor transaksi, perangkat, IP..."></div>
        <div class="actions" style="align-self:end"><button class="btn primary" type="submit">Tampilkan Log</button><a class="btn" href="{{ route($logRoutePrefix . '.index') }}">Reset</a></div>
    </form>
</div>
<div class="card"><p>{{ number_format($logs->total(), 0, ',', '.') }} aktivitas ditemukan.</p><div class="table-wrap"><table>
    <thead><tr><th>Tanggal / Waktu (WIB)</th><th>Akun pelaku</th><th>Menu / Form</th><th>Aktivitas</th><th>Perangkat / IP</th><th>Data</th><th>Detail</th></tr></thead>
    <tbody>@forelse($logs as $log)<tr>
        <td style="white-space:nowrap">{{ $log->occurred_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }}</td>
        <td><strong>{{ $log->actor_name }}</strong><div>{{ $log->actor_email }}</div><div class="muted">{{ $log->actor_roles }} · Akun #{{ $log->actor_id }}</div></td>
        <td><strong>{{ $log->menu }}</strong><div>{{ $log->form_name }}</div></td>
        <td><span class="badge {{ $log->action === 'deleted' ? 'danger' : '' }}">{{ $log->actionLabel() }}</span></td>
        <td>{{ $log->device_name ?? 'Tidak tercatat' }}<div class="muted">IP: {{ $log->ip_address ?? '—' }}</div></td>
        <td>{{ $log->subject_label }}<div class="muted">ID: {{ $log->subject_id }}</div></td>
        <td><a class="btn" href="{{ route($logRoutePrefix . '.show', $log) }}">{{ $log->authentication_session_id ? 'Detail Sesi' : 'Lihat Sebelum / Sesudah' }}</a></td>
    </tr>@empty<tr><td colspan="7">Belum ada aktivitas yang sesuai filter.</td></tr>@endforelse</tbody>
</table></div><div class="pagination">{{ $logs->links() }}</div></div>
@endsection
