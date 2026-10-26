@extends('layouts.app')
@section('content')
<style>
.it-presence-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:20px 0}
.it-presence-summary .card{margin:0}.it-presence-summary h2{font-size:16px;margin:0 0 8px;color:#52635e}.it-presence-summary strong{font-size:32px}
.it-presence-chart{display:flex;align-items:center;flex-wrap:wrap;gap:32px;padding:16px 0}.it-presence-donut{height:200px;width:200px;flex:0 0 200px;border-radius:50%;display:grid;place-items:center}.it-presence-center{height:148px;width:148px;background:#fff;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center}.it-presence-center strong{font-size:30px}.it-presence-legend{display:grid;gap:16px;font-size:17px}.it-presence-key{display:inline-block;width:14px;height:14px;border-radius:4px;margin-right:8px}.it-presence-state{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:8px;font-size:15px;font-weight:700;background:#edf1f5;color:#44566a}.it-presence-state.online{background:#e5f5ec;color:#12643e}.it-presence-list td{font-size:15px;vertical-align:top}.it-presence-device{margin-bottom:10px;overflow-wrap:anywhere}.it-presence-notes{font-size:16px;line-height:1.7;color:#52635e}.it-presence-list th{font-size:13px}
@media(max-width:650px){.it-presence-summary{grid-template-columns:1fr}.it-presence-chart{justify-content:center}.it-presence-legend{width:100%}}
</style>
<div class="page-head"><div><h1 class="page-title">Dashboard IT</h1><p class="page-sub">Pantau status akun dan aktivitas perangkat. Diperbarui {{ $checkedAt->format('d-m-Y H:i:s') }} WIB.</p></div><a class="btn primary" href="{{ route('dashboard', request()->only('q','status')) }}">Perbarui Status</a></div>
<div class="it-presence-summary">
    <div class="card"><h2>Total akun</h2><strong>{{ $total }}</strong></div>
    <div class="card"><h2>Akun online</h2><strong style="color:#12643e">{{ $online }}</strong></div>
    <div class="card"><h2>Akun offline</h2><strong>{{ $offline }}</strong></div>
</div>
<div class="card">
    <h2 style="font-size:21px;margin-top:0">Grafik Akun Online dan Offline</h2>
    <div class="it-presence-chart">
        <div class="it-presence-donut" aria-hidden="true" style="background:conic-gradient(#168454 0% {{ $total > 0 ? $online * 100 / $total : 0 }}%,#dbe3e9 {{ $total > 0 ? $online * 100 / $total : 0 }}% 100%)"><div class="it-presence-center"><strong>{{ $total }}</strong><span>akun</span></div></div>
        <div class="it-presence-legend"><div><span class="it-presence-key" style="background:#168454" aria-hidden="true"></span>Online: <strong>{{ $online }} akun</strong></div><div><span class="it-presence-key" style="background:#dbe3e9" aria-hidden="true"></span>Offline: <strong>{{ $offline }} akun</strong></div></div>
    </div>
    <p class="it-presence-notes">Online berarti akun aktif memiliki sedikitnya satu perangkat dengan aktivitas dalam 5 menit terakhir dan sesi belum berakhir. Akun tanpa aktivitas terbaru, sudah logout, atau dinonaktifkan ditampilkan offline. Menutup browser dapat memerlukan hingga 5 menit untuk terdeteksi. Tekan <strong>Perbarui Status</strong> untuk mengambil kondisi terbaru.</p>
    <div class="actions"><a class="btn" href="{{ route('it.session-logs.index') }}">Log Login / Logout</a><a class="btn" href="{{ route('it.activity-logs.index') }}">Log Perubahan Data</a></div>
</div>
<div class="card"><form method="GET" action="{{ route('dashboard') }}" class="form-grid">
    <div class="field"><label for="presence-search">Cari nama / email</label><input id="presence-search" name="q" value="{{ request('q') }}" placeholder="Nama atau email akun"></div>
    <div class="field"><label for="presence-status">Status akun</label><select id="presence-status" name="status"><option value="">Semua status</option><option value="online" @selected(request('status') === 'online')>Online</option><option value="offline" @selected(request('status') === 'offline')>Offline</option></select></div>
    <div class="actions" style="align-self:end"><button class="btn primary" type="submit">Tampilkan</button><a class="btn" href="{{ route('dashboard') }}">Reset</a></div>
</form></div>
<div class="card"><h2 style="font-size:21px">Daftar Status Akun</h2><div class="table-wrap"><table class="it-presence-list">
    <thead><tr><th>Akun</th><th>Role</th><th>Status</th><th>Aktivitas Terakhir (WIB)</th><th>Perangkat / IP</th></tr></thead>
    <tbody>@forelse($users as $user)
        @php
            $isOnline = $user->is_active && $user->has_online_session;
            $latest = $user->latestAuthenticationSession;
            $latestActivity = $user->authenticationSessions->max('last_activity_at') ?? $latest?->last_activity_at;
        @endphp
        <tr><td><strong>{{ $user->name }}</strong><div>{{ $user->email }}</div></td><td>{{ $user->roles->pluck('name')->implode(', ') ?: 'Belum ada role' }}</td>
        <td><span class="it-presence-state {{ $isOnline ? 'online' : '' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>@unless($user->is_active)<div>Akun dinonaktifkan</div>@endunless</td>
        <td>{{ $latestActivity?->copy()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') ?? 'Belum ada aktivitas tercatat' }}</td>
        <td>@if($isOnline)
            @foreach($user->authenticationSessions as $session)<div class="it-presence-device">{{ $session->device_name }}<div class="muted">IP: {{ $session->ip_address }}</div></div>@endforeach
        @elseif($latest)<div>{{ $latest->device_name }}</div><div class="muted">IP terakhir: {{ $latest->ip_address }}</div>
        @else Belum ada perangkat tercatat @endif</td></tr>
    @empty<tr><td colspan="5">Tidak ada akun yang sesuai filter.</td></tr>@endforelse</tbody>
</table></div><div class="pagination">{{ $users->links() }}</div></div>
@endsection
