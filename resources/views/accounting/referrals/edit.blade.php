@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Edit Pengeluaran Referal</h1><p class="page-sub">Perubahan hanya diperbolehkan sebelum referal dibuatkan jurnal.</p></div></div>
<div class="card">@include('accounting.referrals.form',['action'=>route('accounting.referrals.update',$referral),'method'=>'PUT','referral'=>$referral])</div>
@endsection
