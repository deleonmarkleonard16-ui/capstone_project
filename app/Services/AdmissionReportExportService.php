<?php

namespace App\Services;

use App\Models\AdmissionCycle;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AdmissionReportExportService
{
    public const TITLES = [
        'psu-cat-qualifiers' => 'PSU-CAT Master Roster',
        'interview-qualifiers' => 'Interview Qualifiers',
        'interview-non-qualifiers' => 'Not Qualified for Interview',
        'final-enrollment-qualified' => 'Final List - Qualified for Enrollment',
        'final-enrollment-waitlisted' => 'Waitlisted / Not Qualified for Enrollment',
    ];

    public function render(AdmissionCycle $cycle, string $type, array $roster, string $format, ?string $batch): string
    {
        return $format === 'docx'
            ? $this->docx($cycle, $type, $roster, $batch)
            : $this->pdf($cycle, $type, $roster, $batch);
    }

    private function pdf(AdmissionCycle $cycle, string $type, array $roster, ?string $batch): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.admission.reports.document', compact('cycle', 'type', 'roster', 'batch'))->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        return $pdf->output();
    }

    private function docx(AdmissionCycle $cycle, string $type, array $roster, ?string $batch): string
    {
        $word = new PhpWord();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(8);
        $section = $word->addSection(['orientation' => 'landscape', 'marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 500, 'marginRight' => 500]);
        $header = $section->addHeader();
        $header->addText('Pangasinan State University - San Carlos Campus', ['bold' => true, 'size' => 11], ['alignment' => 'center']);
        $header->addText('Guidance & Admission Office', ['size' => 9], ['alignment' => 'center']);
        $section->addText(self::TITLES[$type], ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $section->addText('Admission Cycle: '.$cycle->displayName.' | Academic Year: '.$cycle->academic_year.' | Batch: '.($batch ?: 'All').' | Records: '.$roster['count'], ['size' => 8], ['alignment' => 'center']);
        $section->addText('Generated: '.now()->format('F j, Y g:i A'), ['size' => 8], ['alignment' => 'center']);
        $columns = $this->columns($type);
        foreach ($roster['groups'] as $course => $items) {
            $section->addTextBreak();
            $meta = $type === 'interview-qualifiers' || $type === 'interview-non-qualifiers'
                ? ' | Top limit: '.($roster['cutoffs'][$course] ?? 'All')
                : (str_starts_with($type, 'final-') ? ' | Seats: '.($roster['quotas'][$course] ?? 0) : '');
            $section->addText($course.' - '.count($items).' applicant(s)'.$meta, ['bold' => true, 'size' => 9]);
            $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'B7C2D4', 'cellMargin' => 35]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach ($columns as $column) $table->addCell(1200, ['bgColor' => 'E8EDF5'])->addText($column, ['bold' => true, 'size' => 7]);
            foreach ($items as $item) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ($this->values($item, $type) as $value) $table->addCell(1200)->addText($value, ['size' => 7]);
            }
        }
        if ($roster['count'] === 0) $section->addText('No applicants matched this report.');
        $section->addFooter()->addPreserveText('DMSGTA | Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => 'center']);
        $path = tempnam(sys_get_temp_dir(), 'admission_report_');
        if ($path === false) throw new \RuntimeException('Could not create a temporary report file.');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $bytes = file_get_contents($path);
            if ($bytes === false) throw new \RuntimeException('Could not read the generated report.');
            return $bytes;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    public function columns(string $type): array
    {
        $columns = ['Rank', 'Application Number', 'Full Name', 'Course Choice', 'Sex', '4Ps/OSY/IP/PWD/SP', 'CMFL', 'GWA', 'Exam Score', 'Stanine', 'Interview Score', 'Total Score'];
        if ($type !== 'psu-cat-qualifiers') $columns[] = 'Result';
        return $columns;
    }

    public function values(array $item, string $type): array
    {
        $a = $item['applicant'];
        $values = [(string) $item['rank'], (string) $a->application_number, $a->full_name, (string) $a->course_choice,
            (string) ($a->sex ?? '-'), (string) ($a->special_group ?? '-'), (string) ($a->cmfl ?? '-'),
            $this->score($a->gwa), $this->score($a->exam_score), (string) ($a->stanine_score ?? '-'),
            $this->score($a->interview_score), $this->score($a->total_score)];
        if ($type !== 'psu-cat-qualifiers') $values[] = $item['status'];
        return $values;
    }

    private function score($value): string
    {
        return $value === null ? '-' : number_format((float) $value, 2);
    }
}
