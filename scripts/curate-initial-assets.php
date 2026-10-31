<?php

// One-time, reproducible document reconciliation requested by the user.
// Defaults to preview. --apply posts operational inventory only, never accounting.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Asset, AssetCapitalization, AssetImport, AssetStagingRow, JournalEntry, User};
use App\Services\{AssetAudit, AssetExcelImport};
use Illuminate\Support\Facades\{DB, Gate, Storage, Validator};

$options = getopt('', ['user:', 'apply']);
$actor = User::findOrFail($options['user'] ?? 0);
Gate::forUser($actor)->authorize('approve', Asset::class);
$service = app(AssetExcelImport::class);
$expected = [
    'classification'=>'Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx',
    'it'=>'INVENTARIS HARDWATRE & soft .xlsx',
    'infrastructure'=>'Prasarana_RSUVE_Ketersediaan_Ada.xlsx',
    'alkes'=>'Format Import Alkes (2).xls',
];
$batches = collect();
foreach ($expected as $source=>$filename) {
    $batch = AssetImport::where('source_type',$source)->where('filename',$filename)->sole();
    if (!in_array($batch->status,['STAGED','POSTED'],true)) throw new RuntimeException('Unexpected batch status: '.$batch->id);
    $path = Storage::disk('local')->path($batch->path);
    if (hash_file('sha256',$path)!==$batch->file_hash) throw new RuntimeException('Workbook integrity mismatch');
    $batches->put($source,$batch);
}
if ($batches->every(fn ($b)=>$b->status==='POSTED')) {
    echo "All source batches already posted; no changes.\n";
    exit(0);
}
if ($batches->contains(fn ($b)=>$b->status==='POSTED')) throw new RuntimeException('Mixed batch state: inspect before rerunning.');

$plan = []; $seen = []; $groups = [];
$key = fn ($v)=>AssetExcelImport::key($v);
$add = function ($row,$decision,$reason,$changes=[]) use (&$plan) {
    $d = array_replace($row->normalized,$changes);
    $issues = implode('; ',$row->issues ?? []);
    $note = 'Pemilahan dokumen oleh Codex atas instruksi pengguna; bukan pemeriksaan fisik. '.$reason;
    if ($decision==='READY') {
        $d['notes'] = trim(($d['notes'] ?? '').' | '.$note.' | Catatan sumber: '.$issues.' | Harga sumber belum tervalidasi untuk kapitalisasi.');
    }
    $plan[$row->id] = ['row_id'=>$row->id,'batch_id'=>$row->asset_import_id,'sheet'=>$row->sheet,'source_row'=>$row->source_row,'decision'=>$decision,'reason'=>$note,'data'=>$d];
};

foreach ($batches['classification']->rows()->orderBy('source_row')->get() as $row) {
    $d=$row->normalized;
    if ($key($d['availability']??'')!=='ya') {
        $add($row,'SKIPPED',empty($d['availability']) ? 'Ketersediaan kosong: bukan bukti inventaris tersedia.' : 'Sumber menyatakan Tidak tersedia; kondisi Rusak tidak membuktikan keberadaan fisik.');
        continue;
    }
    $serial=$key($d['serial_number']??'');
    $identity=$service->fingerprint($d);
    // Serial long enough to identify an item is deduplicated with its equipment code.
    $serialCode=mb_strlen($serial)>=5 ? 'serial:'.$key($d['code']).'|'.$serial : null;
    $duplicate=$seen['exact:'.$identity]??($serialCode ? ($seen[$serialCode]??null) : null);
    if ($duplicate) {
        $first=$plan[$duplicate];
        $add($row,'SKIPPED','Duplikat identitas alat dengan staging #'.$duplicate.'; gunakan catatan pertama. Lokasi sumber tetap tersimpan di staging.');
        $plan[$duplicate]['data']['notes'].=' | Baris '.$row->source_row.' juga mencantumkan identitas yang sama pada '.$d['location'].'; lokasi aktual belum diverifikasi.';
        continue;
    }
    $seen['exact:'.$identity]=$row->id;
    if ($serialCode) $seen[$serialCode]=$row->id;
    $corrections=[]; $yearNote='';
    if (!empty($d['procurement_year']) && (!ctype_digit((string)$d['procurement_year']) || (int)$d['procurement_year']<1970 || (int)$d['procurement_year']>(int)date('Y')+1)) {
        $corrections['procurement_year']=null;
        $yearNote=' Tahun sumber '.$d['procurement_year'].' tidak valid; tahun master dikosongkan, bukan ditebak.';
    }
    $add($row,'READY','Tersedia=Ya; kategori mengikuti sumber utama. Nama/kode sama dengan serial berbeda dipertahankan sebagai catatan unit terpisah. Serial pendek/berulang lintas jenis bukan identitas fisik yang tervalidasi.'.$yearNote,$corrections);
}

