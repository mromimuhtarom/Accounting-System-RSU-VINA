@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Input Penerimaan</h1><p class="page-sub">Pilih pasien yang sudah terdaftar lalu input penerimaan rumah sakit.</p></div><div class="actions"><a class="btn" href="{{ route('cashier.patients.create') }}">＋ Pasien Baru</a><a class="btn" href="{{ route('cashier.receipts.index') }}">Lihat Penerimaan Saya</a></div></div>
<div class="card">
<form method="POST" action="{{ route('cashier.receipts.store') }}">@csrf
<div class="form-grid">
<div class="field"><label>Tanggal</label><input type="date" name="transaction_date" value="{{ old('transaction_date',date('Y-m-d')) }}" required></div>
<div class="field"><label>Pasien</label><select name="patient_id" required><option value="">Pilih pasien</option>@foreach($patients as $p)<option value="{{ $p->id }}" @selected(old('patient_id')==$p->id)>{{ $p->medical_record_no }} - {{ $p->name }}</option>@endforeach</select><div class="muted" style="margin-top:6px">Pasien belum ada? Tambahkan melalui menu Data Pasien.</div></div>
<div class="field"><label>Unit Pelayanan</label><select name="service_unit_id" required><option value="">Pilih unit</option>@foreach($units as $x)<option value="{{ $x->id }}" @selected(old('service_unit_id')==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field"><label>Penjamin</label><select name="guarantor_id" required><option value="">Pilih penjamin</option>@foreach($guarantors as $x)<option value="{{ $x->id }}" @selected(old('guarantor_id')==$x->id)>{{ $x->name }}{{ $x->is_bpjs ? ' (BPJS)' : '' }}</option>@endforeach</select></div>
<div class="field"><label>Jenis Pelayanan</label><input name="service_type" value="{{ old('service_type') }}" placeholder="Opsional"></div>
<div class="field"><label>Nominal</label><input type="number" min="1" step="0.01" name="amount" value="{{ old('amount') }}" required placeholder="0"></div>
<div class="field"><label>Metode Pembayaran</label><select name="payment_method" required><option value="CASH">Cash</option><option value="TRANSFER">Transfer</option><option value="QRIS">QRIS</option><option value="CARD">Debit/Kartu</option><option value="GUARANTOR">Penjamin / Piutang</option></select></div>
<div class="field"><label>Kas / Bank</label><select name="cash_bank_account_id"><option value="">Pilih / kosong bila Penjamin</option>@foreach($cashBanks as $x)<option value="{{ $x->id }}" @selected(old('cash_bank_account_id')==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field" style="grid-column:1/-1"><label>Keterangan</label><textarea name="notes" placeholder="Catatan tambahan">{{ old('notes') }}</textarea></div>
</div>
<div style="margin-top:18px"><button class="btn primary" type="submit">Simpan Penerimaan</button></div>
</form>
</div>
@endsection
