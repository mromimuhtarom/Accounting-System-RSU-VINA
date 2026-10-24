@extends('layouts.app')

@section('content')
<div class="card">
    <h2>Laporan Pemasukan</h2>
    @include('reports.partials_date_filter')
    <h3>Total: Rp {{ number_format($total, 0, ',', '.') }}</h3>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Keterangan</th>
                    <th>N.Dokter</th>
                    <th>HD</th>
                    <th>RS</th>
                    <th>Input Oleh</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r->transaction_date->format('d-m-Y') }}</td>
                    <td>{{ $r->transaction_no }}</td>
                    <td><strong>{{ $r->account?->code ?? $r->income_code ?? '-' }}</strong>@if($r->account)<div class="muted">{{ $r->account->name }}</div>@endif</td>
                    <td>{{ $r->notes ?: ($r->patient_name ?: '-') }}</td>
                    <td>{{ $r->doctor_name ?: '-' }}</td>
                    <td>{{ $r->hd ?: '-' }}</td>
                    <td>{{ $r->rs ?: '-' }}</td>
                    <td>{{ $r->cashier?->name }}</td>
                    <td class="money">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Belum ada data pemasukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}
</div>
@endsection
