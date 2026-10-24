@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Edit Pasien</h1><p class="page-sub">Perbarui identitas pasien tanpa menghapus riwayat transaksi.</p></div></div>
<div class="card">@include('cashier.patients.form',['action'=>route('cashier.patients.update',$patient),'method'=>'PUT','patient'=>$patient])</div>
@endsection
