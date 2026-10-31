<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\User;
use App\Services\AssetExcelImport;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class StageAssetSources extends Command
{
    protected $signature = 'assets:stage-sources {directory : Folder berisi empat workbook sumber} {--user= : ID petugas berhak import, wajib}';

    protected $description = 'Upload dan staging sumber awal aktiva tanpa approval, posting master, atau jurnal';

    public function handle(AssetExcelImport $service): int
    {
        $actor = User::find($this->option('user'));
        if (! $actor || ! Gate::forUser($actor)->allows('import', Asset::class)) {
            $this->error('--user harus petugas aktif dengan hak import aktiva.');

            return self::FAILURE;
        }
        $sources = [
            'Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx' => ['classification', ['Klasifikasi Alat']],
            'INVENTARIS HARDWATRE & soft .xlsx' => ['it', ['Sheet1', 'Sheet2']],
            'Prasarana_RSUVE_Ketersediaan_Ada.xlsx' => ['infrastructure', ['Prasarana Ada']],
            'Format Import Alkes (2).xls' => ['alkes', ['Format Import Alkes.xls']],
        ];
        foreach ($sources as $file => $_) {
            if (! is_file(rtrim($this->argument('directory'), '/\\').DIRECTORY_SEPARATOR.$file)) {
                $this->error('File tidak ditemukan: '.$file);

                return self::FAILURE;
            }
        }
        foreach ($sources as $file => [$source,$sheets]) {
            $path = rtrim($this->argument('directory'), '/\\').DIRECTORY_SEPARATOR.$file;
            $batch = $service->upload(new UploadedFile($path, $file, null, null, true), $actor->id);
            foreach ($sheets as $sheet) {
                if ($batch->rows()->where('sheet', $sheet)->exists() || in_array($batch->fresh()->status, ['APPROVED', 'POSTED'], true)) {
                    continue;
                }
                [$header,$mapping] = $service->preset($source, $sheet);
                $service->stage($batch, $sheet, $source, $header, $mapping, $actor->id);
            }
            $this->line('#'.$batch->id.' '.$file.' '.json_encode($batch->counts()));
        }
        $this->info('Sumber awal tersimpan di staging. Verifikasi dan approval dilakukan melalui Import Aktiva.');

        return self::SUCCESS;
    }
}
