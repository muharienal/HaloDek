<?php

namespace App\Support;

use ZipArchive;

/**
 * Penulis .xlsx sederhana tanpa dependensi (hanya butuh ekstensi zip).
 * Sel teks ditulis sebagai inline string sehingga isi tidak pernah dianggap rumus.
 *
 * Gaya sel: 'title', 'head', 'bold', 'dec' (0,0), 'int'. Lainnya = biasa.
 */
class Xlsx
{
    private const STYLE = ['title' => 1, 'head' => 2, 'bold' => 3, 'dec' => 4, 'int' => 5];

    /**
     * @param  array<int, array{name:string, widths?:array<int,float>, rows:array<int, array<int, mixed>>}>  $sheets
     *   Sel = nilai biasa, atau ['v' => nilai, 's' => 'gaya'].
     * @return string path berkas sementara (hapus setelah dipakai)
     */
    public static function build(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        $n = count($sheets);
        $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_values($sheets) as $i => $sheet) {
            $id = $i + 1;
            $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb .= '<sheet name="'.self::esc(self::sheetName($sheet['name'], $i)).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', self::sheetXml($sheet));
        }
        $rels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

        $zip->addFromString('[Content_Types].xml', $types.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $wb.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->close();

        return $path;
    }

    private static function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if (! empty($sheet['widths'])) {
            $xml .= '<cols>';
            foreach (array_values($sheet['widths']) as $i => $w) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $cell) {
                $value = is_array($cell) ? ($cell['v'] ?? null) : $cell;
                $style = is_array($cell) ? (self::STYLE[$cell['s'] ?? ''] ?? 0) : 0;
                if ($value === null || $value === '') {
                    if ($style) {
                        $xml .= '<c r="'.self::col($c).($r + 1).'" s="'.$style.'"/>';
                    }
                    continue;
                }
                $ref = self::col($c).($r + 1);
                $s = $style ? ' s="'.$style.'"' : '';
                $xml .= is_int($value) || is_float($value)
                    ? '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>'
                    : '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0"/></numFmts>'
            .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF1A2858"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF263A86"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function sheetName(string $name, int $i): string
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name));

        return mb_substr($name !== '' ? $name : 'Sheet'.($i + 1), 0, 31);
    }

    private static function esc(string $v): string
    {
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
