<?php

namespace CargoDocsStudio\Domain\Render;

use CargoDocsStudio\Domain\Render\Composer\HtmlComposerFacade;

class HtmlComposer
{
    private HtmlComposerFacade $facade;

    public function __construct(?HtmlComposerFacade $facade = null)
    {
        $this->facade = $facade ?: new HtmlComposerFacade();
    }

    public function composeInvoice(array $payload, array $trackingBlock, ?array $paymentBlock = null, array $templateConfig = []): string
    {
        return $this->facade->composeInvoice($payload, $trackingBlock, $paymentBlock, $templateConfig);
    }
}
