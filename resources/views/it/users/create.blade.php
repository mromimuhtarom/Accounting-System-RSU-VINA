@extends('layouts.app')
@section('content')<div class="page-head"><div><h1 class="page-title">Buat Akun</h1><p class="page-sub">Tambahkan akun pengguna untuk sistem Accounting.</p></div></div><div class="card">@include('it.users.form',['action'=>route('it.users.store'),'method'=>'POST','managedUser'=>null])</div>@endsection
