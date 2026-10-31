<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#071f2d">
<link rel="stylesheet" href="{{ asset('public/css/asset-reports.css') }}?v=20261029">
<title>{{ $title ?? 'Accounting System' }} | {{ config('accounting.hospital_name', 'RS. Vina Estetica') }}</title>
<link rel="stylesheet" href="{{ asset('public/css/accounting.css') }}?v=20261025-perf-1">
<link rel="stylesheet" href="{{ asset('public/css/form-controls.css') }}?v=20261025-1">
<link rel="stylesheet" href="{{ asset('public/css/searchable-select.css') }}?v=20261025-1">
<style>
/* RSUVE: tombol sidebar desktop + mobile; tidak mengubah menu atau role. */
.sidebar-toggle-btn{
    display:inline-flex !important;align-items:center;justify-content:center;
    width:40px;height:40px;flex:0 0 40px;border:1px solid #d6e5df;
    background:#fff;color:#163a43;border-radius:11px;cursor:pointer;
    transition:background .2s,border-color .2s,transform .2s;
}
.sidebar-toggle-btn:hover{background:#ebf7f0;border-color:#8fcbb0}
.sidebar-toggle-btn:focus-visible{outline:3px solid #20a36f;outline-offset:2px}
.sidebar-toggle-btn svg{width:20px;height:20px;display:block}
/* Pastikan tombol terlihat di desktop ketika .mobile-toggle disembunyikan oleh CSS asli. */
.top-left{display:flex;align-items:center;gap:12px;min-width:0}
.top-left .search-pill{min-width:0;max-width:100%}
@media (min-width: 1025px){
    .app-shell.sidebar-collapsed{grid-template-columns:0 minmax(0,1fr) !important}
    .app-shell.sidebar-collapsed>.sidebar{
        width:0 !important;min-width:0 !important;max-width:0 !important;
        overflow:hidden !important;visibility:hidden;opacity:0;
        transform:translateX(-100%) !important;pointer-events:none;
        padding-left:0 !important;padding-right:0 !important;border:0 !important;
    }
    .app-shell.sidebar-collapsed>.main{
        width:100% !important;max-width:100% !important;min-width:0 !important;
        margin-left:0 !important;flex:1 1 auto !important;grid-column:2;
    }
    /* Mendukung layout yang mengatur margin sidebar pada elemen .main. */
    body.rsuve-sidebar-collapsed .main{margin-left:0 !important;width:100% !important;max-width:100% !important}
    .app-shell.sidebar-collapsed .content{max-width:100%;min-width:0}
    .app-shell.sidebar-collapsed .topbar{left:0 !important;width:100% !important;max-width:100% !important}
}
@media (max-width: 1024px){
    .sidebar-toggle-btn{position:relative;z-index:2}
}
</style>
<link rel="stylesheet" href="{{ asset('public/css/usability.css') }}?v=20261030-3">
<link rel="stylesheet" href="{{ asset('public/css/system-assistant.css') }}?v=20261030-profile-1">
</head>
<body>
<a class="skip-content" href="#main-content">Lewati menu, langsung ke isi halaman</a>
<div class="page-transition" id="pageTransition" aria-hidden="true">
    <div class="page-transition-inner">
        <div class="transition-logo-wrap"><img src="{{ asset('public/images/vina-estetica-logo.png') }}" alt=""></div>
        <div class="transition-copy">
            <strong>Vina Estetica</strong>
            <span>Accounting System</span>
        </div>
    </div>
</div>
@php($role = strtoupper(trim((string) (auth()->user()?->accountingRole() ?? ''))))
<div class="app-shell">
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <button type="button" class="sidebar-close" data-close-navigation>Tutup menu</button>
        <div class="brand">
            <img src="{{ asset('public/images/vina-estetica-logo.png') }}" alt="Logo RS. Vina Estetica">
            <div>
                <div class="brand-title">{{ config('accounting.hospital_name', 'RS. Vina Estetica') }}</div>
                <div class="brand-sub">Accounting System</div>
            </div>
        </div>

        @include('layouts.partials.primary-navigation')
        <div class="sidebar-footer">
            {{ config('accounting.hospital_name', 'RS. Vina Estetica') }}<br>
            Sistem Informasi Akuntansi
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="top-left">
                <button class="sidebar-toggle-btn" id="sidebarToggleBtn" type="button"
                        onclick="toggleSidebar()" aria-label="Sembunyikan atau tampilkan menu samping"
                        aria-controls="sidebar" aria-expanded="true" title="Sembunyikan / tampilkan menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M9 4v16"></path>
                    </svg>
                    <span>Menu</span>
                </button>
                <span class="nav-guidance">Pilih halaman melalui menu</span>
            </div>
            <div class="top-user">
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U',0,1)) }}</div>
                <div>
                    <div class="user-name">{{ auth()->user()->name ?? '' }}</div>
                    <div class="user-role">{{ $role === 'IT' ? 'IT' : ucfirst(strtolower($role ?? '')) }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-btn" type="submit" title="Keluar dari akun">Keluar</button></form>
            </div>
        </header>

        <section class="content" id="main-content" tabindex="-1">
            <div class="reading-controls" role="group" aria-label="Pengaturan kenyamanan membaca" data-pdf-exclude>
                <strong>Tampilan</strong>
                <button type="button" class="btn" data-reading-size-button="standard" aria-pressed="true">Teks standar</button>
                <button type="button" class="btn" data-reading-size-button="large" aria-pressed="false">Teks besar</button>
                <label for="high-contrast"><input type="checkbox" id="high-contrast">Kontras tinggi</label>
                <button type="button" class="btn" data-reset-reading>Atur ulang tampilan</button>
            </div>
            @include('layouts.partials.navigation-context')
            @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="error" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="error" id="form-errors" role="alert"><strong>Periksa data:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
</div>
<script>
(function () {
    const shell = document.querySelector('.app-shell');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const toggle = document.getElementById('sidebarToggleBtn');
    const desktop = window.matchMedia('(min-width: 1025px)');
    const storageKey = 'rsuve_accounting_sidebar_collapsed';
    if (!shell || !sidebar || !overlay || !toggle) return;

    function syncSidebar() {
        if (desktop.matches) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            const collapsed = localStorage.getItem(storageKey) === '1';
            shell.classList.toggle('sidebar-collapsed', collapsed);
            document.body.classList.toggle('rsuve-sidebar-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.title = collapsed ? 'Tampilkan menu samping' : 'Sembunyikan menu samping';
        } else {
            shell.classList.remove('sidebar-collapsed');
            document.body.classList.remove('rsuve-sidebar-collapsed');
            toggle.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false');
            toggle.title = 'Buka / tutup menu samping';
        }
    }
    window.toggleSidebar = function () {
        if (desktop.matches) {
            const collapsed = !shell.classList.contains('sidebar-collapsed');
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
            syncSidebar();
        } else {
            const open = sidebar.classList.toggle('open');
            overlay.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    };
    if (desktop.addEventListener) desktop.addEventListener('change', syncSidebar);
    else desktop.addListener(syncSidebar);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !desktop.matches && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open'); overlay.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false'); toggle.focus();
        }
    });
    syncSidebar();
})();
</script>
@include('layouts.partials.system-assistant')
<script src="{{ asset('public/js/system-assistant.js') }}?v=20261030-profile-1" defer></script>
<script src="{{ asset('public/js/accounting-ui.js') }}?v=20261025-perf-1"></script>
<script src="{{ asset('public/js/searchable-select.js') }}?v=20261025-1"></script>
@stack('scripts')
<script src="{{ asset('public/js/form-controls.js') }}?v=20261029-readable-money"></script>
<script src="{{ asset('public/js/usability.js') }}?v=20261030-3" defer></script>
<script src="{{ asset('public/js/idle-session.js') }}?v=20261025-1"
    data-activity-url="{{ route('session.activity') }}" data-logout-url="{{ route('logout') }}"
    data-login-url="{{ route('login', ['expired' => 1]) }}"
    data-timeout="{{ (int) config('session.lifetime', 120) * 1000 * 60 }}"
    data-activity-key="rsuve-activity-{{ hash('sha256', session()->getId()) }}"></script>
</body>
</html>
