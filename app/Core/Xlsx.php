<?php
declare(strict_types=1);

/**
 * Minimal dependency-free XLSX (SpreadsheetML 2006) reader & writer.
 *
 * An .xlsx file is a ZIP archive of XML parts. This class implements just
 * enough ZIP (store + deflate) and spreadsheet XML to read and write tabular
 * data — no composer packages and no PHP `zip` extension required.
 *
 * Writing produces a standards-compliant workbook that Excel / LibreOffice /
 * WPS open normally. Reading understands shared strings, inline strings,
 * plain numbers, booleans and formula-string cells.
 */
final class Xlsx
{
    // ------------------------------------------------------------ public API

    /**
     * Read the first worksheet of an .xlsx file into a grid of rows.
     *
     * @return array<int, array<int, int|float|string|bool|null>> rows of cells
     * @throws RuntimeException when the file is not a readable xlsx
     */
    public static function read(string $path): array
    {
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('The PHP DOM extension is required to read XLSX files.');
        }
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('The PHP zlib extension is required to read XLSX files.');
        }
        if (!is_file($path)) {
            throw new RuntimeException('XLSX file not found: ' . $path);
        }
        $files = self::zipRead($path);
        if (!$files) {
            throw new RuntimeException('Not a valid XLSX file.');
        }
        $sheetPath = self::firstSheetPath($files);
        if ($sheetPath === null) {
            throw new RuntimeException('XLSX file contains no worksheet.');
        }
        $shared = isset($files['xl/sharedStrings.xml'])
            ? self::sharedStrings($files['xl/sharedStrings.xml'])
            : [];

        return self::parseSheet($files[$sheetPath], $shared);
    }

    /**
     * Serialize a grid of rows into an .xlsx byte string.
     *
     * @param array $rows rows of scalar cells
     * @param array $opts {
     *   date_cols:   int[]  zero-based column indexes written as Excel date serials
     *   header_rows: int    number of leading rows styled as a bold header
     *   sheet:       string sheet name
     * }
     */
    public static function bytes(array $rows, array $opts = []): string
    {
        $dateCols = array_flip($opts['date_cols'] ?? []);
        $headerRows = (int)($opts['header_rows'] ?? 0);
        $sheetName = self::sanitizeSheetName((string)($opts['sheet'] ?? 'Sheet1'));

        $files = [
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::rootRelsXml(),
            'xl/workbook.xml' => self::workbookXml($sheetName),
            'xl/_rels/workbook.xml.rels' => self::workbookRelsXml(),
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => self::sheetXml($rows, $dateCols, $headerRows),
        ];

        return self::zipBuild($files);
    }

    /** Write a grid of rows to an .xlsx file on disk. */
    public static function write(string $path, array $rows, array $opts = []): void
    {
        file_put_contents($path, self::bytes($rows, $opts));
    }

    /** Y-m-d date → Excel serial number (days since 1899-12-30). */
    public static function dateToSerial(string $ymd): int
    {
        $unix = strtotime($ymd . ' 00:00:00 UTC');
        if ($unix === false) return 0;
        return (int)floor(($unix + 2209161600) / 86400);
    }

    /** Excel serial number → Y-m-d (null when out of range). */
    public static function serialToDate(int|float $serial): ?string
    {
        $serial = (int)round((float)$serial);
        if ($serial < 1 || $serial > 100000) return null;
        return gmdate('Y-m-d', $serial * 86400 - 2209161600);
    }

    // ------------------------------------------------------------- zip layer

    /** Read all entries of a ZIP archive into [name => raw content]. */
    private static function zipRead(string $path): array
    {
        $data = @file_get_contents($path);
        if ($data === false || $data === '') return [];

        $len = strlen($data);
        // End of Central Directory record — must sit within the final 64 KB + EOCD
        // fixed size (22 bytes); anything else is a false signature in the data.
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false || $eocd > $len - 22 || $eocd < $len - 65557) return [];

        $count = unpack('v', substr($data, $eocd + 10, 2))[1];
        $cdSize = unpack('V', substr($data, $eocd + 12, 4))[1];
        $cdOffset = unpack('V', substr($data, $eocd + 16, 4))[1];
        if ($cdOffset + $cdSize > $len) return [];

        $files = [];
        $pos = $cdOffset;
        for ($i = 0; $i < $count && $pos + 46 <= $len; $i++) {
            if (substr($data, $pos, 4) !== "PK\x01\x02") break; // central dir header
            $method = unpack('v', substr($data, $pos + 10, 2))[1];
            $compSize = unpack('V', substr($data, $pos + 20, 4))[1];
            $nameLen = unpack('v', substr($data, $pos + 28, 2))[1];
            $extraLen = unpack('v', substr($data, $pos + 30, 2))[1];
            $commentLen = unpack('v', substr($data, $pos + 32, 2))[1];
            $localOffset = unpack('V', substr($data, $pos + 42, 4))[1];
            $name = substr($data, $pos + 46, $nameLen);
            $pos += 46 + $nameLen + $extraLen + $commentLen;

            if (substr($data, $localOffset, 4) !== "PK\x03\x04") continue; // local header
            $lNameLen = unpack('v', substr($data, $localOffset + 26, 2))[1];
            $lExtraLen = unpack('v', substr($data, $localOffset + 28, 2))[1];
            $dataStart = $localOffset + 30 + $lNameLen + $lExtraLen;
            $raw = substr($data, $dataStart, $compSize);
            if ($method === 8) {
                $raw = @gzinflate($raw);
                if ($raw === false) $raw = '';
            } elseif ($method !== 0) {
                continue; // unsupported compression method
            }
            $files[$name] = $raw;
        }
        return $files;
    }

    /** Build a ZIP archive from [name => content] pairs. */
    private static function zipBuild(array $files): string
    {
        $central = '';
        $out = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $crc = crc32($content) & 0xFFFFFFFF;
            $comp = @gzdeflate($content, 6);
            if ($comp === false || strlen($comp) >= strlen($content)) {
                $comp = $content;
                $method = 0; // stored
            } else {
                $method = 8; // deflate
            }
            $compSize = strlen($comp);
            $uncompSize = strlen($content);
            $nameLen = strlen($name);
            $dosTime = self::dosDateTime();

            // Local file header
            $local = "PK\x03\x04"
                . pack('v', 20)     // version needed to extract
                . pack('v', 0)      // general purpose flags
                . pack('v', $method)
                . pack('V', $dosTime)
                . pack('V', $crc)
                . pack('V', $compSize)
                . pack('V', $uncompSize)
                . pack('v', $nameLen)
                . pack('v', 0)      // extra field length
                . $name
                . $comp;
            $out .= $local;

            // Central directory header
            $central .= "PK\x01\x02"
                . pack('v', 20)     // version made by
                . pack('v', 20)     // version needed
                . pack('v', 0)      // flags
                . pack('v', $method)
                . pack('V', $dosTime)
                . pack('V', $crc)
                . pack('V', $compSize)
                . pack('V', $uncompSize)
                . pack('v', $nameLen)
                . pack('v', 0)      // extra
                . pack('v', 0)      // comment
                . pack('v', 0)      // disk number start
                . pack('v', 0)      // internal attributes
                . pack('V', 0)      // external attributes
                . pack('V', $offset)
                . $name;
            $offset += strlen($local);
        }

        $cdSize = strlen($central);
        return $out . $central
            . "PK\x05\x06"
            . pack('v', 0)          // disk number
            . pack('v', 0)          // disk with central dir
            . pack('v', count($files))
            . pack('v', count($files))
            . pack('V', $cdSize)
            . pack('V', $offset)
            . pack('v', 0);         // comment length
    }

    private static function dosDateTime(): int
    {
        $d = getdate();
        return (($d['year'] - 1980) << 25) | ($d['mon'] << 21) | ($d['mday'] << 16)
            | ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
    }

    // ------------------------------------------------------------ xlsx parts

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function sheetXml(array $rows, array $dateCols, int $headerRows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';
        foreach ($rows as $r => $row) {
            $rowNo = $r + 1;
            $xml .= '<row r="' . $rowNo . '">';
            $cells = is_array($row) ? array_values($row) : [$row];
            foreach ($cells as $c => $value) {
                if ($value === null || $value === '') continue; // empty cell
                $ref = self::colName($c) . $rowNo;
                $style = $r < $headerRows ? 2 : (isset($dateCols[$c]) ? 1 : 0);
                $sAttr = $style ? ' s="' . $style . '"' : '';

                // Date columns: convert Y-m-d strings to Excel serial numbers.
                if (isset($dateCols[$c]) && is_string($value)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $value = self::dateToSerial($value);
                }

                if (is_bool($value)) {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . ' t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . '><v>' . self::num((float)$value) . '</v></c>';
                } elseif (is_numeric($value)) {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . '><v>' . self::numStr((string)$value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . ' t="inlineStr"><is><t>' . self::esc((string)$value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    // ---------------------------------------------------------- spreadsheet XML

    private static function firstSheetPath(array $files): ?string
    {
        $rels = [];
        if (isset($files['xl/_rels/workbook.xml.rels'])) {
            $d = new DOMDocument();
            @$d->loadXML($files['xl/_rels/workbook.xml.rels']);
            foreach ($d->getElementsByTagNameNS('*', 'Relationship') as $rel) {
                $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }
        }
        if (isset($files['xl/workbook.xml'])) {
            $d = new DOMDocument();
            @$d->loadXML($files['xl/workbook.xml']);
            foreach ($d->getElementsByTagNameNS('*', 'sheet') as $sheet) {
                $rid = $sheet->getAttribute('r:id');
                if ($rid === '') {
                    $rid = $sheet->getAttributeNS(
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                }
                $target = $rels[$rid] ?? '';
                $path = self::resolveSheetPath($target);
                if ($path !== null && isset($files[$path])) return $path;
            }
        }
        // Fallback: first worksheet part in the archive
        foreach ($files as $name => $content) {
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string)$name)) return $name;
        }
        return null;
    }

    private static function resolveSheetPath(string $target): ?string
    {
        $target = trim($target);
        if ($target === '') return null;
        if (str_starts_with($target, '/')) return 'xl' . $target;
        if (str_starts_with($target, 'xl/')) return $target;
        return 'xl/' . $target;
    }

    private static function sharedStrings(string $xml): array
    {
        $d = new DOMDocument();
        @$d->loadXML($xml);
        $out = [];
        foreach ($d->getElementsByTagNameNS('*', 'si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagNameNS('*', 't') as $t) {
                $text .= $t->textContent;
            }
            $out[] = $text;
        }
        return $out;
    }

    private static function parseSheet(string $xml, array $shared): array
    {
        $d = new DOMDocument();
        @$d->loadXML($xml);
        if (!$d->documentElement) return [];

        $rows = [];
        foreach ($d->getElementsByTagNameNS('*', 'row') as $rowNode) {
            $cells = [];
            foreach ($rowNode->getElementsByTagNameNS('*', 'c') as $c) {
                $col = self::colIndex($c->getAttribute('r'));
                $type = $c->getAttribute('t');
                $value = null;

                if ($type === 's') {
                    $v = $c->getElementsByTagNameNS('*', 'v')->item(0);
                    $idx = $v !== null ? (int)$v->textContent : -1;
                    $value = ($idx >= 0 && isset($shared[$idx])) ? $shared[$idx] : null;
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($c->getElementsByTagNameNS('*', 't') as $t) $value .= $t->textContent;
                } elseif ($type === 'b') {
                    $v = $c->getElementsByTagNameNS('*', 'v')->item(0);
                    $value = $v !== null && $v->textContent === '1';
                } else {
                    $v = $c->getElementsByTagNameNS('*', 'v')->item(0);
                    if ($v !== null) {
                        $num = trim($v->textContent);
                        $value = ($num === '') ? null
                            : ((strpbrk($num, '.eE') !== false) ? (float)$num : (int)$num);
                    }
                }
                $cells[$col] = $value;
            }
            if (!$cells) continue;
            ksort($cells);
            $line = [];
            for ($i = 0, $max = max(array_keys($cells)); $i <= $max; $i++) {
                $line[] = $cells[$i] ?? null;
            }
            $rows[] = $line;
        }
        return $rows;
    }

    // -------------------------------------------------------------- utilities

    private static function colIndex(string $ref): int
    {
        $i = 0;
        $index = 0;
        $len = strlen($ref);
        while ($i < $len && ($ch = $ref[$i]) >= 'A' && $ch <= 'Z') {
            $index = $index * 26 + (ord($ch) - 64);
            $i++;
        }
        return $index - 1;
    }

    private static function colName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - 1, 26);
        }
        return $name;
    }

    /** Trim a float to a plain decimal string ("1.28", "100", "0.012966804979"). */
    private static function num(float $v): string
    {
        if ((int)$v == $v && abs($v) < 1e15) return (string)(int)$v;
        $s = rtrim(rtrim(sprintf('%.12F', $v), '0'), '.');
        return ($s === '' || $s === '-0') ? '0' : $s;
    }

    /**
     * Trim a numeric string without precision loss (handles exponent notation).
     * Trailing zeros are only trimmed after a decimal point — whole numbers like
     * "150" or "-100" must stay untouched (rtrim would turn them into "15"/"-1").
     */
    private static function numStr(string $s): string
    {
        if (preg_match('/[eE]/', $s)) {
            $s = rtrim(rtrim(sprintf('%.12F', (float)$s), '0'), '.');
        } elseif (str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        return ($s === '' || $s === '-0' || $s === '-') ? '0' : $s;
    }

    private static function sanitizeSheetName(string $name): string
    {
        $name = (string)preg_replace('/[\[\]:*?\/\\\\]/', '', $name);
        $name = mb_substr(trim($name), 0, 31);
        return $name === '' ? 'Sheet1' : $name;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
