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
        $options->set('chroot', resource_path('views/guidance'));
        $options->set('fontCache', $cache);
        $options->set('tempDir', $cache);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('guidance.archive-print', compact('appointments'))->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        return $pdf->output();
    }
}
