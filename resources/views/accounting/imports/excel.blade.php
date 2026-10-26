@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">Smart Excel Import — {{ $module === 'receipts' ? 'Pemasukan' : 'Pengeluaran' }}</h1><p class="page-sub">Upload Excel → Auto Mapping → Preview & Validation → Confirm Import → Result</p></div><a class="btn" href="{{ route($returnRoute) }}">Kembali</a></div>
@if($errors->any())<div class="rsv-note rsv-warning">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@if($result)
<div class="card"><h3>Hasil Import</h3><p>Berhasil: <strong>{{ $result['success'] }}</strong> · Gagal validasi: <strong>{{ $result['failed'] }}</strong> · Dilewati / duplikat: <strong>{{ $result['skipped'] }}</strong></p><a class="btn" href="{{ url()->current() }}">Import file lain</a></div>
@else
<div class="card"><form method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="action" value="upload"><div class="form-grid"><div class="field"><label>File Excel (.xlsx / .xls), maksimal 10 MB / 5.000 baris</label><input type="file" name="file" accept=".xlsx,.xls" required></div><div class="field"><label>Nomor baris header (kosong = otomatis)</label><input type="number" name="header_row" min="1" max="30"></div></div><div class="actions"><button class="btn primary">Upload & Auto Mapping</button><a class="btn" href="{{ request()->url() }}?template=1">Unduh Template Excel</a></div></form><p>Worksheet pertama digunakan. Tanggal: YYYY-MM-DD / DD/MM/YYYY; nominal: 150000.50 atau 150.000,50. Master: ID, kode unik, atau nama. Rumus tidak dihitung. Sesi berlaku 30 menit.</p></div>
@if($token)
<form method="POST">@csrf<input type="hidden" name="token" value="{{ $token }}">
<div class="card"><h3>Mapping Kolom</h3><div class="form-grid">@foreach($headers as $column=>$header)<div class="field"><label>Kolom {{ $column + 1 }}: {{ $header ?: '(tanpa header)' }}</label><select name="mapping[{{ $column }}]" onchange="document.getElementById('confirm-import').disabled=true"><option value="">Lewati kolom</option>@foreach($fields as $field=>$label)<option value="{{ $field }}" @selected(($mapping[$column] ?? '') === $field)>{{ explode('|',$label)[0] }} ({{ $field }})</option>@endforeach</select></div>@endforeach</div><button class="btn" name="action" value="preview">Perbarui Preview & Validasi</button></div>
<div class="card"><h3>Preview — {{ count($preview) }} baris</h3><p>Warning perlu diperiksa. Error dan Duplicate tidak disimpan. Pilih master untuk memperbaiki relasi, lalu perbarui preview sebelum konfirmasi.</p><div class="table-wrap"><table><thead><tr><th>Lewati</th><th>Baris Excel</th><th>Data Asli</th><th>Hasil Mapping</th><th>Status / Keterangan</th><th>Koreksi Master</th></tr></thead><tbody>@foreach($preview as $i=>$row)<tr><td><input type="checkbox" name="skip[]" value="{{ $i }}" @checked(in_array((string)$i,array_map('strval',request('skip',[])),true))></td><td>{{ $i + 1 }}</td><td>@foreach($row['raw'] as $column=>$value)<div><strong>{{ $headers[$column] ?? $column }}:</strong> {{ $value }}</div>@endforeach</td><td>@foreach($row['data'] as $field=>$value)<div>{{ $field }}: {{ $value }}</div>@endforeach</td><td><strong>{{ $row['status'] }}</strong>@foreach($row['messages'] as $message)<div>{{ $message }}</div>@endforeach</td><td>@foreach($masters as $field=>$records)<div class="field"><label>{{ explode('|',$fields[$field])[0] }}</label><select name="overrides[{{ $i }}][{{ $field }}]" onchange="document.getElementById('confirm-import').disabled=true"><option value="">Dari Excel</option>@foreach($records as $record)<option value="{{ $record->id }}" @selected((string)($overrides[$i][$field] ?? '') === (string)$record->id)>{{ $record->code }} — {{ $record->name }} (#{{ $record->id }})</option>@endforeach</select></div>@endforeach</td></tr>@endforeach</tbody></table></div><button id="confirm-import" class="btn primary" name="action" value="confirm">Konfirmasi Import Data Valid & Warning</button></div>
</form>
@endif
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const token = document.querySelector('input[name="token"]');
    if (!token) return;
    token.form.addEventListener('submit', function () {
        const overrides = {}, skip = [];
        this.querySelectorAll('select[name^="overrides["]').forEach(function (input) {
            const parts = input.name.match(/^overrides\[(\d+)\]\[([^\]]+)\]$/);
            if (parts && input.value) {
                overrides[parts[1]] = overrides[parts[1]] || {};
                overrides[parts[1]][parts[2]] = input.value;
            }
            input.disabled = true;
        });
        this.querySelectorAll('input[name="skip[]"]').forEach(function (input) {
            if (input.checked) skip.push(input.value);
            input.disabled = true;
        });
        for (const [name, value] of Object.entries({overrides_json: overrides, skip_json: skip})) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = JSON.stringify(value);
            this.appendChild(input);
        }
    });
});
</script>

@endsection
