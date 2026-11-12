@php($selectedDoctor = (string) ($doctorInputValue ?? ''))
<select id="{{ $doctorInputId ?? 'receipt-doctor' }}" name="{{ $doctorInputName ?? 'doctor_name' }}" data-allow-create="true" data-create-label="Tambah dokter" data-search-placeholder="Cari nama dokter / ketik nama baru" data-max-length="150" data-option-group="doctors">
    <option value="">Pilih dokter (opsional)</option>
    @foreach($doctorNames as $doctorName)<option value="{{ $doctorName }}" @selected($selectedDoctor === $doctorName)>{{ $doctorName }}</option>@endforeach
    @if($selectedDoctor !== '' && !$doctorNames->contains($selectedDoctor))<option value="{{ $selectedDoctor }}" selected>{{ $selectedDoctor }}</option>@endif
</select>
<small>Belum tersedia? Ketik nama lalu pilih “Tambah dokter”. Tersimpan setelah transaksi disimpan.</small>
