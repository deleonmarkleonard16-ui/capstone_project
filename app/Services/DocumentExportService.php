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
                default => view($document['view'] ?? 'exports.document', $document)->render(),
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
        $pdf->loadHtml(view($document['view'] ?? 'exports.document', $document)->render(), 'UTF-8');
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
        if (($document['view'] ?? null) === 'exports.guidance-analytics') {
            app(GuidanceAnalyticsDocxWriter::class)->write($word, $document);

            return $this->wordBytes($word);
        }
        if (($document['view'] ?? null) === 'guidance.psychological-report') {
            app(PsychologicalReportDocxWriter::class)->write($word, $document);

            return $this->wordBytes($word);
        }
        if (($document['view'] ?? null) === 'guidance.career-assessment-report') {
            app(CareerAssessmentReportDocxWriter::class)->write($word, $document);

            return $this->wordBytes($word);
        }
        if (! empty($document['certificates']) && ! empty($document['certificateBrand'])) {
            app(DocumentCertificateDocxWriter::class)->write($word, $document);

            return $this->wordBytes($word);
        }
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->setDefaultParagraphStyle(['spaceAfter' => 60]);
        $portrait = $document['orientation'] === 'portrait';
        $contentWidth = $portrait ? 10432 : 15364;
        $imageWidth = $contentWidth / 20;
        // The official images include an 11px white border on each side.
        $contentInset = (int) round($contentWidth * 11 / 1024);
        $tableWidth = $contentWidth - 2 * $contentInset;
        $bodyParagraph = ['indentation' => ['left' => $contentInset, 'right' => $contentInset]];
        $section = $word->addSection([
            'paperSize' => 'A4',
            'orientation' => $document['orientation'],
            'marginTop' => $portrait ? 2835 : 4252,
            'marginBottom' => $portrait ? 1701 : 2551,
            'marginLeft' => 737,
            'marginRight' => 737,
            'headerHeight' => 120,
            'footerHeight' => 120,
        ]);
        $header = $section->addHeader();
        $letterheadHeader = public_path('images/admission/download-letterhead-header.png');
        if (is_file($letterheadHeader)) {
            $header->addImage($letterheadHeader, ['width' => $imageWidth, 'alignment' => 'center']);
            $header->addText(self::UNIVERSITY, ['size' => 1, 'color' => 'FFFFFF'], ['spaceAfter' => 0]);
        } else {
            $header->addText(self::UNIVERSITY, ['bold' => true, 'color' => '17305F'], ['alignment' => 'center']);
            $header->addText($document['office'], [], ['alignment' => 'center']);
        }
        $section->addText($document['office'], ['size' => 9], ['alignment' => 'center']);
        $section->addText($document['title'], ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        foreach ($document['metadata'] as $label => $value) {
            $section->addText($label.': '.$value, ['size' => 9], $bodyParagraph);
        }
        foreach ($document['summary'] as $label => $value) {
            $section->addText($label.': '.$value, ['bold' => true, 'size' => 9], $bodyParagraph);
        }
        foreach ($document['certificates'] as $index => $certificate) {
            if ($index > 0) {
                $section->addPageBreak();
                $section->addText($document['title'], ['bold' => true, 'size' => 14], ['alignment' => 'center']);
                foreach ($document['metadata'] as $label => $value) $section->addText($label.': '.$value, ['size' => 9], $bodyParagraph);
            }
            $section->addText('This certifies that '.$certificate['name'].', student number '.$certificate['student_number'].', enrolled in '.$certificate['course'].', '.$certificate['statement'], [], ['spaceAfter' => 180]);
            $section->addText('Purpose: '.$certificate['purpose'], ['bold' => true]);
            $section->addText('Issued: '.$certificate['issued_at'], ['bold' => true]);
            $section->addTextBreak(3);
            $section->addText('___________________________', [], ['alignment' => 'right']);
            $section->addText('Guidance Counselor', [], ['alignment' => 'right']);
        }
        if ($document['columns'] !== []) {
            // Match the visible letterhead edges rather than its white image border.
            $table = $section->addTable(['layout' => 'fixed', 'alignment' => 'center', 'width' => $tableWidth, 'unit' => 'dxa', 'borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
            $table->addRow(null, ['tblHeader' => true]);
            $width = (int) ($tableWidth / count($document['columns']));
            foreach ($document['columns'] as $column) {
                $table->addCell($width, ['bgColor' => 'E8EDF5'])->addText($column, ['bold' => true, 'size' => 8]);
            }
            foreach ($document['rows'] as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ($row as $value) $table->addCell($width)->addText((string) ($value ?? '-'), ['size' => 8]);
            }
            $this->signatures($section, $bodyParagraph);
        }
        $footer = $section->addFooter();
        $letterheadFooter = public_path('images/admission/download-letterhead-footer.png');
        if (is_file($letterheadFooter)) {
            $footer->addImage($letterheadFooter, ['width' => $imageWidth, 'alignment' => 'center']);
        }
        $footer->addPreserveText('DMSGTA | Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => 'center']);

        return $this->wordBytes($word);
    }

    private function wordBytes(PhpWord $word): string
    {
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

    private function signatures($section, array $paragraphStyle = []): void
    {
        $section->addTextBreak(2);
        $section->addText('Prepared by: ___________________________    Guidance Staff', [], $paragraphStyle);
        $section->addText('Approved by: ___________________________    Guidance Counselor', [], $paragraphStyle);
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
            foreach ($document['certificates'] as $certificate) {
                $write(['Name', $certificate['name']]);
                $write(['Student Number', $certificate['student_number']]);
                $write(['Course', $certificate['course']]);
                $write(['Statement', $certificate['statement']]);
                $write(['Purpose', $certificate['purpose']]);
                $write(['Issued', $certificate['issued_at']]);
            }
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
