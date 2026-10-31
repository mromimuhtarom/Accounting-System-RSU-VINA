@can('delete',$asset)
@if($asset->status==='ACTIVE' && (!$asset->capitalization || $asset->capitalization->status==='DRAFT'))
<details><summary class="btn danger">Hapus Aktiva</summary>
<form method="POST" action="{{ route('assets.destroy',$asset) }}" onsubmit="return confirm('Hapus aktiva ini dari daftar? Riwayat tetap tersimpan.');">@csrf @method('DELETE')
<input type="hidden" name="lock_version" value="{{ $asset->lock_version }}">
<div class="field"><label for="delete-reason-{{ $asset->id }}">Alasan penghapusan *</label><textarea id="delete-reason-{{ $asset->id }}" name="delete_reason" minlength="5" maxlength="2000" required></textarea></div>
<p>Aktiva yang sudah disetujui atau dijurnal harus melalui pelepasan aset.</p><button class="btn danger">Hapus Aktiva</button>
</form></details>
@endif
@endcan
