<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

class CareerAssessmentReportDocxWriter
{
    public function write(PhpWord $word, array $document): void
    {
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(8);
        $word->setDefaultParagraphStyle(['spaceAfter' => 35]);
        $section = $word->addSection(['paperSize' => 'A4', 'marginTop' => 480, 'marginBottom' => 480, 'marginLeft' => 737, 'marginRight' => 737]);
        $brand = $document['brand'] ?? [];

        $letterhead = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct', 'borderSize' => 0, 'borderBottomSize' => 14, 'borderBottomColor' => '17305F']);
        $letterhead->addRow(null, ['cantSplit' => true]);
        $brandCell = $letterhead->addCell(8800, ['bgColor' => 'F6DC4B', 'valign' => 'center']);
        $brandCell->addText('Republic of the Philippines', ['size' => 7], ['alignment' => 'center', 'spaceAfter' => 0]);
        if (! empty($brand['universityTitlePath'])) {
            $brandCell->addImage($brand['universityTitlePath'], ['width' => 310, 'height' => 31.2, 'alignment' => 'center']);
        } else {
            $brandCell->addText('Pangasinan State University', ['bold' => true, 'size' => 17, 'color' => '17305F'], ['alignment' => 'center']);
        }
        $brandCell->addText($brand['campus'] ?? 'San Carlos City, Pangasinan', ['bold' => true, 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
        $brandCell->addText('Website: '.($brand['website'] ?? 'www.psu.edu.ph').'    Telephone: '.($brand['telephone'] ?? '(075) 206 0802').'    Telefax: '.($brand['telefax'] ?? '(075) 522 4201 / 4057'), ['size' => 6], ['alignment' => 'center', 'spaceAfter' => 0]);
        $sealCell = $letterhead->addCell(1600, ['bgColor' => 'F6DC4B', 'valign' => 'center']);
        if (! empty($brand['sealPath'])) $sealCell->addImage($brand['sealPath'], ['width' => 55, 'height' => 55, 'alignment' => 'center']);

        $section->addText('OFFICE OF ADMISSION AND GUIDANCE SERVICES', ['bold' => true, 'size' => 9, 'underline' => 'single'], ['alignment' => 'center', 'spaceBefore' => 75, 'spaceAfter' => 35]);
        $section->addText('CAREER ASSESSMENT', ['bold' => true, 'size' => 10], ['alignment' => 'center', 'spaceAfter' => 100]);

        $profile = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
        foreach (array_chunk($document['fields'], 2, true) as $pair) {
            $profile->addRow(null, ['cantSplit' => true]);
            foreach ($pair as $label => $value) $profile->addCell(5100)->addText($label.': '.$value, ['size' => 8]);
        }

        $section->addText('I. Summary of Career Interest Profile', ['bold' => true, 'size' => 9], ['spaceBefore' => 100, 'keepNext' => true]);
        $section->addText('RIASEC Interest Code: '.($document['topCode'] ?: 'Pending counselor review'), ['bold' => true, 'size' => 8]);
        $table = $section->addTable(['borderSize' => 5, 'borderColor' => '222222', 'cellMargin' => 35, 'layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        $table->addCell(1700, ['bgColor' => 'F2F2F2'])->addText('Interest Area', ['bold' => true, 'size' => 7]);
        $table->addCell(3800, ['bgColor' => 'F2F2F2'])->addText('Description', ['bold' => true, 'size' => 7]);
        foreach ([1, 2, 3, 4, 5] as $rating) $table->addCell(430, ['bgColor' => 'F2F2F2'])->addText((string) $rating, ['bold' => true, 'size' => 7], ['alignment' => 'center']);
        $table->addCell(2200, ['bgColor' => 'F2F2F2'])->addText('Interpretation', ['bold' => true, 'size' => 7]);
        foreach ($document['rows'] as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell(1700)->addText(strtoupper($row['label']), ['bold' => true, 'size' => 7]);
            $table->addCell(3800)->addText($row['description'], ['size' => 7]);
            foreach ([1, 2, 3, 4, 5] as $rating) {
                $table->addCell(430, ['vAlign' => 'center'])->addText($row['selected'] === $rating ? '[X]' : '[ ]', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
            }
            $table->addCell(2200)->addText($row['interpretation'], ['size' => 7]);
        }
        $legend = [];
        foreach ($document['legend'] as $rating => $label) $legend[] = $rating.' - '.$label;
        $section->addText('Legend: '.implode('     ', $legend), ['size' => 7], ['spaceBefore' => 50]);

        $section->addText('II. Remarks:', ['bold' => true, 'size' => 9], ['spaceBefore' => 110, 'keepNext' => true]);
        $section->addText($document['remarks']);
        if (! $document['complete']) $section->addText('Some ratings are unavailable; counselor review is required.', ['italic' => true, 'size' => 7]);
        $section->addText('III. Recommendation:', ['bold' => true, 'size' => 9], ['spaceBefore' => 100, 'keepNext' => true]);
        $section->addText($document['recommendation']);
        $section->addText('Career interest results support educational and career exploration. Discuss the profile with the Guidance Counselor for further interpretation.', ['size' => 7], ['spaceBefore' => 75]);

        $signatures = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct']);
        $signatures->addRow(null, ['cantSplit' => true]);
        $administered = $signatures->addCell(5100);
        $administered->addText('Administered and interpreted by:');
        $administered->addText('____________________________', [], ['spaceBefore' => 120]);
        $administered->addText('(Position)');
        $reviewed = $signatures->addCell(5100);
        $reviewed->addText('Reviewed and certified by:');
        $reviewed->addText($document['counselor'], [], ['spaceBefore' => 120]);
        $reviewed->addText('Guidance Counselor');
        $section->addText('*NOT VALID WITHOUT UNIVERSITY SEAL', ['bold' => true, 'size' => 7], ['spaceBefore' => 100]);
        $section->addText('O.R. #: '.$document['orNumber'].'    Date: '.$document['orDate'].'    Doc. Stamp Tax Paid', ['size' => 7]);
        $footer = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct', 'borderSize' => 0]);
        $footer->addRow();
        $footer->addCell(10400, ['bgColor' => 'F6DC4B'])->addText('Pangasinan State University · San Carlos Campus', ['bold' => true, 'size' => 7, 'color' => '17305F'], ['alignment' => 'center']);
    }
}
