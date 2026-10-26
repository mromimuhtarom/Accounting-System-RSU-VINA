<form method="POST" action="{{ $action }}">@csrf @if($method==='PUT')@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>Tanggal Pengeluaran</label><input type="date" name="expense_date" value="{{ old('expense_date',$referral?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required></div>
<div class="field"><label for="referral-recipient">Nama Penerima Referral</label><select id="referral-recipient" name="referral_recipient_id" required><option value="">Pilih penerima referral</option>@foreach($recipients as $r)<option value="{{ $r->id }}" @selected(old('referral_recipient_id',$referral?->referral_recipient_id)==$r->id)>{{ $r->name }}{{ $r->is_active ? '' : ' (Nonaktif)' }}</option>@endforeach</select><button type="button" class="btn" id="add-referral-recipient">+ Tambah Penerima Baru</button></div>
<div class="field"><label>Nama Pasien</label><input name="referral_patient_name" value="{{ old('referral_patient_name',$referral?->referral_patient_name ?: ($referral?->patient?->name ?: $referral?->receipt?->patient_name)) }}" maxlength="150" required></div>
<div class="field"><label>Nomor RM</label><input name="referral_medical_record_no" value="{{ old('referral_medical_record_no',$referral?->referral_medical_record_no ?: $referral?->patient?->medical_record_no) }}" maxlength="100" required></div>
<div class="field"><label>Jenis Tindakan</label><select name="referral_procedure" required><option value="">Pilih tindakan</option>@foreach(['SC','RI'] as $procedure)<option value="{{ $procedure }}" @selected(old('referral_procedure',$referral?->referral_procedure)===$procedure)>{{ $procedure }}</option>@endforeach</select></div>
<div class="field"><label>Nominal Referal</label><input type="number" min="1" step="0.01" name="amount" value="{{ old('amount',$referral?->amount) }}" required></div>
<div class="field" style="grid-column:1/-1"><label>Keterangan</label><textarea name="description">{{ old('description',$referral?->description) }}</textarea></div>
<div style="grid-column:1/-1"><h3>Pemetaan Akuntansi dan Pembayaran</h3></div>
<div class="field"><label>COA Referal</label><select name="chart_account_id" required><option value="">Pilih COA Referal</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('chart_account_id',$referral?->chart_account_id)==$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select><div class="muted">Kelola akun melalui Master COA, kelompok Referal.</div></div>
<div class="field" style="grid-column:1/-1"><label>Transaksi Penerimaan (opsional)</label><select name="receipt_id"><option value="">Tanpa tautan penerimaan</option>@foreach($sessions as $s)@php($used=$s->referralExpense && $s->referralExpense->id !== ($editingReferralId ?? null))<option value="{{ $s->id }}" @selected(old('receipt_id',$referral?->receipt_id)==$s->id) @disabled($used)>{{ $s->transaction_no }} - {{ $s->transaction_date->format('d-m-Y') }} - {{ $s->account?->name }} - {{ $s->notes }}{{ $used?' (Sudah ada referal)':'' }}</option>@endforeach</select><div class="muted">Satu transaksi penerimaan hanya boleh memiliki satu referal.</div></div>
<div class="field"><label>Metode Pembayaran</label><select name="payment_method" required>@foreach(['CASH'=>'Cash','TRANSFER'=>'Transfer','QRIS'=>'QRIS','CARD'=>'Debit/Kartu'] as $k=>$v)<option value="{{ $k }}" @selected(old('payment_method',$referral?->payment_method)===$k)>{{ $v }}</option>@endforeach</select></div>
<div class="field"><label>Kas / Bank</label><select name="cash_bank_account_id" required>@foreach($cashBanks as $x)<option value="{{ $x->id }}" @selected(old('cash_bank_account_id',$referral?->cash_bank_account_id)==$x->id)>{{ $x->name }}</option>@endforeach</select></div>
<div class="field"><label>No Referensi</label><input name="reference_no" value="{{ old('reference_no',$referral?->reference_no) }}"></div>

</div>
<div style="margin-top:18px;display:flex;gap:8px"><button class="btn primary">Simpan Referal</button><a class="btn" href="{{ route('accounting.referrals.index') }}">Batal</a></div>
</form>

<dialog id="recipient-dialog" class="card" style="max-width:580px;width:90%;border:1px solid #d6e5df" aria-labelledby="recipient-dialog-title">
<h3 id="recipient-dialog-title">Tambah Penerima Referral</h3>
<form id="recipient-modal-form" action="{{ route('accounting.referral-recipients.store') }}" data-similar-url="{{ route('accounting.referral-recipients.similar') }}">@csrf
<div class="field"><label>Nama Penerima</label><input name="name" maxlength="150" required></div>
<div class="field"><label>Nomor Kontak (opsional)</label><input name="phone" maxlength="50"></div>
<div class="field"><label>Keterangan (opsional)</label><textarea name="notes" maxlength="2000"></textarea></div>
<div id="recipient-modal-message" role="status" aria-live="polite"></div>
<div id="recipient-suggestions"></div>
<label id="recipient-confirm-label" hidden><input type="checkbox" name="confirm_distinct" value="1"> Sudah diperiksa, ini penerima yang berbeda.</label>
<div class="actions"><button class="btn primary" type="submit">Simpan Penerima</button><button class="btn" type="button" id="close-recipient-dialog">Batal</button></div>
</form></dialog>
@push('scripts')<script src="{{ asset('public/js/referral-recipient.js') }}" defer></script>@endpush
