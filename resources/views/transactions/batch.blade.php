@extends('layouts.app')
@section('content')
@php
    $isReceipt = $module === 'receipts';
    $isReferral = $module === 'referrals';
    $grouped = request()->boolean('grouped') || is_array(old('common'));
    $historyField = $isReceipt ? 'notes' : 'description';
    $historyQuery = $isReceipt
        ? \App\Models\Receipt::where('cashier_id', auth()->id())->where('status', 'ACTIVE')
        : \App\Models\Expense::where('created_by', auth()->id())->where('status', 'ACTIVE')->where('expense_type', $isReferral ? 'REFERRAL' : 'GENERAL');
    if ($isReceipt && ($isInsurance ?? false)) $historyQuery->whereHas('guarantor', fn ($q) => $q->whereIn('category', \App\Models\Guarantor::INSURANCE_CATEGORIES));
    $recentDescriptions = $historyQuery->latest('id')->limit(300)->pluck($historyField)->filter()->unique()->values();
    $title = $isReceipt ? 'Pemasukan' : ($isReferral ? 'Pengeluaran Referal' : 'Pengeluaran Umum');
    $batchRows = old('rows', [0=>[]]);
    $batchRows = is_array($batchRows) ? array_filter($batchRows, fn ($row,$key) => is_array($row) && ctype_digit((string)$key), ARRAY_FILTER_USE_BOTH) : [];
    if (!$batchRows) $batchRows = [0=>[]];
    $fields = [$isReceipt ? 'transaction_date' : 'expense_date' => ['Tanggal', 'date', true]];
    if ($isReceipt) {
        $fields += ['chart_account_id'=>['Kode / Chart of Accounts','select',true,$accounts->mapWithKeys(fn ($a) => [$a->id=>$a->code.' - '.$a->name])->all()],
            'guarantor_id'=>['Sumber / Penjamin','select',$isInsurance ?? false,$guarantors->pluck('name','id')->all()],
            'service_type'=>['Jenis Pelayanan','select',false,['RAWAT_JALAN'=>'Rawat Jalan','RAWAT_INAP'=>'Rawat Inap','MCU'=>'MCU','LAINNYA'=>'Lainnya']],
            'notes'=>['Keterangan (termasuk nama pasien)','textarea',true], 'doctor_name'=>['Nama Dokter','text',false],
            'hd'=>['HD','text',false], 'rs'=>['RS','text',false]];
    } elseif ($isReferral) {
        $fields += ['referral_recipient_id'=>['Penerima Referral','select',true,$recipients->pluck('name','id')->all()],
            'referral_patient_name'=>['Nama Pasien','text',true], 'referral_medical_record_no'=>['Nomor RM','text',true],
            'referral_procedure'=>['Jenis Tindakan','select',true,['SC'=>'SC','RI'=>'RI']], 'description'=>['Keterangan','textarea',false]];
    } else {
        $fields += ['expense_category_id'=>['Kategori Pengeluaran','select',true,$categories->mapWithKeys(fn ($c) => [$c->id=>$c->name.' - '.$c->expenseAccount?->code])->all()], 'description'=>['Keterangan','textarea',true]];
    }
    $fields['amount'] = ['Nominal (Rp)','number',true];
    if (!$isReceipt) $fields += ['payment_method'=>['Metode Pembayaran','select',true,['CASH'=>'Tunai (Cash)','TRANSFER'=>'Transfer','QRIS'=>'QRIS','CARD'=>'Debit / Kartu']],
        'cash_bank_account_id'=>['Kas / Bank','select',true,$cashBanks->pluck('name','id')->all()], 'reference_no'=>['Nomor Referensi','text',false],
        'attachment'=>['Bukti Transaksi (opsional, maksimal 5 MB)','file',false]];
