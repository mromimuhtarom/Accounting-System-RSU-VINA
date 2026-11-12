@php
    $pickerField = $descriptionField ?? 'description';
    $pickerId = $descriptionPickerId ?? 'transaction-description-picker';
    if (!isset($recentDescriptions)) {
        $pickerQuery = $pickerField === 'notes'
            ? \App\Models\Receipt::where('cashier_id', auth()->id())->where('status', 'ACTIVE')
            : \App\Models\Expense::where('created_by', auth()->id())->where('status', 'ACTIVE')
                ->where('expense_type', request()->routeIs('accounting.referrals.*') ? 'REFERRAL' : 'GENERAL');
        if ($pickerField === 'notes' && request()->routeIs('insurance.receipts.*')) $pickerQuery->whereHas('guarantor', fn ($q) => $q->whereIn('category', \App\Models\Guarantor::INSURANCE_CATEGORIES));
        $recentDescriptions = $pickerQuery->latest('id')->limit(300)->pluck($pickerField)->filter()->unique()->values();
    }
@endphp
<label for="{{ $pickerId }}">Pilih keterangan sebelumnya</label>
<select id="{{ $pickerId }}" data-description-picker="{{ $pickerField }}" data-search-placeholder="Cari nama PT / kata kunci keterangan">
    <option value="">Cari / pilih keterangan (atau tulis langsung di bawah)</option>
    @foreach($recentDescriptions as $description)<option value="{{ $description }}">{{ $description }}</option>@endforeach
</select>
<small>Pilihan otomatis mengisi teks keterangan di bawah. Teks tetap bisa diubah.</small>
@once
@push('scripts')<script src="{{ asset('public/js/description-picker.js') }}?v=20261112" defer></script>@endpush
@endonce
