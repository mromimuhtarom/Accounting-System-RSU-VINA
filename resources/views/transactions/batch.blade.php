@extends('layouts.app')
@section('content')
@php
    $isReceipt = $module === 'receipts';
    $isReferral = $module === 'referrals';
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
<div data-batch-rows>
@foreach($batchRows as $index=>$row)
    @include('transactions.batch-row', ['rowIndex'=>$index,'rowData'=>$row])
@endforeach
</div>
<div class="card actions"><button type="button" class="btn" data-add-batch-row>+ Tambah Transaksi</button><strong data-batch-count aria-live="polite"></strong><button type="submit" class="btn primary">Simpan Semua Transaksi</button></div>
<template data-batch-template>@include('transactions.batch-row', ['rowIndex'=>'__INDEX__','rowData'=>[]])</template>
</form>
@push('scripts')<script src="{{ asset('public/js/batch-transactions.js') }}" defer></script>@endpush
@endsection
