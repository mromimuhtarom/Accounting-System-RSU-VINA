@extends('layouts.app')
@section('content')
<div class="card">
    <div class="actions">
        <h2 style="flex:1">Pengeluaran</h2>

        <a class="btn primary" href="{{ route($routePrefix . '.create') }}">Tambah Pengeluaran</a>
        <a class="btn" href="{{ route('excel.expenses', request()->except('page')) }}" style="background:#e8f6ed;color:#087344;border:1px solid #a3d8bb;font-weight:700">↓ Export Excel Sesuai Filter</a>
    </div>
</div>

<div class="card">
    <form class="form-grid" method="GET" action="{{ route($routePrefix . '.index') }}">
        <div class="field"><label>Cari No. Transaksi / Keterangan / Kategori</label><input name="q" value="{{request('q')}}" placeholder="Ketik untuk mencari pengeluaran..."></div><div class="field">
            <label>Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="field">
            <label>Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}">
        </div>
        <div style="align-self:end;display:flex;gap:8px">
            <button class="btn" type="submit">Filter</button>
            <a class="btn" href="{{ route($routePrefix . '.index') }}">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No</th>
                    <th>Jenis</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Kas/Bank</th>
                    <th>Nominal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($expenses as $e)
                <tr>
                    <td>{{ $e->expense_date->format('d-m-Y') }}</td>
                    <td>{{ $e->expense_no }}</td>
                    <td>
                        @if($e->expense_type === 'REFERRAL')
                            <span class="badge">Referal</span>
                        @else
                            <span class="muted">Umum</span>
                        @endif
                    </td>
                    <td>{{ $e->category?->name }}</td>
                    <td>
                        {{ $e->description }}
                        @if($e->expense_type === 'REFERRAL')
                            <div class="muted">
                                Pasien: {{ $e->patient?->name }} · Sesi: {{ $e->receipt?->transaction_no }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $e->cashBankAccount?->name }}</td>
                    <td class="money">Rp {{ number_format($e->amount, 0, ',', '.') }}</td>
                    <td>
                        @if(!$e->journal)
                            <div class="actions">
                                @if($e->expense_type === 'REFERRAL')
                                    @unless($isCashier)
                                        <a class="btn" href="{{ route('accounting.referrals.edit', $e) }}">Edit</a>
                                        <form method="POST" action="{{ route('accounting.referrals.journal', $e) }}">
                                            @csrf
                                            <button class="btn" type="submit">Buat Jurnal</button>
                                        </form>
                                    @endunless
                                @else
                                    <a class="btn" href="{{ route($routePrefix . '.edit', $e) }}">Edit</a>
                                    <form method="POST" action="{{ route($routePrefix . '.destroy', $e) }}" onsubmit="return confirm('Hapus pengeluaran {{ $e->expense_no }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>

                                    @unless($isCashier)
                                        <form method="POST" action="{{ route('accounting.pengeluaran.journal', $e) }}">
                                            @csrf
                                            <button class="btn" type="submit">Buat Jurnal</button>
                                        </form>
                                    @endunless
                                @endif
                            </div>
                        @else
                            <span class="muted">Sudah dijurnal</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">Belum ada transaksi pengeluaran.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $expenses->links() }}
</div>
@endsection
