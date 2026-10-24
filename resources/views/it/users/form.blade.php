<form method="POST" action="{{ $action }}">@csrf @if($method==='PUT')@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>Nama</label><input name="name" value="{{ old('name',$managedUser?->name) }}" required></div>
<div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email',$managedUser?->email) }}" required></div>
<div class="field"><label>Password {{ $managedUser?'(kosongkan jika tidak diganti)':'' }}</label><input type="password" name="password" {{ $managedUser?'':'required' }}></div>
<div class="field"><label>Status Akun</label><select name="is_active"><option value="1" @selected((string)old('is_active',$managedUser?->is_active ?? 1)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',$managedUser?->is_active ?? 1)==='0')>Nonaktif</option></select></div>
<div class="field" style="grid-column:1/-1"><label>Role</label><div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:8px">@foreach($roles as $role)@php($checked=in_array($role->id,old('role_ids',$managedUser?->roles?->pluck('id')->all() ?? [])))<label style="display:flex;gap:7px;align-items:center"><input style="width:auto" type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($checked)> {{ $role->name }}</label>@endforeach</div></div>
</div>
<div style="margin-top:18px;display:flex;gap:8px"><button class="btn primary">Simpan Akun</button><a class="btn" href="{{ route('it.users.index') }}">Batal</a></div>
</form>
