@extends('layouts.app')
@section('content')<div class="page-head"><div><h1 class="page-title">Edit Akun</h1><p class="page-sub">Ubah identitas, password, role, atau nonaktifkan akun.</p></div></div><div class="card">@include('it.users.form',['action'=>route('it.users.update',$managedUser),'method'=>'PUT','managedUser'=>$managedUser])</div>@endsection
