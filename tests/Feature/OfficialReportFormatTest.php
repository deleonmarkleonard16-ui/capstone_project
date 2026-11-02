<?php

namespace Tests\Feature;

use App\Services\DocumentExportService;
use Tests\TestCase;

class OfficialReportFormatTest extends TestCase
{
    public function test_generic_reports_use_official_letterhead_and_fit_both_paper_orientations(): void
    {
        $exporter = app(DocumentExportService::class);
        foreach (['portrait', 'landscape'] as $orientation) {
            $document = [
                'module' => 'guidance-testing', 'office' => 'Guidance and Counseling Office',
                'title' => 'Testing Completion Report', 'orientation' => $orientation,
                'metadata' => ['Active Filters' => 'All tests'], 'summary' => [],
                'certificates' => [], 'columns' => ['Application Number', 'Name', 'Sex', 'Course Choice', 'GWA', 'Exam Score', 'Stanine', 'Interview', 'Total Score', 'Status'],
                'rows' => array_fill(0, 100, ['CAT-2026-001', 'Sample Student', 'Male', 'BSIT', '90', '72', '9', '85', '88', 'Completed']),
                'format' => 'html', 'autoPrint' => false,
            ];
            $html = $exporter->render($document, 'html');
            $this->assertStringContainsString('Official PSU San Carlos City Campus letterhead', $html);
            $this->assertStringContainsString('data:image/png;base64,', $html);
            $this->assertStringContainsString('screen-official-letterhead header', $html);
            $this->assertStringContainsString('screen-official-letterhead footer', $html);
            $this->assertStringContainsString('display: table-header-group', $html);

            $document['format'] = 'pdf';
            $pdf = $exporter->render($document, 'pdf');
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
            $this->assertStringContainsString('/Subtype /Image', $pdf);

            $path = tempnam(sys_get_temp_dir(), 'official_report_');
            try {
                file_put_contents($path, $exporter->render($document, 'docx'));
                $zip = new \ZipArchive();
                $this->assertTrue($zip->open($path));
                $xml = $zip->getFromName('word/document.xml');
                $this->assertNotFalse(simplexml_load_string($xml));
                $this->assertStringContainsString('w:tblHeader', $xml);
                $dom = new \DOMDocument();
                $dom->loadXML($xml);
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $tableWidth = $xpath->query('//w:tbl/w:tblPr/w:tblW')->item(0);
                $this->assertSame('pct', $tableWidth->getAttribute('w:type'));
                $this->assertSame('5000', $tableWidth->getAttribute('w:w'));
                $gridWidth = 0;
                foreach ($xpath->query('//w:tbl/w:tblGrid/w:gridCol') as $column) {
                    $gridWidth += (int) $column->getAttribute('w:w');
                }
                $this->assertEqualsWithDelta($orientation === 'portrait' ? 10432 : 15364, $gridWidth, count($document['columns']));

                $this->assertStringContainsString('w:left="737"', $xml);
                $this->assertStringContainsString('w:top="'.($orientation === 'portrait' ? '2835' : '4252').'"', $xml);
                $this->assertStringContainsString('v:imagedata', $zip->getFromName('word/header1.xml'));
                $this->assertStringContainsString('v:imagedata', $zip->getFromName('word/footer1.xml'));
                $media = [];
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = $zip->getNameIndex($index);
                    if (str_starts_with($name, 'word/media/')) $media[] = hash('sha256', $zip->getFromName($name));
                }
                foreach (['header', 'footer'] as $part) {
                    $this->assertContains(hash_file('sha256', public_path('images/admission/download-letterhead-'.$part.'.png')), $media);
                }
                $zip->close();
            } finally {
                unlink($path);
            }
            $csv = $exporter->render($document, 'csv');
            $this->assertStringContainsString('Sample Student', $csv);
            $this->assertStringNotContainsString('base64', $csv);
        }
    }
}

