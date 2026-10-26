@extends('layouts.app')
@section('content')
<style>.audit-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}.audit-meta dt{font-size:14px;color:#52635e}.audit-meta dd{margin:4px 0 0;font-size:16px;overflow-wrap:anywhere}.audit-values td{vertical-align:top}.audit-values pre{margin:0;font:inherit;white-space:pre-wrap;overflow-wrap:anywhere;max-width:520px}.audit-values .audit-changed{background:#fff9e8}.audit-values th{font-size:14px}.audit-values td{font-size:16px}</style>
<div class="page-head"><div><h1 class="page-title">Detail Aktivitas #{{ $log->id }}</h1><p class="page-sub">{{ $log->actionLabel() }} · {{ $log->subject_label }}</p></div><a class="btn" href="{{ route($logRoutePrefix . '.index') }}">Kembali ke Daftar Log</a></div>
<div class="card"><dl class="audit-meta">
    <div><dt>Akun yang melakukan perubahan</dt><dd><strong>{{ $log->actor_name }}</strong><br>{{ $log->actor_email }}<br>Akun #{{ $log->actor_id }} · {{ $log->actor_roles }}</dd></div>
    <div><dt>Tanggal dan waktu (WIB)</dt><dd>{{ $log->occurred_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }}</dd></div>
    <div><dt>Menu / Nama form</dt><dd>{{ $log->menu }} / {{ $log->form_name }}</dd></div>
    <div><dt>Data yang diubah</dt><dd>{{ $log->subject_label }}<br>ID: {{ $log->subject_id }}</dd></div>
    <div><dt>Halaman asal (route)</dt><dd>{{ $log->route_name ?? 'Proses sistem' }}</dd></div>
    <div><dt>Alamat IP</dt><dd>{{ $log->ip_address ?? '—' }}</dd></div>
    @if($log->device_name)
        <div><dt>Perangkat / Browser</dt><dd>{{ $log->device_name }}</dd></div>
        <div><dt>Nomor sesi perangkat</dt><dd>#{{ $log->authentication_session_id }}</dd></div>
        <div><dt>Informasi browser (User-Agent)</dt><dd>{{ $log->user_agent ?: 'Tidak tersedia' }}</dd></div>
    @endif
</dl></div>
@if($log->device_name)<div class="card"><p style="margin:0">Identifikasi perangkat mengikuti informasi yang dilaporkan browser. Nama komputer atau nama pribadi HP tidak tersedia secara otomatis. Logout otomatis dicatat pada batas masa tidak aktif, meskipun browser sudah ditutup.</p></div>@endif
@php($fields = array_unique(array_merge(array_keys($log->before_values ?? []), array_keys($log->after_values ?? []))))
<div class="card"><h2 style="font-size:20px">Data sebelum dan sesudah</h2><p>Baris berwarna menandai kolom yang berubah. Kata sandi selalu disembunyikan.</p>
    <div class="table-wrap"><table class="audit-values"><thead><tr><th>Kolom</th><th>Sebelum</th><th>Sesudah</th></tr></thead><tbody>
    @foreach($fields as $field)<tr class="{{ in_array($field, $log->changed_fields ?? [], true) ? 'audit-changed' : '' }}">
        <td>{{ \App\Models\ActivityLog::fieldLabel($field) }}</td>
        <td><pre>{{ \App\Models\ActivityLog::displayValue($log->before_values[$field] ?? null) }}</pre></td>
        <td><pre>{{ \App\Models\ActivityLog::displayValue($log->after_values[$field] ?? null) }}</pre></td>
    </tr>@endforeach
    </tbody></table></div>
</div>
@endsection
