@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><h1 class="page-title">{{ $isInsurance ? 'Input Penerimaan Asuransi' : 'Input Penerimaan' }}</h1><p class="page-sub">Format Penerimaan seperti Accounting. Informasi pasien cukup ditulis dalam Keterangan.</p></div>
    <a class="btn" href="{{ route($routePrefix . '.index') }}">Lihat Semua Penerimaan</a>
</div>
<div class="card">
<form method="POST" action="{{ route($routePrefix . '.store') }}">@csrf

<div class="form-grid">

 <div class="field"><label>Tanggal *</label><input type="date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required></div>
 <div class="field"><label>Kode / Chart of Accounts *</label><select name="chart_account_id" class="js-select2" required><option value="">Pilih akun</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected((string)old('chart_account_id','')===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
 <div class="field"><label>Sumber / Penjamin</label><select name="guarantor_id" class="js-select2" @required($isInsurance)><option value="">{{ $isInsurance ? 'Pilih penjamin asuransi' : 'Tanpa penjamin' }}</option>@foreach($guarantors as $g)<option value="{{ $g->id }}" @selected((string)old('guarantor_id','')===(string)$g->id)>{{ $g->name }}</option>@endforeach</select></div>
 <div class="field"><label>Jenis Pelayanan</label><select name="service_type"><option value="">Tidak ditentukan</option>@foreach(['RAWAT_JALAN'=>'Rawat Jalan','RAWAT_INAP'=>'Rawat Inap','MCU'=>'MCU','LAINNYA'=>'Lainnya'] as $key=>$label)<option value="{{ $key }}" @selected(old('service_type','')===$key)>{{ $label }}</option>@endforeach</select></div>
 <div class="field" style="grid-column:1/-1"><label>Keterangan * (termasuk nama pasien jika ada)</label><textarea name="notes" maxlength="1000" required placeholder="Contoh: Pembayaran Poli IGD atas nama ...">{{ old('notes','') }}</textarea></div>
 <div class="field"><label>Nama Dokter</label><input name="doctor_name" value="{{ old('doctor_name','') }}"></div>
 <div class="field"><label>HD (opsional)</label><input name="hd" value="{{ old('hd','') }}"></div>
 <div class="field"><label>RS (opsional)</label><input name="rs" value="{{ old('rs','') }}"></div>
 <div class="field"><label>Jumlah Penerimaan *</label><input type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount','') }}" required></div>
</div>
<div style="margin-top:18px;display:flex;gap:10px"><button type="submit" class="btn primary">Simpan Penerimaan</button><a class="btn" href="{{ route($routePrefix . '.index') }}">Batal</a></div>
</form></div>
@endsection
