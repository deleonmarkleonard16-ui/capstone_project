<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

class GuidanceAnalyticsDocxWriter
{
    public function write(PhpWord $word, array $document): void
    {
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $word->setDefaultParagraphStyle(['spaceAfter' => 60]);
        $section = $word->addSection([
            'paperSize' => 'A4', 'marginTop' => 2835, 'marginBottom' => 1701,
            'marginLeft' => 737, 'marginRight' => 737, 'headerHeight' => 120, 'footerHeight' => 120,
        ]);
        $section->addHeader()->addImage(public_path('images/admission/download-letterhead-header.png'), [
            'width' => 520, 'alignment' => 'center',
        ]);
        $footer = $section->addFooter();
        $footer->addPreserveText('DMSGTA | Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => 'center']);
        $footer->addImage(public_path('images/admission/download-letterhead-footer.png'), ['width' => 520, 'alignment' => 'center']);
        $section->addText($document['office'], ['size' => 9], ['alignment' => 'center']);
        $section->addText($document['title'], ['bold' => true, 'size' => 14, 'color' => '17305F'], ['alignment' => 'center']);
        foreach ($document['metadata'] as $label => $value) {
            $section->addText($label.': '.$value);
        }
        if ($document['reportType'] === 'summary') {
            foreach ($document['reportSections'] as $title => $rows) {
                $section->addText($title, ['bold' => true, 'color' => '17305F'], ['spaceBefore' => 160, 'keepNext' => true]);
                $table = $this->table($section, ['Metric', 'Value']);
                foreach ($rows as $row) {
                    $label = $title === 'Overview' ? $row[0] : \Illuminate\Support\Str::after($row[0], ' / ');
                    $this->row($table, [$label, $row[1] ?? '-']);
                }
            }
        } else {
            foreach ($document['rows'] as $index => $row) {
                $section->addText('Record '.($index + 1).' — '.$row[0], ['bold' => true, 'color' => '17305F'], ['spaceBefore' => 160, 'keepNext' => true]);
                $table = $this->table($section, ['Field', 'Details']);
                foreach ($document['columns'] as $columnIndex => $column) {
                    $this->row($table, [$column, $row[$columnIndex] ?? '-']);
                }
            }
        }
        $section->addTextBreak();
        $section->addText('Prepared by: ___________________________    Guidance Staff');
        $section->addText('Approved by: ___________________________    Guidance Counselor');
    }

    private function table($section, array $headings)
    {
        $table = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct', 'borderSize' => 6, 'borderColor' => 'B7C1CE', 'cellMargin' => 80]);
        $this->row($table, $headings, true);

        return $table;
    }

    private function row($table, array $values, bool $heading = false): void
    {
        $table->addRow(null, ['cantSplit' => true, 'tblHeader' => $heading]);
        foreach ($values as $index => $value) {
            $table->addCell($index === 0 ? 6500 : 3930, $heading ? ['bgColor' => 'EDF1F6'] : [])
                ->addText((string) $value, ['bold' => $heading, 'size' => 9]);
        }
    }
}
