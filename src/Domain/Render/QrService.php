<?php

namespace CargoDocsStudio\Domain\Render;

class QrService
{
    public function buildBitcoinUri(string $address, ?string $amountBtc = null, ?string $label = null): string
    {
        $address = trim($address);
        $params = [];

        if (!empty($amountBtc)) {
            $params['amount'] = $amountBtc;
        }

        if (!empty($label)) {
            $params['label'] = $label;
        }

        return 'bitcoin:' . $address . (!empty($params) ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public function generateQrDataUri(string $payload, int $size = 6): string
    {
        $this->maybeLoadQrLibrary();

        if (!class_exists('QRcode')) {
            return '';
        }

        $size = max(2, min($size, 10));

        ob_start();
        \QRcode::png($payload, null, QR_ECLEVEL_M, $size, 2);
        $imageData = ob_get_clean();

        if (!$imageData) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    private function maybeLoadQrLibrary(): void
    {
        if (class_exists('QRcode')) {
            return;
        }

        $candidatePaths = [
            CDS_PLUGIN_DIR . 'lib/phpqrcode/qrlib.php',
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('QRcode')) {
                    return;
                }
            }
        }
    }
}
