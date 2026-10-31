<div class="navigation-search">
    <label for="navigation-search">Cari menu</label>
    <input type="search" id="navigation-search" placeholder="Contoh: pemasukan, neraca" autocomplete="off" aria-controls="primary-menu">
    <button type="button" data-clear-navigation hidden>Hapus pencarian</button>
    <p data-navigation-results role="status" aria-live="polite" hidden></p>
</div>
<nav class="menu" id="primary-menu" aria-label="Menu utama">
    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
    @if(in_array($role,['ACCOUNTING','KEPALA_KEUANGAN','ADMIN_ACCOUNTING']))
        <div class="menu-label">Transaksi Harian</div>
        <a class="{{ request()->routeIs('accounting.receipts.*') ? 'active' : '' }}" href="{{ route('accounting.receipts.index') }}" data-menu-keywords="pemasukan uang masuk pembayaran">Penerimaan / Uang Masuk</a>
        <a class="{{ request()->routeIs('accounting.pengeluaran.*') ? 'active' : '' }}" href="{{ route('accounting.pengeluaran.index') }}" data-menu-keywords="biaya beban uang keluar">Pengeluaran / Uang Keluar</a>
        <a class="{{ request()->routeIs('accounting.referrals.*') ? 'active' : '' }}" href="{{ route('accounting.referrals.index') }}">Pengeluaran Referal</a>
    @elseif($role==='KASIR')
        <div class="menu-label">Transaksi Harian</div>
        <a class="{{ request()->routeIs('cashier.receipts.index','cashier.receipts.edit') ? 'active' : '' }}" href="{{ route('cashier.receipts.index') }}" data-menu-keywords="pemasukan uang masuk">Penerimaan</a>
        <a class="{{ request()->routeIs('cashier.receipts.create') ? 'active' : '' }}" href="{{ route('cashier.receipts.create') }}">Input Penerimaan</a>
        <a class="{{ request()->routeIs('cashier.expenses.index','cashier.expenses.edit') ? 'active' : '' }}" href="{{ route('cashier.expenses.index') }}" data-menu-keywords="uang keluar">Pengeluaran Umum</a>
        <a class="{{ request()->routeIs('cashier.expenses.create') ? 'active' : '' }}" href="{{ route('cashier.expenses.create') }}">Input Pengeluaran</a>
        <a class="{{ request()->routeIs('accounting.referrals.*') ? 'active' : '' }}" href="{{ route('accounting.referrals.index') }}">Pengeluaran Referal</a>
    @elseif($role==='ASURANSI')
        <div class="menu-label">Transaksi Harian</div>
        <a class="{{ request()->routeIs('insurance.receipts.index','insurance.receipts.edit') ? 'active' : '' }}" href="{{ route('insurance.receipts.index') }}">Penerimaan Asuransi</a>
        <a class="{{ request()->routeIs('insurance.receipts.create') ? 'active' : '' }}" href="{{ route('insurance.receipts.create') }}">Input Penerimaan Asuransi</a>
    @endif
    @include('layouts.partials.financial-navigation',['showDashboard'=>false])
    @if(in_array($role,['ASURANSI','ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN','OWNER','DIREKTUR']))
        <a class="{{ request()->routeIs('insurance.*') && !request()->routeIs('insurance.receipts.*') ? 'active' : '' }}" href="{{ route('insurance.index') }}" data-menu-keywords="klaim piutang penjamin tracking">Tracking Klaim Asuransi</a>
    @endif
    @if(in_array($role,['ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN','ATASAN','FINANCE','DIREKTUR','OWNER']))
        <details class="bookkeeping-menu" @if(request()->routeIs('reports.monthly*','reports.yearly','reports.income','reports.expense')) open @endif>
            <summary>Laporan Transaksi</summary><div class="bookkeeping-links">
            @foreach(['monthly'=>'Laporan Bulanan','yearly'=>'Laporan Tahunan','income'=>'Laporan Penerimaan','expense'=>'Detail Pengeluaran'] as $key=>$label)
                <a class="{{ request()->routeIs('reports.'.$key) ? 'active' : '' }}" href="{{ route('reports.'.$key) }}">{{ $label }}</a>
            @endforeach
            </div>
        </details>
    @endif
    @if(in_array($role,['KASIR','ACCOUNTING','KEPALA_KEUANGAN','ADMIN_ACCOUNTING']))
        <details class="bookkeeping-menu" @if(request()->routeIs('accounting.master.*','accounting.referral-recipients.*')) open @endif>
            <summary>Data Pendukung</summary><div class="bookkeeping-links">
            <a class="{{ request()->routeIs('accounting.referral-recipients.*') ? 'active' : '' }}" href="{{ route('accounting.referral-recipients.index') }}">Penerima Referral</a>
            @foreach(['accounts'=>'Daftar Akun (COA)','cash-banks'=>'Kas & Bank','expense-categories'=>'Kategori Pengeluaran','units'=>'Unit Pelayanan','guarantors'=>'Penjamin','financial-groups'=>'Group Laporan'] as $key=>$label)
                <a class="{{ request()->routeIs('accounting.master.*') && request()->route('type')===$key ? 'active' : '' }}" href="{{ route('accounting.master.index',$key) }}">{{ $label }}</a>
            @endforeach
            </div>
        </details>
    @elseif($role==='ASURANSI')
        <a href="{{ route('accounting.master.index','guarantors') }}">Kelola Penjamin Asuransi</a>
    @endif
    @if($role==='IT')
        <a class="{{ request()->routeIs('it.assistant-profile.*') ? 'active' : '' }}" href="{{ route('it.assistant-profile.edit') }}">Profil Asisten</a>
        <a class="{{ request()->routeIs('it.session-logs.*') ? 'active' : '' }}" href="{{ route('it.session-logs.index') }}">Log Login / Logout</a>
        <a class="{{ request()->routeIs('it.activity-logs.*') ? 'active' : '' }}" href="{{ route('it.activity-logs.index') }}">Log Perubahan Data</a>
        <a class="{{ request()->routeIs('it.users.*') ? 'active' : '' }}" href="{{ route('it.users.index') }}">Manajemen Akun</a>
    @endif
</nav>
