@extends('layouts.app')

@section('content')
@php
    $masterRole = strtoupper(trim((string) (auth()->user()?->accountingRole() ?? '')));
    $canManageMaster = $type === 'accounts'
        ? in_array($masterRole, ['ACCOUNTING', 'KEPALA_KEUANGAN', 'ADMIN_ACCOUNTING', 'KASIR'], true)
        : (in_array($masterRole, ['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'KASIR'], true)
           || ($masterRole === 'ASURANSI' && $type === 'guarantors'));
    $form = $editingItem ?? null;
@endphp

<div class="card">
    <h2>Master Data</h2>
    <div class="nav master-nav">
        @foreach([
            'accounts' => 'COA',
            'units' => 'Unit Pelayanan',
            'guarantors' => 'Penjamin',
            'cash-banks' => 'Kas/Bank',
            'expense-categories' => 'Kategori Pengeluaran',
            'financial-groups' => 'Group Laporan',
        ] as $k => $v)
            <a class="{{ $type === $k ? 'active' : '' }}" href="{{ route('accounting.master.index', $k) }}">{{ $v }}</a>
        @endforeach
    </div>
</div>

@if($type === 'accounts')
<div class="card actions">
    <a class="btn" href="{{ route('accounting.master.index', 'accounts') }}">Semua COA</a>
    <a class="btn" href="{{ route('accounting.master.index', ['accounts', 'group'=>'general']) }}">COA Umum</a>
    <a class="btn" href="{{ route('accounting.master.index', ['accounts', 'group'=>'referral']) }}">COA Referal</a>
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
                <div class="field"><label>Type</label><select name="type">@foreach(['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE'] as $x)<option value="{{ $x }}" @selected(old('type',$form?->type)===$x)>{{ $x }}</option>@endforeach</select></div>
                <div class="field"><label>Normal Balance</label><select name="normal_balance"><option value="DEBIT" @selected(old('normal_balance',$form?->normal_balance ?? 'DEBIT')==='DEBIT')>DEBIT</option><option value="CREDIT" @selected(old('normal_balance',$form?->normal_balance)==='CREDIT')>CREDIT</option></select></div>
                <div class="field"><label>Parent</label><select name="parent_id"><option value="">-</option>@foreach($accounts->where('id','!=',$form?->id) as $a)<option value="{{ $a->id }}" @selected((string)old('parent_id',$form?->parent_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'units')
                <div class="field"><label>Revenue Account</label><select name="revenue_account_id"><option value="">-</option>@foreach($accounts->where('type','REVENUE') as $a)<option value="{{ $a->id }}" @selected((string)old('revenue_account_id',$form?->revenue_account_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'guarantors')
                <div class="field"><label>Category</label><select name="category">@foreach(['SELF_PAY'=>'Umum / Pribadi','BPJS'=>'BPJS Kesehatan','PRIVATE_INSURANCE'=>'Asuransi Swasta','CORPORATE'=>'Corporate','OTHER'=>'Lainnya'] as $k=>$v)<option value="{{ $k }}" @selected(old('category',$form?->category)===$k)>{{ $v }}</option>@endforeach</select></div>
                <div class="field"><label>Group Laporan</label><select name="financial_group_id" required><option value="">- Pilih Group -</option>@foreach($financialGroups->where('type','INCOME') as $group)<option value="{{ $group->id }}" @selected((string)old('financial_group_id',$form?->financial_group_id)===(string)$group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="field"><label>BPJS?</label><select name="is_bpjs"><option value="0" @selected((string)old('is_bpjs',(int)($form?->is_bpjs ?? 0))==='0')>Tidak</option><option value="1" @selected((string)old('is_bpjs',(int)($form?->is_bpjs ?? 0))==='1')>Ya</option></select></div>
            @endif

            @if($type === 'cash-banks')
                <div class="field"><label>Type</label><select name="type"><option value="CASH" @selected(old('type',$form?->type)==='CASH')>CASH</option><option value="BANK" @selected(old('type',$form?->type)==='BANK')>BANK</option></select></div>
                <div class="field"><label>Bank Name</label><input name="bank_name" value="{{ old('bank_name',$form?->bank_name) }}"></div>
                <div class="field"><label>No Rekening</label><input name="account_number" value="{{ old('account_number',$form?->account_number) }}"></div>
                <div class="field"><label>COA</label><select name="chart_account_id">@foreach($accounts as $a)<option value="{{ $a->id }}" @selected((string)old('chart_account_id',$form?->chart_account_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'expense-categories')
                <div class="field"><label>Group Laporan</label><select name="financial_group_id" required><option value="">- Pilih Group -</option>@foreach($financialGroups->where('type','EXPENSE') as $group)<option value="{{ $group->id }}" @selected((string)old('financial_group_id',$form?->financial_group_id)===(string)$group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="field"><label>Expense Account</label><select name="expense_account_id">@foreach($accounts->where('type','EXPENSE') as $a)<option value="{{ $a->id }}" @selected((string)old('expense_account_id',$form?->expense_account_id)===(string)$a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            @endif

            @if($type === 'financial-groups')
                <div class="field"><label>Jenis Group</label><select name="type"><option value="INCOME" @selected(old('type',$form?->type)==='INCOME')>Pemasukan</option><option value="EXPENSE" @selected(old('type',$form?->type)==='EXPENSE')>Pengeluaran</option></select></div>
                <div class="field"><label>Urutan</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order',$form?->sort_order ?? 10) }}" required></div>
            @endif

            <div class="field"><label>Status</label><select name="is_active"><option value="1" @selected((string)old('is_active',(int)($form?->is_active ?? 1))==='1')>Active</option><option value="0" @selected((string)old('is_active',(int)($form?->is_active ?? 1))==='0')>Inactive</option></select></div>
        </div>
        <div class="actions">
            <button class="btn primary">{{ $form ? 'Simpan Perubahan' : 'Tambah' }}</button>
            @if($form)<a class="btn" href="{{ route('accounting.master.index',$type) }}">Batal</a>@endif
        </div>
    </form>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Code</th><th>Nama</th>@if($type === 'financial-groups')<th>Jenis</th><th>Urutan</th>@endif @if(in_array($type,['guarantors','expense-categories']))<th>Group Laporan</th>@endif <th>Status</th>@if($canManageMaster)<th>Aksi</th>@endif</tr></thead>
            <tbody>
                @foreach($items as $x)
                    <tr>
                        <td>{{ $x->code }}</td><td>{{ $x->name }} @if($type === 'accounts' && $x->is_referral)<span class="badge">Referal</span>@endif</td>
                        @if($type === 'financial-groups')<td>{{ $x->type === 'INCOME' ? 'Pemasukan' : 'Pengeluaran' }}</td><td>{{ $x->sort_order }}</td>@endif
                        @if(in_array($type,['guarantors','expense-categories']))<td>{{ $x->financialGroup?->name ?? '-' }}</td>@endif
                        <td>{{ $x->is_active ? 'Active' : 'Inactive' }}</td>
                        @if($canManageMaster)
                            <td><div class="actions">
                                <a class="btn" href="{{ route('accounting.master.index',[$type,'edit'=>$x->id]) }}">Edit</a>
                                @if($type === 'accounts' && in_array((int)$x->id, $lockedCoaIds ?? [], true))
                                    <button type="button" class="btn" disabled title="COA masih digunakan dan tidak boleh dihapus">Tidak Bisa Dihapus</button>
                                @else
                                    <form method="POST" action="{{ route('accounting.master.destroy',[$type,$x->id]) }}" onsubmit="return confirm('Hapus data {{ addslashes($x->name) }}? Data hanya dapat dihapus jika belum digunakan oleh data lain.')">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>
                                @endif
                            </div></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $items->links() }}
</div>
@endsection
