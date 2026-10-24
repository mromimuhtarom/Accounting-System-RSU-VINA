@extends('layouts.app')
@section('content')<div class="card"><h2>Input Pengeluaran</h2>@include('accounting.expenses.form',['action'=>route('accounting.pengeluaran.store'),'method'=>'POST','expense'=>null])</div>@endsection
