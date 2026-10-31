<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssetExcelExport
{
    public function download(Request $request)
    {
        $query = app(AssetInventoryQuery::class)->build($request);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Inventaris');
        $filter = collect($request->only(['q', 'type', 'location', 'condition']))->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v, $k) => $k.': '.$v)->implode(' | ') ?: 'Semua inventaris';
        $sheet->setCellValueExplicit('A1', 'Inventaris & Aktiva — '.config('accounting.hospital_name', 'RS. Vina Estetica'), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', 'Filter: '.$filter, DataType::TYPE_STRING);
        $sheet->setCellValue('A3', 'Harga sementara terpisah dari nilai accounting. Fasilitas bukan unit aktiva. Aset dilepas memiliki nilai buku nol; harga/akumulasi historis tetap ditampilkan.');
        $sheet->setCellValue('A4', 'Diekspor: '.now('Asia/Jakarta')->format('d/m/Y H:i').' WIB');
        foreach (range(1, 4) as $row) {
            $sheet->mergeCells('A'.$row.':AD'.$row);
        }
        $headers = ['No', 'ID Aktiva', 'Nama', 'Jenis', 'Kategori', 'Kode', 'Nomor Seri', 'Merk', 'Tipe / Model', 'Lokasi', 'Penanggung Jawab', 'Jumlah', 'Kondisi', 'Tahun Pengadaan', 'Harga Sementara', 'Pendanaan', 'Kapasitas', 'Status Inventaris', 'Status Kapitalisasi', 'Harga Tervalidasi', 'Akumulasi Penyusutan', 'Nilai Buku', 'Perlu Kalibrasi', 'Jatuh Tempo Kalibrasi', 'File / Sumber', 'Worksheet', 'Baris Asal', 'Keterangan', 'Tanggal Dicatat', 'QR Aktiva'];
        $sheet->fromArray($headers, null, 'A5');
        $rowNumber = 6;
        $totals = [];
        $index = 0;
        $query->with(['capitalization', 'stagingRow.import', 'events' => fn ($q) => $q->where('type', 'DEPRECIATION')])->chunkById(250, function ($assets) use ($sheet, &$rowNumber, &$totals, &$index) {
            foreach ($assets as $asset) {
                $cap = $asset->capitalization;
                $official = $cap?->status === 'POSTED';
                $cost = $official ? (float) $cap->cost : null;
                $acc = $official ? round((float) $cap->opening_accumulated + $asset->events->sum(fn ($e) => (float) ($e->data['amount'] ?? 0)), 2) : null;
                $bookValue = $official ? ($asset->status === 'DISPOSED' ? 0 : round($cost - $acc, 2)) : null;
                $origin = $asset->stagingRow;
                $values = [++$index, 'AST-'.$asset->id, $asset->name, Asset::TYPES[$asset->inventory_type], $asset->category, $asset->code, $asset->serial_number, $asset->brand, $asset->model, $asset->location, $asset->custodian, $asset->quantity, $asset->condition, $asset->procurement_year, $asset->source_price === null ? null : (float) $asset->source_price, $asset->funding, $asset->capacity, $asset->status, $cap?->status ?? 'Belum dikapitalisasi', $cost, $acc, $bookValue, $asset->calibration_required ? 'Ya' : 'Tidak', $asset->calibration_due_at?->format('Y-m-d'), $origin?->import?->filename ?? 'Input manual', $origin?->sheet, $origin?->source_row, $asset->notes, $asset->created_at?->format('Y-m-d'), 'RSUVE:'.$asset->qr_token];
                foreach ($values as $col => $value) {
                    if ($value !== null) {
                        $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($col + 1).$rowNumber, $value, in_array($col, [0, 11, 13, 14, 19, 20, 21, 26], true) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                }
                $type = $asset->inventory_type;
                $totals[$type] ??= ['records' => 0, 'units' => 0, 'cost' => 0, 'accumulated' => 0, 'book' => 0];
                $totals[$type]['records']++;
                $totals[$type]['units'] += $asset->quantity;
                if ($official && $asset->status === 'ACTIVE') {
                    $totals[$type]['cost'] += (int) round($cost * 100);
                    $totals[$type]['accumulated'] += (int) round($acc * 100);
                    $totals[$type]['book'] += (int) round($bookValue * 100);
                }
                $rowNumber++;
            }
        });
        $last = max(5, $rowNumber - 1);
        $sheet->freezePane('D6');
        $sheet->setAutoFilter('A5:AD'.$last);
        $sheet->getStyle('A5:AD5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:AD5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF14734E');
        $sheet->getStyle('A1:AD'.$last)->getAlignment()->setVertical('top');
        if ($last >= 6) {
            foreach (['O', 'T', 'U', 'V'] as $col) {
                $sheet->getStyle($col.'6:'.$col.$last)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getStyle('A6:AD'.$last)->getAlignment()->setWrapText(true);
        }
        for ($col = 1; $col <= 30; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(in_array($col, [3, 10, 25, 28, 30]) ? 40 : 20);
        }
        $sheet->getRowDimension(5)->setRowHeight(32);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $summary = $book->createSheet()->setTitle('Ringkasan');
        $summary->setCellValue('A1', 'Ringkasan sesuai filter — seluruh halaman');
        $summary->setCellValueExplicit('A2', $filter, DataType::TYPE_STRING);
        $summary->setCellValue('A3', 'Nilai accounting hanya kapitalisasi terposting pada aktiva aktif. Jumlah fasilitas adalah parameter; jumlah software adalah instalasi.');
        $summary->fromArray(['Kelompok', 'Catatan', 'Jumlah / Satuan Sumber', 'Harga Tervalidasi', 'Akumulasi', 'Nilai Buku'], null, 'A5');
        $r = 6;
        foreach ($totals as $type => $total) {
            $summary->fromArray([Asset::TYPES[$type], $total['records'], $type === 'FACILITY' ? 'Parameter kapasitas' : $total['units'], $total['cost'] / 100, $total['accumulated'] / 100, $total['book'] / 100], null, 'A'.$r++, true);
        }
        foreach (range('A', 'F') as $col) {
            $summary->getColumnDimension($col)->setWidth(26);
        }
        $summary->getStyle('A5:F5')->getFont()->setBold(true);
        $summary->getStyle('D6:F'.max(6, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $summary->freezePane('A6');
        $path = tempnam(sys_get_temp_dir(), 'asset-export-');
        try {
            (new Xlsx($book))->save($path);
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            } throw $e;
        } finally {
            $book->disconnectWorksheets();
        }

        return response()->download($path,'Inventaris_Aktiva_'.now('Asia/Jakarta')->format('Ymd_His').'.xlsx',['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
