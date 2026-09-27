<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class GuidanceArchivePdfService
{
    public function render(iterable $appointments): string
    {
        $cache = storage_path('app/private/pdf-cache');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', resource_path('views/guidance'));
        $options->set('fontCache', $cache);
        $options->set('tempDir', $cache);
        $options->set('defaultFont', 'DejaVu Sans');

        $html = view('guidance.archive-print', compact('appointments'))->render();

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        $output = $pdf->output();
        if (!$output || strlen($output) === 0) {
            throw new \RuntimeException('Dompdf returned an empty document. Check the HTML template and font cache directory.');
        }

        return $output;
    }
}
