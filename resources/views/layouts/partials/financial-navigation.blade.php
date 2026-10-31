@if($showDashboard ?? true)<a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="menu-icon" aria-hidden="true">⌂</span> Dashboard</a>@endif
@can('viewAny',\App\Models\OpeningBalance::class)
<details class="bookkeeping-menu" @if(request()->routeIs('reports.financial-*','opening-balances.*','accounting.workspace','accounting.entries.*','accounting.analysis')) open @endif>
    <summary>Pembukuan</summary>
    <div class="bookkeeping-links">
        <a href="{{ route('accounting.workspace') }}" class="{{ request()->routeIs('accounting.workspace','accounting.entries.*') ? 'active' : '' }}">Ruang Kerja Accounting</a>
        <a href="{{ route('accounting.analysis') }}" class="{{ request()->routeIs('accounting.analysis') ? 'active' : '' }}">Analisis Saldo Akun</a>
        <a href="{{ route('reports.financial-position') }}" class="{{ request()->routeIs('reports.financial-position','reports.financial-ledger') ? 'active' : '' }}">Neraca Aset &amp; Pasiva</a>
        <a href="{{ route('reports.financial-trial-balance') }}" class="{{ request()->routeIs('reports.financial-trial-balance') ? 'active' : '' }}">Neraca Saldo</a>
    </div>
</details>
@endcan
@can('viewAny',\App\Models\Asset::class)
<a class="{{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}">Inventaris Barang</a>
@endcan