foreach ($batches['it']->rows()->orderBy('sheet')->orderBy('source_row')->get() as $row) {
    $d=$row->normalized; $raw=$row->raw;
    if (preg_match('/mengetahui|ketua instalasi|hasudungan/i',$d['name']??'') || !is_numeric($raw['D']??null) || (int)$raw['D']<1) {
        $add($row,'SKIPPED','Baris pengesahan/tanda tangan atau bukan perangkat dengan jumlah positif.'); continue;
    }
    $groupKey=$row->sheet.'|'.$key($d['name']).'|'.$key($d['location']);
    if (isset($groups[$groupKey])) {
        $previous=$groups[$groupKey];
        $previousRaw=$previous->raw;
        if (is_numeric($raw['A']??null) && is_numeric($previousRaw['A']??null) && (string)$raw['A']!==(string)$previousRaw['A']) {
            $plan[$previous->id]['data']['quantity']=(string)((int)$plan[$previous->id]['data']['quantity']+(int)$d['quantity']);
            $plan[$previous->id]['data']['notes'].=' | Jumlah digabung dari kelompok sumber berbeda pada baris '.$previous->source_row.' dan '.$row->source_row.'; tetap catatan agregat, bukan unit berserial.';
            $add($row,'SKIPPED','Jumlah digabung ke staging #'.$previous->id.' untuk nama/lokasi sama pada kelompok sumber berbeda.');
        } elseif ((int)$d['quantity']===(int)$previous->normalized['quantity']) {
            $add($row,'SKIPPED','Pengulangan nama, lokasi, dan jumlah pada kelompok yang sama; gunakan staging #'.$previous->id.'.');
        } else {
            throw new RuntimeException('Unresolved IT quantity conflict at row '.$row->id);
        }
        continue;
    }
    $groups[$groupKey]=$row;
    $add($row,'READY','Inventaris '.($d['inventory_type']==='SOFTWARE'?'instalasi software; lisensi belum diverifikasi':'IT/hardware').' berdasarkan daftar unit; jumlah sesuai Excel, kondisi tidak diketahui, serial tidak direka.');
}

foreach ($batches['infrastructure']->rows()->orderBy('source_row')->get() as $row) {
    $d=$row->normalized;
    $add($row,'READY','Parameter ketersediaan/kapasitas fasilitas rumah sakit. Bukan unit aktiva individual; tidak dikapitalisasi dan tidak disusutkan.',[
        'name'=>AssetExcelImport::text($row->raw['C']??'').' — '.$d['name'],
        'inventory_type'=>'FACILITY','condition'=>'GOOD','location'=>null,
    ]);
}
foreach ($batches['alkes']->rows()->orderBy('source_row')->get() as $row) {
    $add($row,'SKIPPED',empty($row->normalized['name']) ? 'Baris struktural template Alkes, bukan inventaris.' : 'Sumber pembanding: identitas/baris asal telah tercakup pada workbook klasifikasi. Tidak membuat inventaris kedua.');
}

$summary=[];
foreach ($plan as $entry) {
    $k=$entry['batch_id']; $s=$entry['decision'];
    $summary[$k][$s]=($summary[$k][$s]??0)+1;
    if ($s==='READY') {
        $rules=['name'=>'required|string|max:255','quantity'=>'required|integer|min:1','procurement_year'=>'nullable|integer|min:1970|max:'.(date('Y')+1),'source_price'=>'nullable|numeric|min:0'];
        foreach (['code','serial_number','brand','model','category','location','condition','funding','capacity'] as $f) $rules[$f]='nullable|string|max:255';
        Validator::make($entry['data'],$rules)->validate();
    }
}
echo json_encode(['plan'=>$summary,'apply'=>array_key_exists('apply',$options)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
$report='asset-imports/reconciliation-20261029.json';
if (!array_key_exists('apply',$options)) exit(0);
$backup='asset-imports/backups/pre-reconciliation-'.now()->format('YmdHis').'.json';
Storage::disk('local')->put($backup,json_encode(['imports'=>$batches->values()->toArray(),'staging'=>AssetStagingRow::whereIn('asset_import_id',$batches->pluck('id'))->get()->toArray(),'assets'=>Asset::all()->toArray()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$journalCount=JournalEntry::count(); $capCount=AssetCapitalization::count(); $before=Asset::count();
DB::transaction(function () use ($plan,$batches,$actor,$service,$journalCount,$capCount,$before) {
    // Resolve discarded candidates first, then review all retained document records.
    foreach (['SKIPPED','READY'] as $decision) foreach ($plan as $entry) {
        if ($entry['decision']!==$decision) continue;
        $service->review(AssetStagingRow::findOrFail($entry['row_id']),$entry['data'],$decision,$entry['reason'],$actor->id);
    }
    foreach ($batches as $batch) {
        $service->approve($batch,$actor->id); $service->post($batch,$actor->id);
        if ($batch->fresh()->status!=='POSTED') throw new RuntimeException('Posting blocked; rolling back complete reconciliation.');
        AssetAudit::record($batch,'DOCUMENT_RECONCILIATION',$actor->id,[],['instruction'=>'Pengguna meminta Codex memilah dan memasukkan langsung','verification'=>'document_only','capitalization'=>false]);
    }
    $expected=count(array_filter($plan,fn($e)=>$e['decision']==='READY'));
    if (Asset::count()!==$before+$expected || JournalEntry::count()!==$journalCount || AssetCapitalization::count()!==$capCount) throw new RuntimeException('Reconciliation invariant failed.');
});
Storage::disk('local')->put($report,json_encode(['actor'=>$actor->id,'method'=>'user_delegated_document_reconciliation','created_at'=>now()->toIso8601String(),'backup'=>$backup,'summary'=>$summary,'decisions'=>array_values($plan)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode(['posted'=>Asset::count()-$before,'types'=>Asset::selectRaw('inventory_type, COUNT(*) AS records, SUM(quantity) AS units')->groupBy('inventory_type')->get()->toArray(),'journals'=>JournalEntry::count(),'capitalizations'=>AssetCapitalization::count(),'report'=>$report,'backup'=>$backup],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
