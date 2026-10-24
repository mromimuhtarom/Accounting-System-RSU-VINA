@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Input Pengeluaran Referal</h1><p class="page-sub">Referal otomatis tercatat sebagai kategori pengeluaran Referal.</p></div></div>
<div class="card">@include('accounting.referrals.form',['action'=>route('accounting.referrals.store'),'method'=>'POST','referral'=>null])</div>
@endsection
