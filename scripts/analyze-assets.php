<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/../vendor/autoload.php';
$files = ['INVENTARIS HARDWATRE & soft .xlsx', 'Format Import Alkes (2).xls', 'Prasarana_RSUVE_Ketersediaan_Ada.xlsx', 'Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx'];
$report = [];
$equipment = [];
foreach ($files as $file) {
    $reader = IOFactory::createReaderForFile('C:/Users/ASUS/Downloads/'.$file);
    $reader->setReadDataOnly(true);
    $book = $reader->load('C:/Users/ASUS/Downloads/'.$file);
    foreach ($book->getWorksheetIterator() as $sheet) {
        $rows = $sheet->toArray(null, false, false, true);
        $nonempty = [];
        foreach ($rows as $i => $row) {
            $values = array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '');
            if ($values) {
                $nonempty[$i] = $values;
            }
        }
        $report[$file][$sheet->getTitle()] = ['rows' => $sheet->getHighestDataRow(), 'columns' => $sheet->getHighestDataColumn(), 'nonempty_rows' => count($nonempty), 'sample' => array_slice($nonempty, 0, 14, true)];
        $columns = [];
        foreach ($nonempty as $number => $row) {
            foreach ($row as $column => $value) {
                $columns[$column]['populated'] = ($columns[$column]['populated'] ?? 0) + 1;
                if (is_string($value) && str_starts_with($value, '=')) {
                    $columns[$column]['formulas'] = ($columns[$column]['formulas'] ?? 0) + 1;
                }
            }
        }
        $report[$file][$sheet->getTitle()]['column_profile'] = $columns;
        $data = [];
        $room = null;
        foreach ($rows as $number => $row) {
            if ($file === 'Format Import Alkes (2).xls' && $sheet->getTitle() === 'Format Import Alkes.xls') {
                if (($row['A'] ?? '') === '***') {
                    $room = $row['D'];
                }
                if (! is_numeric($row['A'] ?? null) || empty($row['E'])) {
                    continue;
                }
                $data[] = ['row' => $number, 'code' => $row['D'], 'name' => $row['E'], 'location' => $room, 'serial' => $row['G'], 'brand' => $row['H'], 'model' => $row['I'], 'year' => $row['J'], 'condition' => $row['K'], 'price' => $row['L'], 'funding' => $row['M'], 'available' => $row['F']];
            }
            if ($sheet->getTitle() === 'Klasifikasi Alat' && $number > 1 && ! empty($row['G'])) {
                $data[] = ['row' => $number, 'original_row' => $row['B'], 'code' => $row['F'], 'name' => $row['G'], 'location' => $row['E'], 'serial' => $row['L'], 'brand' => $row['M'], 'model' => $row['N'], 'year' => $row['O'], 'condition' => $row['P'], 'price' => $row['Q'], 'funding' => $row['R'], 'category' => $row['H'], 'confidence' => $row['I'], 'available' => $row['K']];
            }
        }
        if ($data) {
            $stats = ['data_rows' => count($data)];
            foreach (['category', 'confidence', 'condition', 'funding', 'available', 'year'] as $field) {
                $stats[$field] = array_count_values(array_map(fn ($r) => (string) ($r[$field] ?? '(not provided)'), $data));
            }
            foreach (['serial', 'brand', 'model', 'price', 'location'] as $field) {
                $stats['missing_'.$field] = count(array_filter($data, fn ($r) => empty($r[$field]) || preg_match('/^(?:unknown|-unknown-|unregister|-)$/i', (string) $r[$field])));
            }
            $keys = array_map(fn ($r) => mb_strtolower(trim((string) $r['code'])).'|'.mb_strtolower(trim((string) $r['serial'])).'|'.mb_strtolower(trim((string) $r['name'])).'|'.mb_strtolower(trim((string) $r['location'])), $data);
            $stats['repeated_exact_identity'] = count($keys) - count(array_unique($keys));
            $report[$file][$sheet->getTitle()]['data_profile'] = $stats;
            $equipment[$file] = $data;
        }
    }
    $book->disconnectWorksheets();
}
$original = array_column($equipment['Format Import Alkes (2).xls'] ?? [], null, 'row');
$matches = 0;
foreach ($equipment['Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx'] ?? [] as $r) {
    $origin = $original[$r['original_row']] ?? null;
    if ($origin && trim((string) $origin['code']) === trim((string) $r['code']) && trim((string) $origin['serial']) === trim((string) $r['serial'])) {
        $matches++;
    }
}
$report['_cross_source_verification'] = ['classification_rows_matching_alkes_original_row_code_and_serial' => $matches, 'classification_rows' => count($equipment['Klasifikasi_Alat_Medis_dan_Penunjang_Medis_RSUVE.xlsx'] ?? [])];
file_put_contents(__DIR__.'/../docs/asset-source-analysis.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
