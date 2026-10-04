<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

class PsychologicalReportDocxWriter
{
    public function write(PhpWord $word, array $document): void
    {
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $word->setDefaultParagraphStyle(['spaceAfter' => 60]);
        $section = $word->addSection(['paperSize' => 'A4', 'marginTop' => 794, 'marginBottom' => 794, 'marginLeft' => 907, 'marginRight' => 907]);
        $section->addText('OFFICE OF ADMISSION AND GUIDANCE SERVICES', ['bold' => true, 'size' => 11], ['alignment' => 'center']);
        $section->addText('PSYCHOLOGICAL ASSESSMENT', ['bold' => true, 'size' => 11], ['alignment' => 'center', 'spaceAfter' => 180]);
        $profile = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
        foreach (array_chunk($document['fields'], 2, true) as $pair) {
            $profile->addRow(null, ['cantSplit' => true]);
            foreach ($pair as $label => $value) {
                $profile->addCell(5000)->addText($label.': '.$value);
            }
        }
        foreach ($document['matrices'] as $matrix) {
            $section->addText($matrix['title'], ['bold' => true], ['spaceBefore' => 120, 'keepNext' => true]);
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '222222', 'cellMargin' => 60, 'layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
            $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
            $table->addCell(4200)->addText('SCALE', ['bold' => true], ['alignment' => 'center']);
            $width = (int) (5600 / count($matrix['columns']));
            foreach ($matrix['columns'] as $column) {
                $table->addCell($width)->addText($column, ['bold' => true, 'size' => 8], ['alignment' => 'center']);
            }
            foreach ($matrix['rows'] as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                $cell = $table->addCell(4200);
                $cell->addText($row['label'], ['bold' => true]);
                if ($row['description']) {
                    $cell->addText('('.$row['description'].')', ['size' => 8]);
                }
                foreach ($matrix['columns'] as $column) {
                    $table->addCell($width, ['vAlign' => 'center'])->addText($row['selected'] === $column ? '[X]' : '', ['bold' => true], ['alignment' => 'center']);
                }
            }
        }
        // IV. Remarks
        $section->addText('IV. Remarks:', ['bold' => true], ['spaceBefore' => 120, 'keepNext' => true]);
        if (! empty($document['remarks'])) {
            $section->addText($document['remarks'], [], ['spaceAfter' => 60]);
        } else {
            $section->addText('________________________________________________________________________', ['size' => 9], ['spaceAfter' => 40]);
            $section->addText('________________________________________________________________________', ['size' => 9], ['spaceAfter' => 60]);
        }
        // V. Recommendation
        $section->addText('V. Recommendation:', ['bold' => true], ['spaceBefore' => 120, 'keepNext' => true]);
        foreach (PsychologicalReportService::RECOMMENDATIONS as $option) {
            $section->addText(($document['recommendation'] === $option ? '[X]' : '[ ]').' '.$option);
        }
        if (! $document['complete']) {
            $section->addText('Incomplete assessment: blank cells indicate unavailable results. Counselor review required.', ['size' => 8]);
        }
        $section->addText(PsychologicalReportService::NOTE, ['size' => 8], ['spaceBefore' => 100, 'spaceAfter' => 180]);
        $signatures = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
        $signatures->addRow(null, ['cantSplit' => true]);
        $administered = $signatures->addCell(5000);
        $administered->addText('Administered and interpreted by:');
        $administered->addText('____________________________', [], ['spaceBefore' => 180]);
        $administered->addText('____________________________ (Position)');
        $reviewed = $signatures->addCell(5000);
        $reviewed->addText('Reviewed and certified by:');
        $reviewed->addText($document['counselor'] ?: '____________________________', [], ['spaceBefore' => 180]);
        $reviewed->addText('Guidance Counselor');
        $section->addText('*NOT VALID WITHOUT UNIVERSITY SEAL', ['bold' => true, 'size' => 8], ['spaceBefore' => 180]);
        $section->addText('O.R. #: '.$document['orNumber'].'    Date: '.$document['orDate'].'    Doc. Stamp Tax Paid', ['size' => 8]);
    }
}
