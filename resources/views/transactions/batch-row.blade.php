<section class="card" data-batch-row>
<div class="actions"><h3 style="flex:1" data-batch-title>Transaksi {{ is_numeric($rowIndex) ? (int)$rowIndex + 1 : '' }}</h3><button type="button" class="btn danger" data-remove-batch-row>Hapus Baris</button></div>
<div class="form-grid">
@foreach($fields as $field=>$definition)
@php
    [$label,$type,$required] = $definition;
    $value = $rowData[$field] ?? ($type === 'date' ? now()->format('Y-m-d') : ($field === 'payment_method' ? 'CASH' : ''));
    if (!is_scalar($value)) $value = '';
    $id = 'batch-'.$rowIndex.'-'.$field;
    $errorKey = 'rows.'.$rowIndex.'.'.$field;
@endphp
<div class="field" @if($type==='textarea')style="grid-column:1/-1"@endif>
<label for="{{ $id }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
@if($type==='select')
<select id="{{ $id }}" name="rows[{{ $rowIndex }}][{{ $field }}]" @required($required) @if($errors->has($errorKey))aria-invalid="true"@endif><option value="">Pilih {{ $label }}</option>@foreach($definition[3] as $option=>$text)<option value="{{ $option }}" @selected((string)$value===(string)$option)>{{ $text }}</option>@endforeach</select>
@elseif($type==='textarea')
<textarea id="{{ $id }}" name="rows[{{ $rowIndex }}][{{ $field }}]" @required($required)>{{ $value }}</textarea>
@else
<input id="{{ $id }}" type="{{ $type }}" name="rows[{{ $rowIndex }}][{{ $field }}]" @if($type!=='file')value="{{ $value }}"@endif @required($required) @if($type==='number')min="0.01" step="0.01" inputmode="decimal"@endif @if($type==='file' && $isReferral)accept=".jpg,.jpeg,.png,.webp,.pdf"@endif>
@endif
@error($errorKey)<span class="text-danger" role="alert">{{ $message }}</span>@enderror
</div>
@endforeach
</div>
</section>