@endphp
<div class="card"><div class="actions"><h2 style="flex:1">Input {{ $title }} Banyak Sekaligus</h2><a class="btn" href="{{ $singleUrl }}">Input Satu Transaksi</a><a class="btn" href="{{ $returnUrl }}">Kembali</a></div><p>Tambahkan hingga 20 transaksi, lalu klik Simpan Semua. Setiap baris dicatat sebagai transaksi tersendiri. Jika ada kesalahan, semua baris belum disimpan. Lampiran perlu dipilih ulang jika validasi gagal.</p></div>
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-batch-form>
@csrf
<div class="card actions">
<a class="btn" href="{{ $singleUrl }}">Form Biasa</a>
<a class="btn" href="{{ $singleUrl }}?multiple=1">Banyak Transaksi / Kategori Berbeda</a>
<a class="btn" href="{{ $singleUrl }}?multiple=1&amp;grouped=1">Satu Kategori, Banyak Rincian</a>
</div>
@if($grouped)
<div class="card"><h3>Satu Kategori, Banyak Rincian</h3><p>Pilihan di sini berlaku untuk semua rincian. Setiap rincian disimpan sebagai transaksi tersendiri; total dihitung di bawah.</p><div class="form-grid">
@foreach(\App\Services\BatchTransactionService::SHARED_FIELDS as $key)
@if(isset($fields[$key]))
@php
    $definition = $fields[$key];
    [$label, $type, $required] = $definition;
    $commonValue = old('common.'.$key, $batchRows[array_key_first($batchRows)][$key] ?? ($type === 'date' ? now()->format('Y-m-d') : ($key === 'payment_method' ? 'CASH' : '')));
    if (!is_scalar($commonValue)) $commonValue = '';
@endphp
<div class="field"><label for="common-{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
@if($type === 'select')
<select id="common-{{ $key }}" name="common[{{ $key }}]" @required($required)><option value="">Pilih {{ $label }}</option>@foreach($definition[3] as $option=>$text)<option value="{{ $option }}" @selected((string)$commonValue === (string)$option)>{{ $text }}</option>@endforeach</select>
@else
<input id="common-{{ $key }}" name="common[{{ $key }}]" type="{{ $type }}" value="{{ $commonValue }}" @required($required)>
@endif
</div>
@php
    unset($fields[$key]);
@endphp
@endif
@endforeach
</div></div>
@endif
@if(!$isReferral)
<div class="card">
    <h3>Input cepat dari Excel / buku kas</h3>
    <p>{{ $grouped ? 'Isi pilihan bersama pada bagian atas. Semua rincian memakai kategori / akun yang sama.' : 'Isi tanggal, kategori / akun dan pilihan lainnya pada transaksi pertama. Baris baru mengikuti pilihan tersebut.' }} Satu PT atau rincian = satu baris.</p>
    <label for="batch-paste">Tempel 2 kolom Excel: Keterangan lalu Nominal (tanpa judul kolom)</label>
    <textarea id="batch-paste" data-batch-paste rows="4" placeholder="PT Mitra Medika&#9;1.337.950&#10;PT Surya Sehat&#9;900.318"></textarea>
    <div class="actions"><button type="button" class="btn" data-import-batch>Masukkan ke Baris Transaksi</button><span data-paste-status role="status" aria-live="polite"></span></div>
    <p>Maksimal 20 transaksi per penyimpanan. Nominal menerima 1.337.950 atau 1.337.950,50. Periksa rincian dan total sebelum menyimpan.</p>
</div>
@endif
<div data-batch-rows>
@foreach($batchRows as $index=>$row)
    @include('transactions.batch-row', ['rowIndex'=>$index,'rowData'=>$row])
@endforeach
</div>
<div class="card actions"><button type="button" class="btn" data-add-batch-row>+ Tambah Transaksi</button><strong data-batch-count aria-live="polite"></strong><strong data-batch-total aria-live="polite"></strong><button type="submit" class="btn primary">Simpan Semua Transaksi</button></div>
<template data-batch-template>@include('transactions.batch-row', ['rowIndex'=>'__INDEX__','rowData'=>[]])</template>
</form>
@push('scripts')<script src="{{ asset('public/js/batch-transactions.js') }}?v=20261113-perf-2" defer></script>@endpush
@endsection
