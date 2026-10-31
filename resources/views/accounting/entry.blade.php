@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Pencatatan Nonkas</h1><p class="page-sub">Pilih jenis pencatatan; sistem menyiapkan sisi debit dan kredit dengan nominal yang sama.</p></div><a class="btn" href="{{ route('accounting.workspace') }}">Ruang Kerja Accounting</a></div>
<div class="card"><form method="GET" class="period-filter"><label for="entry-kind">Jenis pencatatan</label><select id="entry-kind" name="kind">@foreach($types as $key=>$type)<option value="{{ $key }}" @selected($kind===$key)>{{ $type['label'] }}</option>@endforeach</select><button class="btn">Pilih Jenis</button></form><p>{{ $types[$kind]['help'] }}</p><p><strong>Form ini tidak mencatat arus kas.</strong> Uang masuk/keluar tetap melalui Penerimaan/Pengeluaran. Untuk pencatatan lebih dari dua akun gunakan jurnal manual yang diverifikasi Accounting.</p></div>
<div class="card"><form method="POST" action="{{ route('accounting.entries.store') }}" data-accounting-entry>@csrf
<input type="hidden" name="entry_token" value="{{ old('entry_token',$entryToken) }}"><input type="hidden" name="kind" value="{{ $kind }}">
<h2>{{ $types[$kind]['label'] }}</h2><div class="form-grid">
<div class="field"><label for="entry-date">Tanggal jurnal *</label><input type="date" id="entry-date" name="journal_date" value="{{ old('journal_date',now('Asia/Jakarta')->toDateString()) }}" max="{{ now('Asia/Jakarta')->toDateString() }}" required></div>
<div class="field"><label for="entry-reference">Referensi bukti *</label><input id="entry-reference" name="reference" maxlength="100" value="{{ old('reference') }}" required><small>Nomor invoice, berita acara, atau dokumen koreksi.</small></div>
<div class="field"><label for="entry-amount">Nominal (Rp) *</label><input type="number" id="entry-amount" name="amount" min="0.01" max="1000000000000" step="0.01" value="{{ old('amount') }}" required></div>
<div class="field"><label for="entry-debit">Akun debit *</label><select id="entry-debit" name="debit_account_id" required><option value="">Pilih akun debit</option>@foreach($debitAccounts as $account)<option value="{{ $account->id }}" @selected((string)old('debit_account_id')===(string)$account->id)>{{ $account->code }} / {{ $account->name }}</option>@endforeach</select><small>Hanya akun rincian aktif yang sesuai jenis pencatatan.</small></div>
<div class="field"><label for="entry-credit">Akun kredit *</label><select id="entry-credit" name="credit_account_id" required><option value="">Pilih akun kredit</option>@foreach($creditAccounts as $account)<option value="{{ $account->id }}" @selected((string)old('credit_account_id')===(string)$account->id)>{{ $account->code }} / {{ $account->name }}</option>@endforeach</select></div>
<div class="field"><label for="entry-description">Dasar pencatatan *</label><textarea id="entry-description" name="description" minlength="10" maxlength="400" required>{{ old('description') }}</textarea></div>
</div>
@if($debitAccounts->isEmpty() || $creditAccounts->isEmpty())<p><strong>Akun yang sesuai belum tersedia.</strong> Lengkapi COA terlebih dahulu. Sistem tidak membuat akun atau memilih pasangan akun secara otomatis.</p>@endif
<div class="accounting-entry-preview" aria-live="polite" data-entry-preview>Isi akun dan nominal untuk melihat pasangan jurnal.</div>
<p><label><input type="checkbox" name="verified" value="1" @checked(old('verified')) required> Saya sudah memeriksa bukti, tanggal, akun, dan memastikan pencatatan ini belum masuk saldo awal, transaksi lain, atau jurnal aktiva.</label></p>
<button class="btn primary" @disabled($debitAccounts->isEmpty() || $creditAccounts->isEmpty())>Posting Pencatatan Nonkas</button></form></div>
@push('scripts')<script src="{{ asset('public/js/accounting-entry.js') }}?v=20261030" defer></script>@endpush
@endsection
