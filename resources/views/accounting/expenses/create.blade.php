@extends('layouts.app')
@section('content')
<div class="card actions"><a class="btn primary" href="{{ route($routePrefix . '.create', ['multiple'=>1]) }}">Input Banyak Sekaligus</a><a class="btn" href="{{ route($routePrefix . '.create', ['multiple'=>1, 'grouped'=>1]) }}">Satu Kategori, Banyak Rincian</a></div><div class="card"><h2>Input Pengeluaran</h2>@include('accounting.expenses.form',['action'=>route($routePrefix . '.store'),'method'=>'POST','expense'=>null])</div>@endsection
