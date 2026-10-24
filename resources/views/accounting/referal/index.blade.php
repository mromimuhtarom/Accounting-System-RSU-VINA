@extends('layouts.app')
@section('content')
<div class="card">
    <div class="actions">
        <h2 style="flex:1">Pengeluaran dari referal</h2>
        <a class="btn primary" href="{{ route('accounting.pengeluaran.create') }}">Tambah Pengeluaran</a>
    </div>
</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>No</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Kas/Bank</th>
                <th>Nominal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $e)
            <tr>
                <td>{{ $e->expense_date->format('d-m-Y') }}</td>
                <td>{{ $e->expense_no }}</td>
                <td>{{ $e->category->name }}</td>
                <td>{{ $e->description }}</td>
                <td>{{ $e->cashBankAccount->name }}</td>
                <td class="money">Rp {{ number_format($e->amount,0,',','.') }}</td>
                <td>
                    <div class="actions">
                        @if(!$e->journal)
                            <a class="btn" href="{{ route('accounting.pengeluaran.edit',$e) }}">Edit</a>
                        @endif
                        @if(!$e->journal)
                            <form method="POST" action="{{ route('accounting.pengeluaran.journal',$e) }}">
                                @csrf
                                <button class="btn">Buat Jurnal</button>
                            </form>
                        @else
                            <span class="muted">Sudah dijurnal</span>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $expenses->links() }}
</div>
@endsection