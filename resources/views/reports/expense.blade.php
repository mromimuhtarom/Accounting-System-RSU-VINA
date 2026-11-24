@extends('layouts.app')

@section('content')
<div class="card">
    <h2>Laporan Pengeluaran</h2>
    @include('reports.partials_date_filter')
    <h3>Total Rp {{ number_format($total, 0, ',', '.') }}</h3>
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>No</th><th>COA Pengeluaran</th><th>Grup Laporan</th><th>Keterangan</th><th>Nominal</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($group = $row->account?->financialGroup)
                <tr>
                    <td>{{ $row->expense_date->format('d-m-Y') }}</td>
                    <td>{{ $row->expense_no }}</td>
                    <td>{{ $row->account?->code }} {{ $row->account?->name }}</td>
                    <td>{{ $group?->type === 'EXPENSE' && $group->is_active ? $group->name : 'Pengeluaran tanpa grup laporan' }}</td>
                    <td>{{ $row->description }}</td>
                    <td class="money">Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Belum ada data pengeluaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links() }}
</div>
@endsection
