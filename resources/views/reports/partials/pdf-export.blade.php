<button type="button" class="btn" id="export-report-pdf"
    data-filename="Laporan_{{ $pdfKind }}_{{ $pdfPeriod }}.pdf"
    data-landscape="{{ $pdfKind === 'Tahunan' ? '1' : '0' }}"
    data-period="{{ $pdfPeriod }}"
    data-empty="{{ $income->every(fn ($group) => $group['details']->isEmpty()) && $expense->every(fn ($group) => $group['details']->isEmpty()) ? '1' : '0' }}"
    data-hospital="{{ config('accounting.hospital_name', 'RS. Vina Estetica') }}"
    data-logo="{{ asset('public/images/vina-estetica-logo.png') }}">Export PDF</button>
<span id="report-pdf-status" role="status" aria-live="polite"></span>
@push('scripts')
<script src="{{ asset('public/js/vendor/html2pdf.bundle.min.js') }}" defer></script>
<script src="{{ asset('public/js/report-pdf.js') }}?v=20261029-assets" defer></script>
@endpush
