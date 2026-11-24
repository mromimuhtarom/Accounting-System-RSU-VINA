@extends('layouts.app')

@section('content')
@php
    $masterRole = strtoupper(trim((string) (auth()->user()?->accountingRole() ?? '')));
    $canManageMaster = $type === 'accounts'
        ? in_array($masterRole, ['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING', 'KASIR'], true)
        : (in_array($masterRole, ['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'KASIR'], true)
           || ($masterRole === 'ASURANSI' && $type === 'guarantors'));
    $form = $editingItem ?? null;
    $coaFormType = old('type', $form?->type ?? $coaTypeFilter ?? 'ASSET');
    $coaFormGroup = match ($coaFormType) {
        'REVENUE' => 'INCOME',
        'EXPENSE' => 'EXPENSE',
        default => null,
    };
    $coaNormalBalance = old('normal_balance', $form?->normal_balance ?? ($coaFormType === 'REVENUE' ? 'CREDIT' : 'DEBIT'));
    $coaFilters = $type === 'accounts' ? request()->only(['group', 'account_type', 'q']) : [];
@endphp

<div class="card">
    <h2>Master Data</h2>
    <div class="nav master-nav">
        @foreach([
            'accounts' => 'COA',
            'units' => 'Unit Pelayanan',
            'guarantors' => 'Penjamin',
            'cash-banks' => 'Kas/Bank',
            'financial-groups' => 'Group Laporan',
        ] as $k => $v)
            <a class="{{ $type === $k ? 'active' : '' }}" href="{{ route('accounting.master.index', $k) }}">{{ $v }}</a>
        @endforeach
    </div>
</div>

@if($type === 'accounts')
<div class="card actions coa-filter">
    @foreach(['' => 'Semua COA', 'REVENUE' => 'COA Pemasukan', 'EXPENSE' => 'COA Pengeluaran'] as $accountType => $label)
        <a class="btn {{ request()->query('account_type', '') === $accountType && ($accountType !== '' || !request()->filled('group')) ? 'primary' : '' }}"
           href="{{ route('accounting.master.index', ['accounts', 'account_type' => $accountType ?: null, 'group' => $accountType === '' ? null : request()->query('group'), 'q' => $search === '' ? null : $search]) }}">{{ $label }}</a>
    @endforeach
    @foreach(['general' => 'COA Umum', 'referral' => 'COA Referal'] as $group => $label)
        <a class="btn {{ request()->query('group', '') === $group ? 'primary' : '' }}"
           href="{{ route('accounting.master.index', ['accounts', 'group' => request()->query('group') === $group ? null : $group, 'account_type' => request()->query('account_type'), 'q' => $search === '' ? null : $search]) }}">{{ $label }}</a>
    @endforeach
</div>
@endif
@if($canManageMaster)
<div class="card">
    <h3>{{ $form ? 'Edit Data' : 'Tambah Data' }}</h3>
    <form method="POST" action="{{ $form ? route('accounting.master.update', [$type, $form->id]) : route('accounting.master.store', $type) }}">
        @csrf
        @if($form) @method('PUT') @endif
        <div class="form-grid">
            <div class="field">
                <label>Code</label>
                <input name="code" value="{{ old('code', $form?->code) }}" required>
            </div>
            <div class="field">
                <label>Nama</label>
                <input name="name" value="{{ old('name', $form?->name) }}" required>
            </div>

            @if($type === 'accounts')
                <div class="field"><label>Kelompok COA</label><select name="is_referral"><option value="0" @selected(!old('is_referral',$form?->is_referral))>Umum</option><option value="1" @selected(old('is_referral',$form?->is_referral))>Referal</option></select></div>
                <div class="field"><label for="coa-type">Jenis COA</label><select id="coa-type" name="type">@foreach(['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE'] as $x)<option value="{{ $x }}" @selected($coaFormType === $x)>{{ $x }}</option>@endforeach</select></div>
                <div class="field">
                    <label for="coa-financial-group">Grup Laporan</label>
                    <select id="coa-financial-group" name="financial_group_id">
                        <option value="">Tanpa grup laporan</option>
                        @foreach(['INCOME' => 'Pemasukan', 'EXPENSE' => 'Pengeluaran'] as $groupType => $groupLabel)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach($financialGroups->get($groupType, []) as $group)
                                    <option value="{{ $group->id }}" data-group-type="{{ $groupType }}" @disabled($coaFormGroup !== $groupType) @if($coaFormGroup !== $groupType) hidden @endif @selected($coaFormGroup === $groupType && (string) old('financial_group_id', $form?->financial_group_id) === (string) $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('financial_group_id') <small>{{ $message }}</small> @enderror
                </div>
                <div class="field"><label>Normal Balance</label><select name="normal_balance"><option value="DEBIT" @selected($coaNormalBalance === 'DEBIT')>DEBIT</option><option value="CREDIT" @selected($coaNormalBalance === 'CREDIT')>CREDIT</option></select></div>
                <div class="field"><label>Parent</label><select name="parent_id"><option value="">-</option>@foreach($accounts->where('id','!=',$form?->id) as $a)<option value="{{ $a->id }}" @selected((string)old('parent_id',$form?->parent_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'units')
                <div class="field"><label>Revenue Account</label><select name="revenue_account_id"><option value="">-</option>@foreach($accounts->where('type','REVENUE') as $a)<option value="{{ $a->id }}" @selected((string)old('revenue_account_id',$form?->revenue_account_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'guarantors')
                <div class="field"><label>Category</label><select name="category">@foreach(['SELF_PAY'=>'Umum / Pribadi','BPJS'=>'BPJS Kesehatan','PRIVATE_INSURANCE'=>'Asuransi Swasta','CORPORATE'=>'Corporate','OTHER'=>'Lainnya'] as $k=>$v)<option value="{{ $k }}" @selected(old('category',$form?->category)===$k)>{{ $v }}</option>@endforeach</select></div>
                <div class="field"><label>BPJS?</label><select name="is_bpjs"><option value="0" @selected((string)old('is_bpjs',(int)($form?->is_bpjs ?? 0))==='0')>Tidak</option><option value="1" @selected((string)old('is_bpjs',(int)($form?->is_bpjs ?? 0))==='1')>Ya</option></select></div>
            @endif

            @if($type === 'cash-banks')
                <div class="field"><label>Type</label><select name="type"><option value="CASH" @selected(old('type',$form?->type)==='CASH')>CASH</option><option value="BANK" @selected(old('type',$form?->type)==='BANK')>BANK</option></select></div>
                <div class="field"><label>Bank Name</label><input name="bank_name" value="{{ old('bank_name',$form?->bank_name) }}"></div>
                <div class="field"><label>No Rekening</label><input name="account_number" value="{{ old('account_number',$form?->account_number) }}"></div>
                <div class="field"><label>COA</label><select name="chart_account_id">@foreach($accounts as $a)<option value="{{ $a->id }}" @selected((string)old('chart_account_id',$form?->chart_account_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'financial-groups')
                <div class="field"><label>Jenis Group</label><select name="type"><option value="INCOME" @selected(old('type',$form?->type)==='INCOME')>Pemasukan</option><option value="EXPENSE" @selected(old('type',$form?->type)==='EXPENSE')>Pengeluaran</option></select></div>
                <div class="field"><label>Urutan</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order',$form?->sort_order ?? 10) }}" required></div>
            @endif

            <div class="field"><label>Status</label><select name="is_active"><option value="1" @selected((string)old('is_active',(int)($form?->is_active ?? 1))==='1')>Active</option><option value="0" @selected((string)old('is_active',(int)($form?->is_active ?? 1))==='0')>Inactive</option></select></div>
        </div>
        <div class="actions">
            <button class="btn primary">{{ $form ? 'Simpan Perubahan' : 'Tambah' }}</button>
            @if($form)<a class="btn" href="{{ route('accounting.master.index', [$type] + $coaFilters) }}">Batal</a>@endif
        </div>
    </form>
</div>
@endif

@if($type === 'accounts' && $canManageMaster)
<script>
    (() => {
        const type = document.getElementById('coa-type');
        const group = document.getElementById('coa-financial-group');
        const update = () => {
            const allowed = { REVENUE: 'INCOME', EXPENSE: 'EXPENSE' }[type.value];
            for (const option of group.options) {
                if (option.value) option.disabled = option.hidden = option.dataset.groupType !== allowed;
            }
            if (group.selectedOptions[0]?.disabled) group.value = '';
            group.dispatchEvent(new Event('change'));
        };
        type.addEventListener('change', update);
    })();
</script>
@endif

<div class="card">
    @if($type === 'accounts')
        <form method="GET" action="{{ route('accounting.master.index', $type) }}">
            @if($type === 'accounts' && request()->filled('group'))
                <input type="hidden" name="group" value="{{ request()->query('group') }}">
            @endif
            @if($type === 'accounts' && in_array(request()->query('account_type'), ['REVENUE', 'EXPENSE'], true))
                <input type="hidden" name="account_type" value="{{ request()->query('account_type') }}">
            @endif
            <div class="field">
                <label for="master-search">Cari COA</label>
                <input id="master-search" type="search" name="q" value="{{ $search }}" placeholder="Kode atau nama akun">
            </div>
            <div class="actions">
                <button type="submit" class="btn primary">Cari</button>
                @if($search !== '')
                    <a class="btn" href="{{ route('accounting.master.index', [$type, 'group' => $type === 'accounts' ? request()->query('group') : null, 'account_type' => $type === 'accounts' ? request()->query('account_type') : null]) }}">Hapus Pencarian</a>
                @endif
            </div>
        </form>
    @endif
    <div class="table-responsive">
        <table>
            <thead><tr><th>Code</th><th>Nama</th>@if($type === 'financial-groups')<th>Jenis</th><th>Urutan</th>@endif @if($type === 'accounts')<th>Group Laporan</th>@endif <th>Status</th>@if($canManageMaster)<th>Aksi</th>@endif</tr></thead>
            <tbody>
                @forelse($items as $x)
                    <tr>
                        <td>{{ $x->code }}</td><td>{{ $x->name }} @if($type === 'accounts' && $x->is_referral)<span class="badge">Referal</span>@endif</td>
                        @if($type === 'financial-groups')<td>{{ $x->type === 'INCOME' ? 'Pemasukan' : 'Pengeluaran' }}</td><td>{{ $x->sort_order }}</td>@endif
                        @if($type === 'accounts')<td>{{ $x->financialGroup?->name ?? '-' }}</td>@endif
                        <td>{{ $x->is_active ? 'Active' : 'Inactive' }}</td>
                        @if($canManageMaster)
                            <td><div class="actions">
                                <a class="btn" href="{{ route('accounting.master.index', [$type, 'edit' => $x->id] + $coaFilters) }}">Edit</a>
                                @if($type === 'accounts' && in_array((int)$x->id, $lockedCoaIds ?? [], true))
                                    <button type="button" class="btn" disabled title="COA masih digunakan dan tidak boleh dihapus">Tidak Bisa Dihapus</button>
                                @else
                                    <form method="POST" action="{{ route('accounting.master.destroy',[$type,$x->id]) }}" onsubmit="return confirm('Hapus data {{ addslashes($x->name) }}? Data hanya dapat dihapus jika belum digunakan oleh data lain.')">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>
                                @endif
                            </div></td>
                        @endif
                    </tr>
                @empty
                    @if($type === 'accounts')
                        <tr><td colspan="{{ $canManageMaster ? 5 : 4 }}">COA tidak ditemukan.</td></tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $items->links() }}
</div>
@endsection
