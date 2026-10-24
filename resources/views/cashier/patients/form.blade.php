<form method="POST" action="{{ $action }}">@csrf @if($method==='PUT')@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>No Rekam Medis</label><input name="medical_record_no" value="{{ old('medical_record_no',$patient?->medical_record_no) }}" required placeholder="Contoh: RM000123"></div>
<div class="field"><label>Nama Pasien</label><input name="name" value="{{ old('name',$patient?->name) }}" required></div>
<div class="field"><label>Jenis Kelamin</label><select name="gender"><option value="">Pilih</option><option value="MALE" @selected(old('gender',$patient?->gender)==='MALE')>Laki-laki</option><option value="FEMALE" @selected(old('gender',$patient?->gender)==='FEMALE')>Perempuan</option></select></div>
<div class="field"><label>Tanggal Lahir</label><input type="date" name="birth_date" value="{{ old('birth_date',$patient?->birth_date?->format('Y-m-d')) }}"></div>
<div class="field"><label>No Telepon</label><input name="phone" value="{{ old('phone',$patient?->phone) }}"></div>
<div class="field" style="grid-column:1/-1"><label>Alamat</label><textarea name="address">{{ old('address',$patient?->address) }}</textarea></div>
</div>
<div style="margin-top:18px;display:flex;gap:8px"><button class="btn primary">Simpan Pasien</button><a class="btn" href="{{ route('cashier.patients.index') }}">Batal</a></div>
</form>
