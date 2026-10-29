@extends('layouts.app')

@section('content')
<div class="card actions"><a class="btn primary" href="{{ route('accounting.receipts.create', ['multiple'=>1]) }}">Input Banyak Sekaligus</a></div>
<div class="page-head">
    <div>
        <h1 class="page-title">Input Pemasukan</h1>
        <p class="page-sub">Format input mengikuti buku pemasukan RS. Vina Estetica. Kode dipilih dari Chart of Accounts.</p>
    </div>
    <a class="btn" href="{{ route('accounting.receipts.index') }}">Lihat Pemasukan</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('accounting.receipts.store') }}">
        @csrf

        <div class="form-grid">
            <div class="field">
                <label>Tanggal</label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
            </div>

            <div class="field">
                <label>Kode / Chart of Account</label>
                <select name="chart_account_id" required>
                    <option value="">- Pilih Kode COA -</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('chart_account_id') === (string) $account->id)>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Sumber / Penjamin</label>

                <select name="guarantor_id">
                    <option value="">
                        - Tanpa Penjamin / Pendapatan Langsung -
                    </option>

                    @foreach($guarantors as $guarantor)
                        <option
                            value="{{ $guarantor->id }}"
                            @selected(
                                (string) old('guarantor_id')
                                === (string) $guarantor->id
                            )
                        >
                            {{ $guarantor->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Jenis Pelayanan</label>

                <select name="service_type">
                    <option value="">
                        - Tidak Ada / Tidak Diperlukan -
                    </option>

                    <option value="RAWAT_JALAN"
                        @selected(old('service_type') === 'RAWAT_JALAN')
                    >
                        Rawat Jalan
                    </option>

                    <option value="RAWAT_INAP"
                        @selected(old('service_type') === 'RAWAT_INAP')
                    >
                        Rawat Inap
                    </option>

                    <option value="MCU"
                        @selected(old('service_type') === 'MCU')
                    >
                        MCU
                    </option>

                    <option value="LAINNYA"
                        @selected(old('service_type') === 'LAINNYA')
                    >
                        Lainnya
                    </option>
                </select>
            </div>

            <div class="field" style="grid-column:1/-1">
                <label>Keterangan</label>
                <textarea name="notes" required placeholder="Contoh: Poli IGD a/n ...">{{ old('notes') }}</textarea>
            </div>

            <div class="field">
                <label>N.Dokter</label>
                <input name="doctor_name" value="{{ old('doctor_name') }}" placeholder="Nama dokter, bila ada">
            </div>

            <div class="field">
                <label>HD</label>
                <input name="hd" value="{{ old('hd') }}" placeholder="Opsional">
            </div>

            <div class="field">
                <label>RS</label>
                <input name="rs" value="{{ old('rs') }}" placeholder="Opsional">
            </div>

            <div class="field">
                <label>Jumlah</label>
                <input type="number" min="1" step="0.01" name="amount" value="{{ old('amount') }}" required placeholder="0">
            </div>
        </div>

        <div style="margin-top:18px">
            <button class="btn primary" type="submit">Simpan Pemasukan</button>
        </div>
    </form>
</div>
@endsection
