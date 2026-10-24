@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Edit Penerimaan</h1><p class="page-sub">Perbaiki transaksi sebelum dijurnal oleh Accounting.</p></div><a class="btn" href="{{ route('cashier.receipts.index') }}">← Kembali</a></div>
<div class="card"><form method="POST" action="{{ route('cashier.receipts.update',$receipt) }}">@csrf @method('PUT')
<div class="form-grid">
<div class="field"><label>No Transaksi</label><input value="{{ $receipt->transaction_no }}" disabled></div>
<div class="field"><label>Tanggal</label><input type="date" name="transaction_date" value="{{ old('transaction_date',$receipt->transaction_date->format('Y-m-d')) }}" required></div>
<div class="field"><label>Pasien</label><select name="patient_id" required><option value="">Pilih pasien</option>@foreach($patients as $p)<option value="{{ $p->id }}" @selected(old('patient_id',$receipt->patient_id)==$p->id)>{{ $p->medical_record_no }} - {{ $p->name }}</option>@endforeach</select></div>
<div class="field"><label>Unit Pelayanan</label><select name="service_unit_id" required>@foreach($units as $x)<option value="{{ $x->id }}" @selected(old('service_unit_id',$receipt->service_unit_id)==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field"><label>Penjamin</label><select name="guarantor_id" required>@foreach($guarantors as $x)<option value="{{ $x->id }}" @selected(old('guarantor_id',$receipt->guarantor_id)==$x->id)>{{ $x->name }}{{ $x->is_bpjs ? ' (BPJS)' : '' }}</option>@endforeach</select></div>
<div class="field"><label>Jenis Pelayanan</label><input name="service_type" value="{{ old('service_type',$receipt->service_type) }}" placeholder="Opsional"></div>
<div class="field"><label>Nominal</label><input type="number" min="1" step="0.01" name="amount" value="{{ old('amount',$receipt->amount) }}" required></div>
<div class="field"><label>Metode Pembayaran</label><select name="payment_method" required>@foreach(['CASH'=>'Cash','TRANSFER'=>'Transfer','QRIS'=>'QRIS','CARD'=>'Debit/Kartu','GUARANTOR'=>'Penjamin / Piutang'] as $key=>$label)<option value="{{ $key }}" @selected(old('payment_method',$receipt->payment_method)===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Kas / Bank</label><select name="cash_bank_account_id"><option value="">Pilih / kosong bila Penjamin</option>@foreach($cashBanks as $x)<option value="{{ $x->id }}" @selected(old('cash_bank_account_id',$receipt->cash_bank_account_id)==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field" style="grid-column:1/-1"><label>Keterangan</label><textarea name="notes">{{ old('notes',$receipt->notes) }}</textarea></div>
</div><div style="margin-top:18px;display:flex;gap:10px"><button class="btn primary">Simpan Perubahan</button><a class="btn" href="{{ route('cashier.receipts.index') }}">Batal</a></div>
</form></div>
@endsection
