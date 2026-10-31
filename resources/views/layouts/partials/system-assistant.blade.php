@php($assistantResources=app(\App\Services\SystemAssistantAccess::class)->resources(auth()->user()))
@php($assistantSettings=app(\App\Services\AssistantProfileSettings::class))
@php($assistantProfile=$assistantSettings->current())
@php($assistantPhotoUrl=$assistantSettings->photoUrl($assistantProfile))
@php($assistantAnalyses=app(\App\Services\SystemAssistantAccess::class)->analyses(auth()->user()))
@php($assistantExample=isset($assistantResources['receipts'])?'cara input pemasukan':(isset($assistantResources['assets'])?'cara inventaris':'bantu saya menggunakan halaman ini'))
<div class="system-assistant" data-system-assistant data-name="{{ $assistantProfile->name }}" data-endpoint="{{ route('assistant.ask') }}" data-context="{{ request()->route()?->getName() }}" data-today="{{ now('Asia/Jakarta')->toDateString() }}" data-pdf-exclude>
    <button type="button" class="assistant-launcher" data-assistant-open aria-controls="system-assistant-panel" aria-expanded="false" aria-label="Buka {{ $assistantProfile->name }}" title="{{ $assistantProfile->name }}">@include('layouts.partials.assistant-avatar') <span class="assistant-display-name">{{ $assistantProfile->name }}</span></button>
    <div class="assistant-overlay" data-assistant-overlay hidden></div>
    <section class="assistant-panel" id="system-assistant-panel" role="dialog" aria-labelledby="assistant-title" hidden>
        <header class="assistant-header">@include('layouts.partials.assistant-avatar')<div><h2 id="assistant-title">{{ $assistantProfile->name }}</h2><p>Asisten panduan &amp; analisis sistem</p></div><button type="button" data-assistant-close aria-label="Tutup {{ $assistantProfile->name }}">Tutup</button></header>
        <div class="assistant-tools"><a href="{{ route('assistant.guide') }}" target="_blank" rel="noopener">Panduan PDF</a><button type="button" data-assistant-clear>Bersihkan percakapan</button></div>
        <div class="assistant-messages" role="log" aria-label="Percakapan dengan Sukoco" aria-live="polite" aria-relevant="additions" data-assistant-messages>
            <div class="assistant-message assistant-reply"><strong>{{ $assistantProfile->name }}</strong><p>Halo, saya {{ $assistantProfile->name }}. Saya membantu panduan, pencarian, serta ringkasan laporan dan grafik sesuai hak akses Anda. Analisis menghitung data aplikasi dengan aturan tetap, bukan AI generatif. Saya tidak mengubah atau memposting data.</p></div>
        </div>
        <div class="assistant-compose">
            @if($assistantAnalyses)
            <details class="assistant-search"><summary>Analisis Laporan &amp; Grafik</summary><form data-assistant-analysis>
                <label for="assistant-analysis-type">Yang ingin dianalisis</label><select id="assistant-analysis-type" name="resource">@foreach($assistantAnalyses as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <div class="assistant-dates" data-analysis-dates><div><label for="assistant-analysis-from">Dari</label><input id="assistant-analysis-from" name="from" type="date" value="{{ now('Asia/Jakarta')->startOfMonth()->toDateString() }}" max="{{ now('Asia/Jakarta')->toDateString() }}"></div><div><label for="assistant-analysis-to">Sampai</label><input id="assistant-analysis-to" name="to" type="date" value="{{ now('Asia/Jakarta')->toDateString() }}" max="{{ now('Asia/Jakarta')->toDateString() }}"></div></div>
                <p class="assistant-small">Maksimal 366 hari. Inventaris adalah kondisi terkini; klaim menurut periode bulan. Analisis memakai tanggal; filter kata kunci/akun/penjamin halaman tidak ikut diterapkan.</p><button type="submit">Baca Analisis &amp; Grafik</button><button type="button" data-analysis-context>Analisis Periode Halaman Ini</button>
            </form></details>
            @endif
            <div class="assistant-suggestions" aria-label="Contoh pertanyaan"><button type="button" data-assistant-question="Bantu saya menggunakan halaman ini">Bantuan halaman ini</button>@can('viewAny',\App\Models\OpeningBalance::class)<button type="button" data-assistant-question="Bagaimana agar neraca muncul?">Agar neraca muncul</button><button type="button" data-assistant-question="Total neraca">Ringkasan neraca</button>@endcan</div>
            @if($assistantResources)
            <details class="assistant-search"><summary>Cari Data</summary><form data-assistant-search>
                <label for="assistant-resource">Jenis data</label><select id="assistant-resource" name="resource">@foreach($assistantResources as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <label for="assistant-term">Nama / kode / referensi</label><input id="assistant-term" name="term" maxlength="120" placeholder="Contoh: AST-768 atau nomor bukti">
                <div class="assistant-dates" data-assistant-dates><div><label for="assistant-from">Dari</label><input id="assistant-from" name="from" type="date"></div><div><label for="assistant-to">Sampai</label><input id="assistant-to" name="to" type="date"></div></div>
                <p class="assistant-small" data-assistant-date-note>Tanggal untuk transaksi; pada klaim dipakai sebagai periode bulan, bukan tanggal pencairan.</p><button type="submit">Cari Data</button>
            </form></details>
            @endif
            <form data-assistant-chat><label for="assistant-question">Pertanyaan Anda</label><div class="assistant-send"><textarea id="assistant-question" name="message" rows="2" maxlength="400" required placeholder="Contoh: {{ $assistantExample }}"></textarea><button type="submit">Kirim</button></div></form>
            <p class="assistant-status" role="status" aria-live="polite" data-assistant-status>Enter untuk kirim · Shift+Enter untuk baris baru.</p>
        </div>
    </section>
</div>
