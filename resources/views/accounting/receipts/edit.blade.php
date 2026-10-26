@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><h1 class="page-title">Edit Penerimaan</h1><p class="page-sub">Periksa data penerimaan sebelum menyimpan perubahan.</p></div>
    <a class="btn" href="{{ route('accounting.receipts.index') }}">Lihat Semua Penerimaan</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('accounting.receipts.update', $receipt) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="field"><label for="receipt-number">Nomor transaksi</label><input id="receipt-number" value="{{ $receipt->transaction_no }}" disabled></div>
            <div class="field"><label for="receipt-date">Tanggal</label><input id="receipt-date" type="date" name="transaction_date" value="{{ old('transaction_date', $receipt->transaction_date->format('Y-m-d')) }}" required></div>
            <div class="field">
                <label for="receipt-account">Kode / Chart of Accounts</label>
                <select id="receipt-account" name="chart_account_id" required>
                    <option value="">Pilih akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('chart_account_id', $receipt->chart_account_id) === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="receipt-amount">Jumlah penerimaan</label><input id="receipt-amount" type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount', $receipt->amount) }}" required></div>
            <div class="field" style="grid-column:1/-1"><label for="receipt-notes">Keterangan (termasuk nama pasien jika ada)</label><textarea id="receipt-notes" name="notes" maxlength="1000" required>{{ old('notes', $receipt->notes) }}</textarea></div>
            <div class="field"><label for="receipt-doctor">Nama dokter</label><input id="receipt-doctor" name="doctor_name" value="{{ old('doctor_name', $receipt->doctor_name) }}"></div>
            <div class="field"><label for="receipt-hd">HD (opsional)</label><input id="receipt-hd" name="hd" value="{{ old('hd', $receipt->hd) }}"></div>
            <div class="field"><label for="receipt-rs">RS (opsional)</label><input id="receipt-rs" name="rs" value="{{ old('rs', $receipt->rs) }}"></div>
            <div class="field"><label for="receipt-status">Status</label><select id="receipt-status" name="status" required><option value="ACTIVE" @selected(old('status', $receipt->status) === 'ACTIVE')>Aktif</option><option value="CANCELLED" @selected(old('status', $receipt->status) === 'CANCELLED')>Dibatalkan</option></select></div>
        </div>
        <div class="actions" style="margin-top:18px"><button class="btn primary" type="submit">Simpan Penerimaan</button><a class="btn" href="{{ route('accounting.receipts.index') }}">Batal</a></div>
    </form>
</div>
@endsection
