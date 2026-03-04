<?php

namespace CargoDocsStudio\Domain\Render\Blocks;

use CargoDocsStudio\Domain\Render\QrService;

class TrackingQrBlock
{
    private QrService $qr;

    public function __construct()
    {
        $this->qr = new QrService();
    }

    public function build(string $trackingUrl): array
    {
        return [
            'url' => $trackingUrl,
            'data_uri' => $this->qr->generateQrDataUri($trackingUrl, 5),
        ];
    }
}
