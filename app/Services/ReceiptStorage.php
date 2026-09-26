<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ReceiptStorage
{
    /** @return array{receipt_data: string, receipt_mime_type: string, receipt_original_name: string} */
    public static function payload(UploadedFile $file): array
    {
        $data = $file->get();
        abort_unless($data !== false && $data !== '', 503, 'Receipt could not be read. Please retry.');

        return [
            'receipt_data' => $data,
            'receipt_mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'receipt_original_name' => $file->getClientOriginalName() ?: 'receipt',
        ];
    }

    public static function response(object $receipt): Response
    {
        abort_unless($receipt->hasReceipt(), 404);

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($receipt->receipt_original_name ?: 'receipt'));

        return response($receipt->receipt_data, 200, [
            'Content-Type' => $receipt->receipt_mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
