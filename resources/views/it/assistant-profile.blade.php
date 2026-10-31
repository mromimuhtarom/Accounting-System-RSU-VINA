@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Profil Asisten</h1><p class="page-sub">Ubah nama dan foto asisten untuk seluruh pengguna. Pengaturan ini hanya dapat diubah oleh role IT.</p></div></div>
<div class="card"><form method="POST" action="{{ route('it.assistant-profile.update') }}" enctype="multipart/form-data" data-assistant-profile-form>@csrf @method('PUT')
<input type="hidden" name="lock_version" value="{{ old('lock_version',$profile->lock_version) }}">
<p class="page-sub">Pratinjau profil — perubahan berlaku setelah disimpan.</p>
<div class="assistant-profile-preview" aria-label="Preview profil"><div class="assistant-avatar-wrap">@if($photoUrl)<img src="{{ $photoUrl }}" class="assistant-avatar" alt="" data-assistant-avatar>@endif<span class="assistant-avatar assistant-avatar-fallback" @if($photoUrl) hidden @endif aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->name,0,1)) }}</span></div><div><strong data-profile-name-preview>{{ old('name',$profile->name) }}</strong><p>Asisten panduan dan analisis sistem</p></div></div>
<div class="form-grid"><div class="field"><label for="assistant-profile-name">Nama asisten *</label><input id="assistant-profile-name" name="name" value="{{ old('name',$profile->name) }}" minlength="2" maxlength="60" required><small>Digunakan pada tombol, judul panel, sapaan dan balasan chat.</small></div>
<div class="field"><label for="assistant-profile-photo">Foto profil (opsional)</label><input id="assistant-profile-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG atau WebP. Maksimal 2 MB; dimensi 32–2048 piksel pada setiap sisi. Foto persegi paling sesuai.</small></div></div>
@if($photoUrl)<p><label><input type="checkbox" name="remove_photo" value="1" @checked(old('remove_photo'))> Hapus foto dan gunakan inisial nama</label></p>@endif
<p>Nama dan foto tersimpan untuk seluruh akun, tanpa mengubah hak akses atau kemampuan asisten. Pengguna yang masih membuka halaman lama perlu memuat ulang untuk melihat perubahan.</p>
<button type="submit" class="btn primary">Simpan Profil Asisten</button></form></div>
@push('scripts')<script src="{{ asset('public/js/assistant-profile.js') }}?v=20261030-1" defer></script>@endpush
@endsection
