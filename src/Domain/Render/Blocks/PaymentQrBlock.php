<?php

namespace CargoDocsStudio\Domain\Render\Blocks;

use CargoDocsStudio\Domain\Render\QrService;

class PaymentQrBlock
{
    private QrService $qr;

    public function __construct()
    {
        $this->qr = new QrService();
    }

    public function buildBitcoin(string $address, ?string $amountBtc = null, ?string $label = null): array
    {
        $uri = $this->qr->buildBitcoinUri($address, $amountBtc, $label);

        return [
            'uri' => $uri,
            'data_uri' => $this->qr->generateQrDataUri($uri, 5),
        ];
    }
}
