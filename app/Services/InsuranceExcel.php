<?php
namespace App\Services;

use ZipArchive;

class InsuranceExcel
{
    public static function download(string $filename, array $headers, array $records, string $subtitle = '')
    {
        if (!class_exists(ZipArchive::class)) {
            abort(500, 'Ekstensi PHP zip belum aktif pada XAMPP (php.ini).');
        }
        $file = tempnam(sys_get_temp_dir(), 'rsuve_xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat file ekspor Excel.');
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $column = static function (int $i): string {
            $out = '';
            do { $out = chr(65 + ($i % 26)) . $out; $i = intdiv($i, 26) - 1; } while ($i >= 0);
            return $out;
        };
        $last = $column(count($headers) - 1);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="19"/><cols>';
        foreach ($headers as $i => $h) {
            $width = min(46, max(17, mb_strlen((string) $h, 'UTF-8') + 12));
            $xml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1" ht="35" customHeight="1"><c r="A1" t="inlineStr" s="1"><is><t>RSU VINA ESTETICA | LAPORAN KEUANGAN</t></is></c></row>';
        $xml .= '<row r="2"><c r="A2" t="inlineStr" s="3"><is><t>'.$esc($subtitle).'</t></is></c></row>';
        $xml .= '<row r="4" ht="27" customHeight="1">';
        foreach ($headers as $i => $h) $xml .= '<c r="'.$column($i).'4" t="inlineStr" s="2"><is><t>'.$esc($h).'</t></is></c>';
        $xml .= '</row>';
        $n = 5; $total = 0; $amountIdx = array_search('Nominal', $headers, true);
        foreach ($records as $record) {
            $xml .= '<row r="'.$n.'">';
            foreach (array_values($record) as $i => $value) {
                $reference = $column($i).$n;
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$reference.'" s="'.($n%2===0?5:4).'"><v>'.(0+$value).'</v></c>';
                    if ($i === $amountIdx) $total += $value;
                } else {
                    // Prevent spreadsheet formula injection from imported user data.
                    $value = (string) $value;
                    if (preg_match('/^\s*[=+@\-]/u', $value)) $value = "'".$value;
                    $xml .= '<c r="'.$reference.'" t="inlineStr" s="'.($n%2===0?6:0).'"><is><t>'.$esc($value).'</t></is></c>';
                }
            }
            $xml .= '</row>'; $n++;
        }
        $dataEnd = max(4, $n-1);
        // OOXML worksheet order: autoFilter MUST come before mergeCells.
        // Reversing this order triggers Excel repair of xl/worksheets/sheet1.xml.
        $xml .= '</sheetData><autoFilter ref="A4:'.$last.$dataEnd.'"/>';
        $xml .= '<mergeCells count="2"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/></mergeCells>';
        $xml .= '</worksheet>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0;[Red](#,##0)"/></numFmts>'
          .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/></font></fonts>'
          .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF00875A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE9F6EF"/><bgColor indexed="64"/></patternFill></fill></fills>'
          .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
          .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
          .'<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
          .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
          .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
          .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
          .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
          .'<xf numFmtId="164" fontId="0" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1"/>'
          .'<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
          .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        $parts = [
          '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
          '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
          'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap" sheetId="1" r:id="rId1"/></sheets></workbook>',
          'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
          'xl/styles.xml' => $styles,
          'xl/worksheets/sheet1.xml' => $xml,
        ];
        foreach ($parts as $name=>$data) $zip->addFromString($name,$data);
        if (!$zip->close()) { @unlink($file); abort(500, 'Gagal menutup berkas Excel.'); }
        return response()->download($file, $filename, ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'no-store'])->deleteFileAfterSend(true);
    }
}
