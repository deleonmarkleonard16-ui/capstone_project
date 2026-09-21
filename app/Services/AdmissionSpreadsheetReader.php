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
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($rows) >= 10001 || count($row) > 100) throw new RuntimeException('Import at most 10,000 applicants and 100 columns per file.');
                    $rows[] = $row;
                }
            } finally { fclose($handle); }
            return $rows;
        }
        if ($extension !== 'xlsx') throw new RuntimeException('Upload a CSV or XLSX workbook.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Unable to read the Excel workbook.');
        try {
            foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $part) {
                $stat = $zip->statName($part);
                if ($stat && $stat['size'] > 20 * 1024 * 1024) throw new RuntimeException('The expanded workbook is too large.');
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) throw new RuntimeException('The workbook has no first worksheet.');
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $doc = $this->xml($xml);
                foreach ($doc->si as $item) $strings[] = implode('', array_map(fn ($part) => (string) $part, $item->xpath('.//*[local-name()="t"]') ?: []));
            }
            $doc = $this->xml($sheet);
            $rows = [];
            foreach ($doc->sheetData->row as $row) {
                if (count($rows) >= 10001) throw new RuntimeException('Import at most 10,000 applicants per file.');
                $values = [];
                foreach ($row->c as $cell) {
                    preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                    $index = 0;
                    foreach (str_split($match[0] ?? '') as $letter) $index = $index * 26 + ord($letter) - 64;
                    if ($index < 1 || $index > 100) throw new RuntimeException('Import at most 100 columns per file.');
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

    private function xml(string $xml): \SimpleXMLElement
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new RuntimeException('Workbook XML declarations are not supported.');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
            if ($doc === false) throw new RuntimeException('The workbook XML is invalid.');
            return $doc;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
