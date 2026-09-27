<?php

namespace App\Services;

use App\Models\AdmissionCycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Table as TableStyle;

class AdmissionDocxService
{
    /**
     * Guard system dependencies (ZipArchive and XML extensions).
     *
     * @throws \RuntimeException
     */
    public function verifyDependencies(): void
    {
        if (!extension_loaded('zip') && !class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is not enabled on this server. Please ensure php-zip / libzip is installed.');
        }

        if (!extension_loaded('xml') && !class_exists(\XMLWriter::class)) {
            throw new \RuntimeException('PHP XMLWriter extension is not enabled on this server.');
        }
    }

    /**
     * Render the Admission Masterlist / Evaluation report to DOCX bytes.
     *
     * @param  AdmissionCycle  $cycle
     * @param  Collection  $rows
     * @param  string  $type  ('summary' | 'qualified' | 'not-qualified')
     * @return string  Binary DOCX content
     *
     * @throws \RuntimeException
     */
    public function render(AdmissionCycle $cycle, Collection $rows, string $type = 'summary'): string
    {
        $this->verifyDependencies();

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        // Landscape layout with 0.5 in margins (720 twips)
        $section = $phpWord->addSection([
            'orientation'  => 'landscape',
            'marginTop'    => 720,
            'marginBottom' => 720,
            'marginLeft'   => 720,
            'marginRight'  => 720,
        ]);

        // Header Section
        $section->addText(
            'REPUBLIC OF THE PHILIPPINES · PANGASINAN STATE UNIVERSITY',
            ['bold' => true, 'size' => 9, 'color' => '555555'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 20]
        );
        $section->addText(
            'San Carlos City Campus — Guidance and Counseling Office',
            ['bold' => true, 'size' => 12, 'color' => '0D1B3E'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );

        $typeTitle = match ($type) {
            'qualified'     => 'QUALIFIED APPLICANTS REPORT',
            'not-qualified' => 'NOT QUALIFIED APPLICANTS REPORT',
            default         => 'ADMISSION MASTERLIST & EVALUATION REPORT',
        };

        $section->addText(
            'PSU-CAT ' . $typeTitle,
            ['bold' => true, 'size' => 13, 'color' => '0D1B3E'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );

        $cycleName = $cycle->name ?? $cycle->cycle_name ?? 'Active Cycle';
        $academicYear = $cycle->academic_year ?? 'N/A';
        $metaText = "Admission Cycle: {$cycleName}   |   Academic Year: {$academicYear}   |   Generated: " . now()->format('F j, Y g:i A');
        $section->addText(
            $metaText,
            ['size' => 9, 'italic' => true, 'color' => '666666'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 160]
        );

        // Aggregate Summary Metrics
        $totalCount    = $rows->count();
        $qualCount     = $rows->where('qualification_status', 'Qualified')->count();
        $notQualCount  = $rows->where('qualification_status', 'Not Qualified')->count();
        $pendingCount  = $rows->whereNotIn('qualification_status', ['Qualified', 'Not Qualified'])->count();

        $statsText = "Total Applicants: {$totalCount}    |    Qualified: {$qualCount}    |    Not Qualified: {$notQualCount}    |    Pending: {$pendingCount}";
        $section->addText(
            $statsText,
            ['bold' => true, 'size' => 9.5, 'color' => '0D1B3E'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 160]
        );

        // Define Table Styles
        $tableStyleName = 'AdmissionMasterlistTable';
        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => 'CCCCCC',
            'cellMargin'  => 50,
            'alignment'   => JcTable::CENTER,
        ];
        $phpWord->addTableStyle($tableStyleName, $tableStyle);

        $table = $section->addTable($tableStyleName);

        // Header Row
        $table->addRow(320, ['tblHeader' => true, 'cantSplit' => true]);
        $headers = [
            ['Rank', 700],
            ['App No.', 1300],
            ['Applicant Name', 2400],
            ['Course', 1200],
            ['Sex', 700],
            ['GWA', 850],
            ['Exam', 850],
            ['Stanine', 850],
            ['Interview', 900],
            ['Total', 950],
            ['Status', 1400],
        ];

        foreach ($headers as [$headerText, $width]) {
            $table->addCell($width, ['bgColor' => '0D1B3E', 'valign' => 'center'])
                ->addText($headerText, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9], ['alignment' => Jc::CENTER]);
        }

        // Applicant Data Rows with Null-Safe Bindings
        $rank = 1;
        foreach ($rows as $row) {
            $bgColor = ($rank % 2 === 0) ? 'F8F9FA' : 'FFFFFF';
            $table->addRow(280, ['cantSplit' => true]);

            $fullName = $row->full_name
                ?? trim(($row->last_name ?? '') . ', ' . ($row->first_name ?? '') . ' ' . ($row->middle_name ? mb_substr($row->middle_name, 0, 1) . '.' : ''))
                ?: 'N/A';

            $appNo     = (string) ($row->application_number ?? '-');
            $course    = (string) ($row->course_choice ?? $row->course ?? 'N/A');
            $sex       = (string) ($row->sex ?? '-');
            $gwa       = ($row->gwa !== null && $row->gwa !== '') ? number_format((float) $row->gwa, 2) : '-';
            $examScore = ($row->exam_score !== null && $row->exam_score !== '') ? number_format((float) $row->exam_score, 2) : '-';
            $stanine   = ($row->stanine_score !== null && $row->stanine_score !== '') ? (string) $row->stanine_score : '-';
            $interview = ($row->interview_score !== null && $row->interview_score !== '') ? number_format((float) $row->interview_score, 2) : '-';
            $total     = ($row->total_score !== null && $row->total_score !== '') ? number_format((float) $row->total_score, 2) : '-';
            $status    = (string) ($row->qualification_status ?? $row->status ?? 'Unassigned');

            $statusColor = match ($status) {
                'Qualified'     => '15803D',
                'Not Qualified' => 'DC2626',
                default         => '92400E',
            };

            $table->addCell(700, ['bgColor' => $bgColor])
                ->addText((string) $rank++, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(1300, ['bgColor' => $bgColor])
                ->addText($appNo, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(2400, ['bgColor' => $bgColor])
                ->addText($fullName, ['size' => 8.5, 'bold' => true], ['alignment' => Jc::START]);

            $table->addCell(1200, ['bgColor' => $bgColor])
                ->addText($course, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(700, ['bgColor' => $bgColor])
                ->addText($sex, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(850, ['bgColor' => $bgColor])
                ->addText($gwa, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(850, ['bgColor' => $bgColor])
                ->addText($examScore, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(850, ['bgColor' => $bgColor])
                ->addText($stanine, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(900, ['bgColor' => $bgColor])
                ->addText($interview, ['size' => 8.5], ['alignment' => Jc::CENTER]);

            $table->addCell(950, ['bgColor' => $bgColor])
                ->addText($total, ['size' => 8.5, 'bold' => true], ['alignment' => Jc::CENTER]);

            $table->addCell(1400, ['bgColor' => $bgColor])
                ->addText($status, ['size' => 8.5, 'bold' => true, 'color' => $statusColor], ['alignment' => Jc::CENTER]);
        }

        // Signatures Block
        $section->addTextBreak(2);
        $sigTable = $section->addTable(['alignment' => JcTable::CENTER, 'cellMargin' => 60]);
        $sigTable->addRow(400, ['cantSplit' => true]);

        $sigTable->addCell(3500)->addText(
            "Prepared by:\n\n\n___________________________\nGuidance Counselor / Psychometrician",
            ['size' => 9, 'bold' => false],
            ['alignment' => Jc::CENTER]
        );
        $sigTable->addCell(3500)->addText(
            "Verified by:\n\n\n___________________________\nCampus Registrar",
            ['size' => 9, 'bold' => false],
            ['alignment' => Jc::CENTER]
        );
        $sigTable->addCell(3500)->addText(
            "Approved by:\n\n\n___________________________\nCampus Administrator",
            ['size' => 9, 'bold' => false],
            ['alignment' => Jc::CENTER]
        );

        // Stream and save file using IOFactory inside try-catch-finally
        $tempFile = tempnam(sys_get_temp_dir(), 'psucat_docx_');
        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempFile);
            $bytes = file_get_contents($tempFile);

            if ($bytes === false || strlen($bytes) === 0) {
                throw new \RuntimeException('Failed to read generated DOCX document stream.');
            }

            return $bytes;
        } catch (\Throwable $e) {
            Log::error('AdmissionDocxService IOFactory export failed', [
                'cycle_id' => $cycle->id,
                'message'  => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('DOCX generation stream failed: ' . $e->getMessage(), 0, $e);
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
