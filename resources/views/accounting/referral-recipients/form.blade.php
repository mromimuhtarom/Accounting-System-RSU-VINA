@extends('layouts.app')
@section('content')
<div class="card"><h2>{{ $recipient ? 'Edit Penerima Referral' : 'Tambah Penerima Referral' }}</h2>
<form method="POST" action="{{ $recipient ? route('accounting.referral-recipients.update',$recipient) : route('accounting.referral-recipients.store') }}">@csrf @if($recipient) @method('PUT') @endif
@if($recipient)<p>ID: {{ $recipient->code }}</p>@endif
<div class="form-grid">
<div class="field"><label>Nama Penerima</label><input name="name" value="{{ old('name',$recipient?->name) }}" maxlength="150" required></div>
<div class="field"><label>Nomor Kontak (opsional)</label><input name="phone" value="{{ old('phone',$recipient?->phone) }}" maxlength="50"></div>
<div class="field"><label>Keterangan (opsional)</label><textarea name="notes" maxlength="2000">{{ old('notes',$recipient?->notes) }}</textarea></div>
<div class="field"><label>Status</label><select name="is_active"><option value="1" @selected(old('is_active',$recipient?->is_active ?? true))>Aktif</option><option value="0" @selected(!old('is_active',$recipient?->is_active ?? true))>Nonaktif</option></select></div>
</div>
@if(session('similarRecipients'))<div class="card" role="alert"><strong>Nama mirip penerima existing:</strong><ul>@foreach(session('similarRecipients') as $existing)<li><a href="{{ route('accounting.referral-recipients.show',$existing['id']) }}">{{ $existing['name'] }} ({{ $existing['is_active'] ? 'Aktif' : 'Nonaktif' }})</a></li>@endforeach</ul><label><input type="checkbox" name="confirm_distinct" value="1"> Saya sudah memeriksa dan ini penerima yang berbeda.</label></div>@endif
<div class="actions"><button class="btn primary">Simpan</button><a class="btn" href="{{ route('accounting.referral-recipients.index') }}">Batal</a></div>
</form></div>
@endsection
