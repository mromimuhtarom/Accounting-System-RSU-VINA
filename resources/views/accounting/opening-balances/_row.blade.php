<tr data-opening-row data-index="{{ $index }}">
<td><select name="lines[{{ $index }}][chart_account_id]" aria-label="Akun saldo awal"><option value="">Pilih kelompok dan akun</option>
@foreach(['ASSET'=>'Aset: kas/bank, persediaan, aset tetap dan kontra-aset','LIABILITY'=>'Kewajiban: utang yang belum dilunasi','EQUITY'=>'Ekuitas: modal dan saldo laba'] as $type=>$label)
<optgroup label="{{ $label }}">@foreach($accounts->where('type',$type) as $account)<option value="{{ $account->id }}" data-balance="{{ $account->normal_balance }}" @selected((string)($row['chart_account_id']??'')===(string)$account->id)>{{ $account->code }} / {{ $account->name }}</option>@endforeach</optgroup>
@endforeach
</select></td>
<td><input type="number" name="lines[{{ $index }}][amount]" min="0" max="1000000000000" step="0.01" value="{{ $row['amount']??'' }}" aria-label="Nominal saldo dalam rupiah"></td>
<td><select name="lines[{{ $index }}][side]" aria-label="Posisi saldo"><option value="NORMAL" @selected(($row['side']??'NORMAL')==='NORMAL')>Saldo normal akun (otomatis)</option><option value="DEBIT" @selected(($row['side']??'')==='DEBIT')>Debit</option><option value="CREDIT" @selected(($row['side']??'')==='CREDIT')>Kredit</option></select></td>
<td><button class="btn danger" type="button" data-remove-opening-row>Hapus baris</button></td>
</tr>
