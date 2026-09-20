<?php

namespace App\Services;

use App\Models\AdmissionCycle;
use Illuminate\Support\Collection;
use ZipArchive;

class AdmissionDocxService
{
    public function render(AdmissionCycle $cycle, Collection $rows, string $type): string
    {
        $text = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $paragraph = fn ($value) => '<w:p><w:r><w:t xml:space="preserve">'.$text($value).'</w:t></w:r></w:p>';
        $xml = $paragraph('Pangasinan State University - San Carlos Campus')
            .$paragraph('PSU-CAT '.ucwords(str_replace('-', ' ', $type)).' - '.$cycle->name)
            .$paragraph('Generated '.now()->format('F j, Y'));
        $xml .= '<w:tbl>';
        $line = function (array $values) use ($text): string {
            $cells = '';
            foreach ($values as $value) $cells .= '<w:tc><w:p><w:r><w:t>'.$text($value).'</w:t></w:r></w:p></w:tc>';
            return '<w:tr>'.$cells.'</w:tr>';
        };
        $xml .= $line(['Application #', 'Applicant', 'Course', 'GWA', 'Exam', 'Stanine', 'Interview', 'Total', 'Status']);
        foreach ($rows as $row) $xml .= $line([$row->application_number, $row->full_name, $row->course_choice, $row->gwa, $row->exam_score, $row->stanine_score, $row->interview_score, $row->total_score, $row->qualification_status]);
        $xml .= '</w:tbl>';
        $file = tempnam(sys_get_temp_dir(), 'psucat_');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$xml.'<w:sectPr><w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($file);
        unlink($file);
        return $bytes;
    }
}
