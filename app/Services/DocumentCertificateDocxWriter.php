<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

class DocumentCertificateDocxWriter
{
    public function write(PhpWord $word, array $document): void
    {
        $word->setDefaultFontName('Georgia');
        $word->setDefaultFontSize(11);
        $word->setDefaultParagraphStyle(['spaceAfter' => 120, 'lineHeight' => 1.25]);
        $section = $word->addSection(['paperSize' => 'A4', 'marginTop' => 430, 'marginBottom' => 430, 'marginLeft' => 737, 'marginRight' => 737]);
        $brand = $document['certificateBrand'] ?? [];

        foreach ($document['certificates'] as $index => $certificate) {
            if ($index > 0) $section->addPageBreak();
            $header = $section->addTable(['layout' => 'fixed', 'width' => 100, 'unit' => 'pct', 'borderSize' => 0, 'borderBottomSize' => 16, 'borderBottomColor' => 'D1B900']);
            $header->addRow(null, ['cantSplit' => true]);
            $seal = $header->addCell(1800, ['bgColor' => 'F2D83D', 'valign' => 'center']);
            if (! empty($brand['sealPath'])) $seal->addImage($brand['sealPath'], ['width' => 67, 'height' => 67, 'alignment' => 'center']);
            $center = $header->addCell(6800, ['bgColor' => 'F2D83D', 'valign' => 'center']);
            $center->addText('Republic of the Philippines', ['size' => 7], ['alignment' => 'center', 'spaceAfter' => 0]);
            if (! empty($brand['universityTitlePath'])) {
                $center->addImage($brand['universityTitlePath'], ['width' => 295, 'height' => 30, 'alignment' => 'center']);
            } else {
                $center->addText('Pangasinan State University', ['bold' => true, 'size' => 17, 'color' => '17305F'], ['alignment' => 'center']);
            }
            $center->addText($brand['campus'] ?? 'SAN CARLOS CITY CAMPUS', ['bold' => true, 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            $center->addText($brand['address'] ?? 'Roxas Blvd., San Carlos City, Pangasinan 2420', ['size' => 7], ['alignment' => 'center', 'spaceAfter' => 0]);
            $center->addText('Website: '.($brand['website'] ?? 'www.psu.edu.ph').'   Tel. '.($brand['telephone'] ?? '(075) 206 0802').'   Telefax: '.($brand['telefax'] ?? '(075) 522 4201 / 4057'), ['size' => 6], ['alignment' => 'center', 'spaceAfter' => 0]);
            $bagong = $header->addCell(1800, ['bgColor' => 'F2D83D', 'valign' => 'center']);
            if (! empty($brand['bagongPilipinasPath'])) $bagong->addImage($brand['bagongPilipinasPath'], ['width' => 60, 'height' => 60, 'alignment' => 'center']);

            $section->addText(strtoupper($document['title']), ['bold' => true, 'size' => 15], ['alignment' => 'center', 'spaceBefore' => 480, 'spaceAfter' => 520, 'borderBottomSize' => 4, 'borderBottomColor' => '777777']);
            $section->addText('This is to certify that '.$certificate['name'].' '.$certificate['statement'], [], ['alignment' => 'both', 'indentation' => ['firstLine' => 500], 'spaceAfter' => 260]);
            if (($certificate['student_status'] ?? null) === 'alumni' && ! empty($certificate['course'])) {
                $section->addText('Records in this office show that the graduate completed the degree program in '.$certificate['course'].'.', [], ['alignment' => 'both', 'indentation' => ['firstLine' => 500], 'spaceAfter' => 260]);
            }
            if (str_contains(strtolower($document['title']), 'good moral')) {
                $section->addText('Records in this office show that the student has not violated University rules and regulations and is known to possess good moral character.', [], ['alignment' => 'both', 'indentation' => ['firstLine' => 500], 'spaceAfter' => 260]);
            }
            $section->addText('This certification is issued upon the request of '.$certificate['name'].' for '.strtoupper($certificate['purpose']).' purposes only.', [], ['alignment' => 'both', 'indentation' => ['firstLine' => 500], 'spaceAfter' => 260]);
            $section->addText('Issued this '.$certificate['issued_day'].' day of '.$certificate['issued_month_year'].'.', [], ['alignment' => 'center', 'spaceBefore' => 1200, 'spaceAfter' => 1600]);

            $section->addText($brand['counselor'] ?? 'NOEMI C. CARLOS, RGC', ['bold' => true, 'underline' => 'single'], ['alignment' => 'right', 'spaceAfter' => 0]);
            $section->addText('Guidance Counselor', [], ['alignment' => 'right', 'spaceAfter' => 1100]);
            $section->addText('*Not valid without University Seal', ['italic' => true, 'size' => 8], ['spaceAfter' => 50]);
            $section->addText('O.R. #: '.$certificate['or_number'].'    Date: '.$certificate['or_date'], ['size' => 8], ['spaceAfter' => 50]);
            $section->addText('Doc. Stamp Tax Paid', ['size' => 8], ['spaceAfter' => 100]);
            if (! empty($brand['footerPath'])) $section->addImage($brand['footerPath'], ['width' => 480, 'height' => 40, 'alignment' => 'center']);
        }
    }
}
