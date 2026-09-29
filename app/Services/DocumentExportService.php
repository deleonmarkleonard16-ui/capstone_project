<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocumentExportService
{
    public const UNIVERSITY = 'Pangasinan State University - San Carlos Campus';

    /** Generate bytes before sending headers so failures can still flash and redirect. */
    public function render(array $document, string $format): string
    {
        try {
            return match ($format) {
                'pdf' => $this->pdf($document),
                'docx' => $this->docx($document),
                'csv' => $this->csv($document),
                default => view('exports.document', $document)->render(),
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException('Document generation failed.', 0, $e);
        }
    }

    private function pdf(array $document): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new \RuntimeException('The PDF dependency is unavailable.');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.document', $document)->render(), 'UTF-8');
        $pdf->setPaper('A4', $document['orientation']);
        $pdf->render();
        $bytes = $pdf->output();
        if ($bytes === '') {
            throw new \RuntimeException('The PDF generator returned an empty document.');
        }

        return $bytes;
    }

    private function docx(array $document): string
    {
        if (! class_exists(PhpWord::class) || ! class_exists(\ZipArchive::class) || ! class_exists(\XMLWriter::class)) {
            throw new \RuntimeException('DOCX requires PHPWord, ZipArchive and XMLWriter.');
        }
        $word = new PhpWord();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['orientation' => $document['orientation'], 'marginTop' => 720, 'marginBottom' => 720, 'marginLeft' => 720, 'marginRight' => 720]);
        $header = $section->addHeader();
        $header->addText(self::UNIVERSITY, ['bold' => true, 'color' => '17305F'], ['alignment' => 'center']);
        $header->addText($document['office'], [], ['alignment' => 'center']);
        $section->addText($document['title'], ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        foreach ($document['metadata'] as $label => $value) {
            $section->addText($label.': '.$value, ['size' => 9]);
        }
        foreach ($document['summary'] as $label => $value) {
            $section->addText($label.': '.$value, ['bold' => true, 'size' => 9]);
        }
        foreach ($document['certificates'] as $index => $certificate) {
            if ($index > 0) {
                $section->addPageBreak();
                $section->addText($document['title'], ['bold' => true, 'size' => 14], ['alignment' => 'center']);
                foreach ($document['metadata'] as $label => $value) $section->addText($label.': '.$value, ['size' => 9]);
            }
            foreach ($certificate as $paragraph) $section->addText($paragraph, [], ['spaceAfter' => 180]);
            $this->signatures($section);
        }
        if ($document['columns'] !== []) {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
            $table->addRow(null, ['tblHeader' => true]);
            $width = (int) (14000 / count($document['columns']));
            foreach ($document['columns'] as $column) {
                $table->addCell($width, ['bgColor' => 'E8EDF5'])->addText($column, ['bold' => true, 'size' => 8]);
            }
            foreach ($document['rows'] as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ($row as $value) $table->addCell($width)->addText((string) ($value ?? '-'), ['size' => 8]);
            }
            $this->signatures($section);
        }
        $section->addFooter()->addPreserveText('DMSGTA | Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => 'center']);
        $path = tempnam(sys_get_temp_dir(), 'dmsgta_');
        if ($path === false) throw new \RuntimeException('Unable to create an export temporary file.');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $bytes = file_get_contents($path);
            if ($bytes === false || $bytes === '') throw new \RuntimeException('The DOCX generator returned an empty document.');

            return $bytes;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    private function signatures($section): void
    {
        $section->addTextBreak(2);
        $section->addText('Prepared by: ___________________________    Guidance Staff');
        $section->addText('Approved by: ___________________________    Guidance Counselor');
    }

    private function csv(array $document): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) throw new \RuntimeException('Unable to create a CSV stream.');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $write = static function (array $row) use ($stream): void {
                // Prevent spreadsheet formula execution in user-supplied cells.
                $row = array_map(static function ($value) {
                    $value = (string) ($value ?? '-');
                    return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $row);
                if (fputcsv($stream, $row, ',', '"', '') === false) throw new \RuntimeException('CSV write failed.');
            };
            $write([self::UNIVERSITY]);
            $write([$document['office'], $document['title']]);
            foreach ($document['metadata'] as $label => $value) $write([$label, $value]);
            foreach ($document['summary'] as $label => $value) $write([$label, $value]);
            $write($document['columns']);
            foreach ($document['rows'] as $row) $write($row);
            foreach ($document['certificates'] as $certificate) foreach ($certificate as $paragraph) $write([$paragraph]);
            $write(['Prepared by: Guidance Staff', 'Approved by: Guidance Counselor']);
            rewind($stream);
            $bytes = stream_get_contents($stream);
            if ($bytes === false) throw new \RuntimeException('CSV read failed.');

            return $bytes;
        } finally {
            fclose($stream);
        }
    }
}
