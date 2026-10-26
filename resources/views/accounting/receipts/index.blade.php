@extends('layouts.app')

@section('content')
<div class="page-head">
    <a class="btn" href="{{ route('accounting.excel-import', ['module'=>'receipts']) }}">Import Excel</a>
    <div>
        <h1 class="page-title">Pemasukan</h1>
        <p class="page-sub">Kode pemasukan menggunakan Chart of Accounts yang sama dengan Pengeluaran.</p>
    </div>
    <a class="btn primary" href="{{ route('accounting.receipts.create') }}">＋ Input Pemasukan</a>
    <a class="btn" href="{{ route('excel.receipts', request()->except('page')) }}" style="background:#e8f6ed;color:#087344;border:1px solid #a3d8bb;font-weight:700">↓ Export Excel Sesuai Filter</a>
</div>

<div class="card">
    <form class="form-grid" method="GET" action="{{ route('accounting.receipts.index') }}">
        <div class="field">
            <label>Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
        </div>

        <div class="field">
            <label>Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}">
        </div>

        <div class="field">
            <label>Kode / COA</label>
            <select name="account_id" class="js-select2" data-placeholder="Cari kode COA...">
                <option value="">Semua Kode</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}" @selected((string) request('account_id') === (string) $account->id)>
                        {{ $account->code }} - {{ $account->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Cari</label>
            <input name="search" value="{{ request('search') }}" placeholder="No transaksi, kode COA, keterangan, dokter">
        </div>

        <div style="align-self:end;display:flex;gap:8px">
            <button class="btn" type="submit">Filter</button>
            <a class="btn" href="{{ route('accounting.receipts.index') }}">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Keterangan</th>
                    <th>N.Dokter</th>
                    <th>HD</th>
                    <th>RS</th>
                    <th>Input Oleh</th>
                    <th>Jumlah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td>{{ $r->transaction_date->format('d-m-Y') }}</td>
                    <td>
                        <strong>{{ $r->account?->code ?? $r->income_code ?? '-' }}</strong>
                        @if($r->account)<div class="muted">{{ $r->account->name }}</div>@endif
                    </td>
                    <td>
                        {{ $r->notes ?: ($r->patient_name ?: $r->transaction_no) }}
                        <div class="muted">{{ $r->transaction_no }}</div>
                    </td>
                    <td>{{ $r->doctor_name ?: '-' }}</td>
                    <td>{{ $r->hd ?: '-' }}</td>
                    <td>{{ $r->rs ?: '-' }}</td>
                    <td>{{ $r->cashier?->name }}</td>
                    <td class="money">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                    <td>
                        @if(!$r->journal && !in_array($r->id, $bpjsLinkedIds))
                            <div class="actions">
                                <a class="btn" href="{{ route('accounting.receipts.edit', $r) }}">Edit</a>

                                @if(!$r->referralExpense)
                                    <form method="POST" action="{{ route('accounting.receipts.destroy', $r) }}" onsubmit="return confirm('Hapus pemasukan {{ $r->transaction_no }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                @endif

                                @if($r->service_unit_id && ($r->cash_bank_account_id || $r->guarantor_id))
                                    <form method="POST" action="{{ route('accounting.receipts.journal', $r) }}">
                                        @csrf
                                        <button class="btn" type="submit">Buat Jurnal</button>
                                    </form>
                                @else
                                    <span class="muted">Jurnal manual</span>
                                @endif
                            </div>
                        @else
                            <span class="badge">{{ $r->journal ? 'Sudah dijurnal' : 'Terkait pelunasan BPJS' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Belum ada pemasukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $receipts->links() }}</div>
</div>
@endsection
