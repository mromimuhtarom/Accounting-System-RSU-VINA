@extends('layouts.app')

@section('content')
<div class="page-head">
    <a class="btn" href="{{ $isInsurance ? route('insurance.receipts.excel-import') : route('cashier.excel-import', ['module'=>'receipts']) }}">Import Excel</a>
    <div>
        <h1 class="page-title">{{ $isInsurance ? 'Penerimaan Asuransi' : 'Semua Data Penerimaan' }}</h1>
        <p class="page-sub">{{ $isInsurance ? 'Menampilkan penerimaan BPJS, asuransi, dan penjamin sesuai kategori modul Asuransi.' : 'Kasir dapat melihat seluruh penerimaan.' }} Edit dan hapus hanya tersedia untuk transaksi milik sendiri yang belum dijurnal.</p>
    </div>
    <a class="btn primary" href="{{ route($routePrefix . '.create') }}">＋ Input Pemasukan</a>
    @unless($isInsurance)
    <a class="btn" href="{{ route('excel.receipts', request()->except('page')) }}" style="background:#e8f6ed;color:#087344;border:1px solid #a3d8bb;font-weight:700">↓ Export Excel Sesuai Filter</a>
    @endunless
</div>

<div class="card">
    <form class="form-grid" method="GET" action="{{ route($routePrefix . '.index') }}">
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
            <label>Petugas Kasir</label>
            <select name="cashier_id" class="js-select2" data-placeholder="Cari petugas kasir...">
                <option value="">Semua Petugas</option>
                @foreach($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected((string) request('cashier_id') === (string) $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Cari</label>
            <input name="search" value="{{ request('search') }}" placeholder="No transaksi, kode COA, keterangan, dokter">
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
                    <th>Kode</th>
                    <th>Keterangan</th>
                    <th>N.Dokter</th>
                    <th>HD</th>
                    <th>RS</th>
                    <th>Jumlah</th>
                    <th>Petugas Kasir</th>
                    <th>Status Jurnal</th>
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
                    <td class="money">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                    <td>{{ $r->cashier?->name ?? 'Tidak diketahui' }}</td>
                    <td>
                        @if($r->journal)
                            <span class="badge">Sudah Dijurnal</span>
                        @else
                            <span class="badge" style="background:#ecfdf3;color:#15803d">Belum Dijurnal</span>
                        @endif
                    </td>
                    <td>
                        @if(($isInsurance ? (int)$r->cashier_id === (int)auth()->id() : auth()->user()->canManageTransaction($r->cashier_id)) && !$r->journal && !in_array($r->id, $bpjsLinkedIds))
                            <div class="actions">
                                <a class="btn" href="{{ route($routePrefix . '.edit', $r) }}">Edit</a>

                                @if(!$r->referralExpense)
                                    <form method="POST" action="{{ route($routePrefix . '.destroy', $r) }}" onsubmit="return confirm('Hapus pemasukan {{ $r->transaction_no }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                @endif

                            </div>
                        @else
                            <span class="muted">{{ $r->journal ? 'Sudah dijurnal' : (in_array($r->id, $bpjsLinkedIds) ? 'Terkait pelunasan BPJS' : 'Lihat saja') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Tidak ada penerimaan untuk filter yang dipilih.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $receipts->links() }}</div>
</div>
@endsection
