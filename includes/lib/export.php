<?php

function export_csv(array $rows, string $filename): void
{
    $filename = preg_replace('/[^a-z0-9_.\-]/i', '', $filename);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        $safe = [];
        foreach ($row as $cell) {
            $cell = (string)$cell;
            // CSV formula injection: cells starting with = + - @ (or tab/CR)
            // would execute as formulas when opened in Excel/LibreOffice.
            if ($cell !== '' && strpbrk($cell[0], "=+-@\t\r") !== false) {
                $cell = "'" . $cell;
            }
            $safe[] = $cell;
        }
        fputcsv($out, $safe);
    }
    fclose($out);
    exit;
}

/**
 * Export rows as a real .xlsx file without any PHP extension.
 * Builds the OOXML parts and wraps them in a ZIP archive (STORED, no compression).
 */
function export_excel(array $rows, string $filename): void
{
    $filename = preg_replace('/[^a-z0-9_.\-]/i', '', $filename);
    $escape = function (string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    // shared strings
    $shared = [];
    $sheetRows = [];
    foreach ($rows as $row) {
        $cells = '';
        foreach ($row as $cell) {
            $v = (string)$cell;
            $idx = array_search($v, $shared, true);
            if ($idx === false) {
                $shared[] = $v;
                $idx = count($shared) - 1;
            }
            $cells .= '<c t="s"><v>' . $idx . '</v></c>';
        }
        $sheetRows[] = '<row>' . $cells . '</row>';
    }
    $sharedXml = '';
    foreach ($shared as $s) {
        $sharedXml .= '<si><t xml:space="preserve">' . $escape($s) . '</t></si>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '</styleSheet>',
        'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared) . '" uniqueCount="' . count($shared) . '">'
            . $sharedXml . '</sst>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>',
    ];

    // Build ZIP (STORED)
    $zipData = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', $name);
        $nameBytes = $content;
        $crc = crc32($nameBytes);
        $size = strlen($nameBytes);
        $nameLen = strlen($name);
        // local file header
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0) . $name . $nameBytes;
        $zipData .= $local;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }
    $centralSize = strlen($central);
    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralSize, $offset, 0);
    $zipData .= $central . $eocd;

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($zipData));
    echo $zipData;
    exit;
}
