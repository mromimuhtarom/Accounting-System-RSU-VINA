@extends('layouts.app')
@section('content')<div class="card"><h2>Edit Pengeluaran</h2>@include('accounting.expenses.form',['action'=>route($routePrefix . '.update',$expense),'method'=>'PUT','expense'=>$expense])</div>@endsection
