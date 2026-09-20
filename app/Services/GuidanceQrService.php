<?php

namespace App\Services;

class GuidanceQrService
{
    public function dataUri(string $url): string
    {
        require_once app_path('Support/Qr/qrcode.php');
        $qr = \QRCode::getMinimumQRCode($url, QR_ERROR_CORRECT_LEVEL_M);
        $size = $qr->getModuleCount();
        $edge = $size + 8;
        $path = '';
        for ($row = 0; $row < $size; $row++) {
            for ($column = 0; $column < $size; $column++) {
                if ($qr->isDark($row, $column)) {
                    $path .= 'M'.($column + 4).' '.($row + 4).'h1v1h-1z';
                }
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$edge.' '.$edge.'" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><path d="'.$path.'" fill="black"/></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
