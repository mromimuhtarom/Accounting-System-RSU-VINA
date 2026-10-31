@php($origin=$asset->stagingRow)
@php($cap=$asset->capitalization)
<div class="card" id="asset-data-origin">
<h3>Asal data dan hubungannya dengan laporan bulanan</h3>
<p>Halaman edit mengubah data master inventaris satu kali. Aktiva yang sama tidak perlu diinput ulang setiap bulan. Grafik bulanan menggunakan tanggal jurnal kapitalisasi dan penyusutan yang sudah diposting.</p>
@if($origin)
<p><strong>Sumber awal:</strong> {{ $origin->import->filename }}<br>Worksheet <strong>{{ $origin->sheet }}</strong> · baris Excel <strong>{{ $origin->source_row }}</strong>@if(!empty($origin->normalized['original_row'])) · baris pada file Alkes asal <strong>{{ $origin->normalized['original_row'] }}</strong>@endif</p>
@php($originColumns=$origin->import->mapping[$origin->sheet]['columns'] ?? [])
<details><summary>Bandingkan nilai asli Excel dengan master saat ini</summary><div class="table-wrap"><table><thead><tr><th>Field</th><th>Nilai asli Excel</th><th>Nilai master saat ini</th></tr></thead><tbody>
@foreach(['name'=>'Nama','code'=>'Kode','serial_number'=>'Nomor seri','location'=>'Lokasi','procurement_year'=>'Tahun pengadaan','source_price'=>'Harga sementara'] as $field=>$label)
@php($sourceValue=isset($originColumns[$field]) ? ($origin->raw[$originColumns[$field]] ?? null) : ($origin->normalized[$field] ?? null))
<tr><td>{{ $label }}</td><td>@if($field==='source_price' && is_numeric($sourceValue))Rp {{ number_format((float)$sourceValue,2,',','.') }}@else{{ $sourceValue ?? 'Tidak diisi pada sumber' }}@endif</td><td>@if($field==='source_price' && $asset->$field!==null)Rp {{ number_format($asset->$field,2,',','.') }}@else{{ $asset->$field ?? 'Belum dilengkapi' }}@endif</td></tr>
@endforeach
</tbody></table></div><p>Tahun pengadaan pada Excel tidak menentukan bulan jurnal. Tanggal perolehan dan mulai penyusutan dilengkapi pada kapitalisasi berdasarkan dokumen pendukung.</p></details>
@else
<p><strong>Sumber awal:</strong> input manual oleh petugas #{{ $asset->created_by ?? $asset->verified_by }}, dicatat {{ $asset->created_at?->format('d/m/Y H:i') }}. Data tidak berasal dari file Excel.</p>
@endif
<p><strong>Harga sementara pada master:</strong> {{ $asset->source_price===null ? 'Belum diisi' : 'Rp '.number_format($asset->source_price,2,',','.') }}. Nilai ini belum otomatis menjadi nilai pada grafik kapitalisasi.</p>
<p><strong>Status accounting:</strong> {{ ['DRAFT'=>'Draft kapitalisasi','APPROVED'=>'Disetujui, belum diposting','POSTED'=>'Kapitalisasi sudah diposting'][$cap?->status] ?? 'Belum dibuat kapitalisasi' }}.
@if($cap?->status==='POSTED') Jurnal kapitalisasi {{ $cap->journal?->journal_no }} tanggal {{ $cap->journal?->journal_date?->format('d/m/Y') }}; nilai tervalidasi Rp {{ number_format($cap->cost,2,',','.') }}.
@else Kontribusi aktiva ini pada grafik bulanan tetap nol sampai kapitalisasi disetujui dan diposting pada periode yang sesuai.
@endif</p>
<div class="actions"><a class="btn primary" href="{{ route('assets.show',$asset) }}#asset-monthly-history" @if(!empty($editing))target="_blank" rel="noopener" title="Buka detail di tab baru"@endif>Periksa data per bulan</a>@can('account',\App\Models\Asset::class)<a class="btn" href="{{ route('assets.show',$asset) }}#asset-accounting" @if(!empty($editing))target="_blank" rel="noopener"@endif>Buka Kapitalisasi / Penyusutan</a>@endcan</div>
</div>
