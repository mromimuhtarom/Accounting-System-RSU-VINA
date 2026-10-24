<form method="POST" action="{{ $action }}">@csrf @if($method==='PUT')@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>Tanggal Pengeluaran</label><input type="date" name="expense_date" value="{{ old('expense_date',$referral?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required></div>
<div class="field"><label>Pasien</label><select name="patient_id" id="referralPatient" required><option value="">Pilih pasien</option>@foreach($patients as $p)<option value="{{ $p->id }}" @selected(old('patient_id',$referral?->patient_id)==$p->id)>{{ $p->medical_record_no }} - {{ $p->name }}</option>@endforeach</select></div>
<div class="field" style="grid-column:1/-1"><label>Sesi Pasien / Transaksi Penerimaan</label><select name="receipt_id" id="referralSession" required><option value="">Pilih sesi pasien</option>@foreach($sessions as $s)@php($used=$s->referralExpense && $s->referralExpense->id !== ($editingReferralId ?? null))<option value="{{ $s->id }}" data-patient="{{ $s->patient_id }}" data-used="{{ $used ? '1':'0' }}" @selected(old('receipt_id',$referral?->receipt_id)==$s->id) @disabled($used)>{{ $s->transaction_no }} · {{ $s->transaction_date->format('d-m-Y') }} · {{ $s->serviceUnit?->name }}{{ $used?' · SUDAH ADA REFERAL':'' }}</option>@endforeach</select><div class="muted" style="margin-top:6px">Satu sesi/transaksi hanya boleh memiliki satu referal.</div></div>
<div class="field"><label>Nama Penerima Referal</label><input name="referral_recipient" value="{{ old('referral_recipient',$referral?->referral_recipient) }}" required placeholder="Nama dokter / pihak penerima"></div>
<div class="field"><label>Nominal Referal</label><input type="number" min="1" step="0.01" name="amount" value="{{ old('amount',$referral?->amount) }}" required></div>
<div class="field"><label>Metode Pembayaran</label><select name="payment_method" required>@foreach(['CASH'=>'Cash','TRANSFER'=>'Transfer','QRIS'=>'QRIS','CARD'=>'Debit/Kartu'] as $k=>$v)<option value="{{ $k }}" @selected(old('payment_method',$referral?->payment_method)===$k)>{{ $v }}</option>@endforeach</select></div>
<div class="field"><label>Kas / Bank</label><select name="cash_bank_account_id" required>@foreach($cashBanks as $x)<option value="{{ $x->id }}" @selected(old('cash_bank_account_id',$referral?->cash_bank_account_id)==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field"><label>No Referensi</label><input name="reference_no" value="{{ old('reference_no',$referral?->reference_no) }}"></div>
<div class="field" style="grid-column:1/-1"><label>Keterangan</label><textarea name="description">{{ old('description',$referral?->description) }}</textarea></div>
</div>
<div style="margin-top:18px;display:flex;gap:8px"><button class="btn primary">Simpan Referal</button><a class="btn" href="{{ route('accounting.referrals.index') }}">Batal</a></div>
</form>
@push('scripts')
<script>
(function(){
 const patient=document.getElementById('referralPatient');
 const session=document.getElementById('referralSession');
 function filterSessions(){
   const pid=patient.value;
   [...session.options].forEach((o,i)=>{if(i===0)return; const match=!pid||o.dataset.patient===pid; o.hidden=!match;});
   const selected=session.options[session.selectedIndex]; if(selected && selected.value && selected.dataset.patient!==pid) session.value='';
 }
 patient.addEventListener('change',filterSessions); filterSessions();
})();
</script>
@endpush
