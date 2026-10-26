<style>
.expense-form{max-width:960px;font-size:17px;line-height:1.6;color:#203c39}
.expense-form .expense-guide{margin:0 0 28px;padding:18px 20px;background:#eef7f3;border-left:4px solid #16704f;border-radius:8px}
.expense-form .expense-guide p{margin:4px 0 0}
.expense-form fieldset{min-width:0;margin:0 0 28px;padding:22px;border:1px solid #bdcec7;border-radius:14px}
.expense-form legend{padding:0 8px;font-size:20px;font-weight:750;color:#174e3c}
.expense-form .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
.expense-form .field{min-width:0}
.expense-form .expense-wide{grid-column:1/-1}
.expense-form .field label{font-size:17px;color:#203c39;margin-bottom:9px;line-height:1.5}
.expense-form .expense-optional{font-size:15px;font-weight:400;color:#52635e}
.expense-form .field input,.expense-form .field select,.expense-form .field textarea,.expense-form .ss-control,.expense-form .ss-search,.expense-form .ss-option{min-height:52px;font-size:17px;line-height:1.5;padding:12px 14px;color:#203c39;border-color:#849e93}
.expense-form .field textarea{min-height:130px}
.expense-form .ss-control{padding-right:40px}
.expense-form .ss-value{white-space:normal;overflow-wrap:anywhere}
.expense-form .ss-search{font-size:17px!important;min-height:52px;padding-right:34px!important}
.expense-form .searchable-select .ss-panel{min-width:0!important;max-width:100%}
.expense-form .field input[type=file]{max-width:100%;font-size:16px}
.expense-form input::file-selector-button{padding:8px 12px;margin-right:12px;border:1px solid #849e93;border-radius:6px;background:#eef7f3;color:#203c39;font:inherit;cursor:pointer}
.expense-form .field input:focus-visible,.expense-form .field select:focus-visible,.expense-form .field textarea:focus-visible,.expense-form button:focus-visible{outline:3px solid #176baf;outline-offset:3px;box-shadow:none}
.expense-form .expense-help{display:block;margin:8px 0 0;font-size:16px;color:#52635e;overflow-wrap:anywhere}
.expense-form .expense-error{display:block;margin-top:8px;font-size:16px;font-weight:650;color:#a32020}
.expense-form [aria-invalid=true]{border:2px solid #a32020}
.expense-form .expense-footer{display:flex;align-items:center;justify-content:space-between;gap:20px;padding-top:8px}
.expense-form .expense-footer p{margin:0;color:#52635e;font-size:16px}
.expense-form .expense-submit{min-height:56px;padding:14px 28px;font-size:18px;background:#176348;border:1px solid #176348;color:#fff;flex-shrink:0}
.expense-form .expense-submit:hover{background:#104c36;transform:none}
@media(max-width:700px){.expense-form .form-grid{grid-template-columns:minmax(0,1fr);gap:22px}.expense-form fieldset{padding:16px 12px}.expense-form legend{font-size:19px}.expense-form .expense-guide{padding:16px}.expense-form .expense-footer{flex-direction:column;align-items:stretch}.expense-form .expense-submit{width:100%;white-space:normal}}
</style>

<form class="expense-form" method="POST" enctype="multipart/form-data" action="{{ $action }}">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif
    <div class="expense-guide">
        <strong>Catat pengeluaran dengan mudah</strong>
        <p>Isi data dari atas ke bawah, lalu tekan <strong>Simpan Pengeluaran</strong>. Kolom bertanda “opsional” boleh dikosongkan.</p>
    </div>
    <fieldset>
        <legend>1. Data pengeluaran</legend>
        <div class="form-grid">
            <div class="field">
                <label for="expense-date">Tanggal pengeluaran</label>
                <input id="expense-date" type="date" name="expense_date" value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required aria-describedby="expense-date-help">
                <small class="expense-help" id="expense-date-help">Pilih tanggal saat pengeluaran terjadi.</small>
                @error('expense_date') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="expense-category">Kategori pengeluaran</label>
                <select id="expense-category" name="expense_category_id" required aria-describedby="expense-category-help">
                    @foreach($categories as $x)
                        <option value="{{ $x->id }}" @selected(old('expense_category_id', $expense?->expense_category_id) == $x->id)>{{ $x->name }} — {{ $x->expenseAccount?->code }}</option>
                    @endforeach
                </select>
                <small class="expense-help" id="expense-category-help">Pilih jenis pengeluaran yang sesuai.</small>
                @error('expense_category_id') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
            <div class="field expense-wide">
                <label for="expense-description">Keterangan pengeluaran</label>
                <textarea id="expense-description" name="description" required aria-describedby="expense-description-help">{{ old('description', $expense?->description) }}</textarea>
                <small class="expense-help" id="expense-description-help">Tuliskan keperluannya. Contoh: Pembelian alat tulis untuk ruang administrasi.</small>
                @error('description') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </fieldset>
    <fieldset>
        <legend>2. Jumlah dan pembayaran</legend>
        <div class="form-grid">
            <div class="field expense-wide">
                <label for="expense-amount">Jumlah pengeluaran (Rp)</label>
                <input id="expense-amount" type="number" step="0.01" min="1" inputmode="decimal" name="amount" value="{{ old('amount', $expense?->amount) }}" required aria-describedby="expense-amount-help">
                <small class="expense-help" id="expense-amount-help">Contoh: 150.000 untuk seratus lima puluh ribu rupiah. Gunakan koma jika ada sen, misalnya 150.000,50.</small>
                @error('amount') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="expense-payment">Cara pembayaran</label>
                <select id="expense-payment" name="payment_method">
                    @foreach(['CASH' => 'Tunai (Cash)', 'TRANSFER' => 'Transfer bank', 'QRIS' => 'QRIS', 'CARD' => 'Debit / Kartu'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('payment_method', $expense?->payment_method) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                @error('payment_method') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="expense-account">Sumber dana (Kas / Bank)</label>
                <select id="expense-account" name="cash_bank_account_id" aria-describedby="expense-account-help">
                    @foreach($cashBanks as $x)
                        <option value="{{ $x->id }}" @selected(old('cash_bank_account_id', $expense?->cash_bank_account_id) == $x->id)>{{ $x->name }}</option>
                    @endforeach
                </select>
                <small class="expense-help" id="expense-account-help">Pilih kas atau rekening yang digunakan untuk membayar.</small>
                @error('cash_bank_account_id') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </fieldset>
    <fieldset>
        <legend>3. Bukti pendukung <span class="expense-optional">(opsional)</span></legend>
        <div class="form-grid">
            <div class="field">
                <label for="expense-reference">Nomor referensi <span class="expense-optional">(opsional)</span></label>
                <input id="expense-reference" name="reference_no" value="{{ old('reference_no', $expense?->reference_no) }}" aria-describedby="expense-reference-help">
                <small class="expense-help" id="expense-reference-help">Nomor kuitansi, nota, atau bukti transfer jika ada.</small>
                @error('reference_no') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="expense-attachment">Lampiran bukti <span class="expense-optional">(opsional)</span></label>
                <input id="expense-attachment" type="file" name="attachment" aria-describedby="expense-attachment-help">
                <small class="expense-help" id="expense-attachment-help">Pilih berkas bukti dari perangkat Anda. Ukuran maksimal 5 MB.</small>
                @error('attachment') <span class="expense-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </fieldset>
    <div class="expense-footer">
        <p>Periksa kembali tanggal, jumlah, dan sumber dana sebelum menyimpan.</p>
        <button class="btn primary expense-submit" type="submit">Simpan Pengeluaran</button>
    </div>
</form>
