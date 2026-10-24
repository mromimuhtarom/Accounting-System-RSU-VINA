@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Tambah Pasien</h1><p class="page-sub">Data pasien akan tersedia pada form penerimaan Kasir.</p></div></div>
<div class="card">@include('cashier.patients.form',['action'=>route('cashier.patients.store'),'method'=>'POST','patient'=>null])</div>
@endsection
