<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class AdmissionSpreadsheetReader
{
    public function rows(string $path, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            $handle = fopen($path, 'r');
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
            fclose($handle);
            return $rows;
        }
        if ($extension !== 'xlsx') throw new RuntimeException('Upload a CSV or XLSX workbook.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Unable to read the Excel workbook.');
        try {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) throw new RuntimeException('The workbook has no first worksheet.');
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
                foreach ($doc->si as $item) $strings[] = implode('', $item->xpath('.//t') ? array_map(fn ($part) => (string) $part, $item->xpath('.//t')) : []);
            }
            $doc = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
            if (! $doc) throw new RuntimeException('The first worksheet is invalid.');
            $rows = [];
            foreach ($doc->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                    $index = 0;
                    foreach (str_split($match[0] ?? '') as $letter) $index = $index * 26 + ord($letter) - 64;
                    $type = (string) $cell['t'];
                    $value = $type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v;
                    $values[$index - 1] = $type === 's' ? ($strings[(int) $value] ?? '') : $value;
                }
                if ($values) { ksort($values); $rows[] = array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values); }
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }
}
