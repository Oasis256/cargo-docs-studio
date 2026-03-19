<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\FinancialCalculator;
use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;
use CargoDocsStudio\Domain\Render\Composer\Shared\NumberFormatter;

class HtmlComposerFacade
{
    private RenderContextFactory $contextFactory;
    private NumberFormatter $formatter;
    private FinancialCalculator $calculator;
    private ImageResolver $images;
    private InvoiceRenderer $invoiceRenderer;
    private ReceiptRenderer $receiptRenderer;
    private SkrRenderer $skrRenderer;
    private SpaRenderer $spaRenderer;

    public function __construct(
        ?RenderContextFactory $contextFactory = null,
        ?NumberFormatter $formatter = null,
        ?FinancialCalculator $calculator = null,
        ?ImageResolver $images = null,
        ?InvoiceRenderer $invoiceRenderer = null,
        ?ReceiptRenderer $receiptRenderer = null,
        ?SkrRenderer $skrRenderer = null,
        ?SpaRenderer $spaRenderer = null
    ) {
        $this->formatter = $formatter ?: new NumberFormatter();
        $this->calculator = $calculator ?: new FinancialCalculator();
        $this->images = $images ?: new ImageResolver();
        $this->contextFactory = $contextFactory ?: new RenderContextFactory($this->calculator);
        $this->invoiceRenderer = $invoiceRenderer ?: new InvoiceRenderer($this->formatter, $this->images, $this->calculator);
        $this->receiptRenderer = $receiptRenderer ?: new ReceiptRenderer($this->formatter, $this->images);
        $this->skrRenderer = $skrRenderer ?: new SkrRenderer($this->formatter, $this->images);
        $this->spaRenderer = $spaRenderer ?: new SpaRenderer($this->images);
    }

    public function composeInvoice(array $payload, array $trackingBlock, ?array $paymentBlock = null, array $templateConfig = []): string
    {
        $context = $this->contextFactory->build($payload, $templateConfig);
        $docTypeKey = (string) $context['doc_type_key'];
        $theme = $context['theme'];
        $layout = $context['layout'];
        $schema = $context['schema'];
        $computed = $context['computed'];

        if ($docTypeKey === 'invoice') {
            return $this->invoiceRenderer->render($payload, $paymentBlock, $theme);
        }
        if ($docTypeKey === 'receipt') {
            return $this->receiptRenderer->render($payload, $paymentBlock, $computed, $theme);
        }
        if ($docTypeKey === 'skr') {
            return $this->skrRenderer->renderStable($payload, $trackingBlock, $theme);
        }
        if ($docTypeKey === 'spa') {
            return $this->spaRenderer->render($payload);
        }

        $watermarkUrl = $this->images->resolveImageSource((string) ($payload['watermark_url'] ?? ($payload['skr_watermark_url'] ?? '')));
        $trackingCode = esc_html((string) ($payload['tracking_code'] ?? ''));
        $issuedAt = esc_html(current_time('Y-m-d H:i:s'));
        $title = esc_html((string) ($layout['title'] ?? $this->contextFactory->defaultTitleForDocType($docTypeKey)));
        $sections = $layout['sections'];
        $hasTracking = in_array('tracking_qr', $sections, true) && !empty($trackingBlock['data_uri']);
        $hasPayment = in_array('payment_qr', $sections, true) && !empty($paymentBlock['data_uri']);
        $qrRendered = false;
        $showHeader = in_array('header', $sections, true);

        $body = '';
        foreach ($sections as $section) {
            if ($section === 'summary') {
                $body .= $this->renderSummarySection($payload, $schema, $computed);
            } elseif ($section === 'tracking_qr') {
                if (!$qrRendered && ($hasTracking || $hasPayment)) {
                    $body .= $this->renderQrSections($trackingBlock, $paymentBlock, $layout);
                    $qrRendered = true;
                }
            } elseif ($section === 'payment_qr') {
                if (!$qrRendered && ($hasTracking || $hasPayment)) {
                    $body .= $this->renderQrSections($trackingBlock, $paymentBlock, $layout);
                    $qrRendered = true;
                }
            } elseif ($section === 'line_items') {
                $body .= $this->renderLineItemsSection($payload, $computed);
            } elseif ($section === 'footer') {
                $body .= $this->renderFooterSection($trackingCode);
            }
        }

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',sans-serif;color:' . esc_attr($theme['text_color']) . ';font-size:12px;}
.wrap{width:100%;position:relative;}
.doc-watermark{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:56%;max-width:130mm;opacity:0.2;z-index:0;pointer-events:none;}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid ' . esc_attr($theme['primary_color']) . ';padding-bottom:' . (int) $theme['space_sm'] . 'px;margin-bottom:' . (int) $theme['space_md'] . 'px;}
.header,.section{position:relative;z-index:1;}
.title{font-size:24px;font-weight:' . (int) $theme['heading_weight'] . ';margin:0 0 8px 0;color:' . esc_attr($theme['accent_color']) . ';}
.muted{color:#555;font-size:11px;}
.section{margin-bottom:' . (int) $theme['space_md'] . 'px;}
.section h3{margin:0 0 6px 0;font-size:14px;border-bottom:1px solid #ddd;padding-bottom:4px;color:' . esc_attr($theme['accent_color']) . ';}
table{width:100%;border-collapse:collapse;}
th,td{border:1px solid #ddd;padding:' . (int) $theme['table_cell_padding'] . 'px;vertical-align:top;}
th{background:' . esc_attr($theme['table_header_bg']) . ';text-align:left;}
.qr-card{border:1px solid #ddd;padding:10px;margin-bottom:10px;}
.qr-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.qr-align-left{text-align:left;}
.qr-align-right{text-align:right;}
.mono{font-family:monospace;font-size:10px;word-break:break-all;}
</style>
</head>
<body>
<div class="wrap">
  ' . ($watermarkUrl !== '' ? '<img src="' . esc_attr($watermarkUrl) . '" class="doc-watermark" alt="" />' : '') . '
  ' . ($showHeader ? '<div class="header"><div><div class="title">' . $title . '</div><div class="muted">Issued: ' . $issuedAt . '</div><div class="muted">Tracking Code: ' . $trackingCode . '</div></div></div>' : '') . '
  ' . $body . '
</div>
</body>
</html>';
    }

    private function renderSummarySection(array $payload, array $schema, array $computed = []): string
    {
        $fields = [];
        foreach ($schema['fields'] as $field) {
            if (!is_array($field) || empty($field['key'])) {
                continue;
            }
            $key = sanitize_key((string) $field['key']);
            $fields[$key] = [
                'label' => sanitize_text_field((string) ($field['label'] ?? $key)),
                'type' => sanitize_key((string) ($field['type'] ?? 'text')),
            ];
        }

        if (empty($fields)) {
            $fields = [
                'client_name' => ['label' => 'Client Name', 'type' => 'text'],
                'client_email' => ['label' => 'Client Email', 'type' => 'email'],
                'client_address' => ['label' => 'Client Address', 'type' => 'textarea'],
                'cargo_type' => ['label' => 'Cargo Type', 'type' => 'text'],
            ];
        }

        $html = '<div class="section"><h3>Summary</h3><table>';

        foreach ($schema['groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupLabel = sanitize_text_field((string) ($group['label'] ?? ''));
            $groupFields = is_array($group['fields'] ?? null) ? $group['fields'] : [];
            if ($groupLabel !== '') {
                $html .= '<tr><th colspan="2">' . esc_html($groupLabel) . '</th></tr>';
            }
            foreach ($groupFields as $fieldKey) {
                $fieldKey = sanitize_key((string) $fieldKey);
                if (empty($fields[$fieldKey])) {
                    continue;
                }
                $label = $fields[$fieldKey]['label'];
                $raw = array_key_exists($fieldKey, $payload) ? $payload[$fieldKey] : ($computed[$fieldKey] ?? '');
                $value = $this->formatter->formatFieldValue($raw, $fields[$fieldKey]['type']);
                $html .= '<tr><th style="width:30%;">' . esc_html($label) . '</th><td>' . $value . '</td></tr>';
                unset($fields[$fieldKey]);
            }
        }

        foreach ($fields as $key => $field) {
            $raw = array_key_exists($key, $payload) ? $payload[$key] : ($computed[$key] ?? '');
            $value = $this->formatter->formatFieldValue($raw, $field['type']);
            $html .= '<tr><th style="width:30%;">' . esc_html($field['label']) . '</th><td>' . $value . '</td></tr>';
        }

        return $html . '</table></div>';
    }

    private function renderLineItemsSection(array $payload, array $computed = []): string
    {
        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'USD')));
        $items = $this->calculator->resolveLineItems($payload);
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>' .
                '<td>' . esc_html((string) $item['description']) . '</td>' .
                '<td>' . number_format((float) $item['quantity'], 3) . '</td>' .
                '<td>' . esc_html($currency) . ' ' . $this->formatter->formatSmart((float) $item['unit_price'], 2) . '</td>' .
                '<td>' . esc_html($currency) . ' ' . $this->formatter->formatSmart((float) $item['total'], 2) . '</td>' .
                '</tr>';
        }

        $subtotal = (float) ($computed['subtotal'] ?? 0);
        $taxAmount = (float) ($computed['tax_amount'] ?? 0);
        $grandTotal = (float) ($computed['grand_total'] ?? $subtotal);

        return '<div class="section"><h3>Line Items</h3><table>' .
            '<tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr>' .
            $rows .
            '<tr><th colspan="3" style="text-align:right;">Subtotal</th><td>' . esc_html($currency) . ' ' . $this->formatter->formatSmart($subtotal, 2) . '</td></tr>' .
            '<tr><th colspan="3" style="text-align:right;">Tax</th><td>' . esc_html($currency) . ' ' . $this->formatter->formatSmart($taxAmount, 2) . '</td></tr>' .
            '<tr><th colspan="3" style="text-align:right;">Grand Total</th><td><strong>' . esc_html($currency) . ' ' . $this->formatter->formatSmart($grandTotal, 2) . '</strong></td></tr>' .
            '</table></div>';
    }

    private function renderQrSections(array $trackingBlock, ?array $paymentBlock, array $layout): string
    {
        $hasTracking = !empty($trackingBlock['data_uri']);
        $hasPayment = !empty($paymentBlock) && !empty($paymentBlock['data_uri']);
        if (!$hasTracking && !$hasPayment) {
            return '';
        }

        $cards = [];
        if ($hasTracking) {
            $cards[] = $this->renderTrackingCard($trackingBlock, $layout);
        }
        if ($hasPayment) {
            $cards[] = $this->renderPaymentCard((array) $paymentBlock, $layout);
        }

        usort($cards, static function (array $a, array $b): int {
            if ($a['order'] === $b['order']) {
                return $a['index'] <=> $b['index'];
            }
            return $a['order'] <=> $b['order'];
        });

        $htmlCards = implode('', array_map(static fn(array $card): string => $card['html'], $cards));
        if (count($cards) === 1) {
            return '<div class="section">' . $htmlCards . '</div>';
        }

        return '<div class="section"><div class="qr-grid">' . $htmlCards . '</div></div>';
    }

    private function renderTrackingCard(array $trackingBlock, array $layout): array
    {
        $qrSize = (int) (($layout['qr']['size'] ?? 120));
        $position = (string) (($layout['qr']['tracking_position'] ?? 'right'));
        $alignClass = $position === 'left' ? 'qr-align-left' : 'qr-align-right';
        $order = $position === 'left' ? 1 : 2;
        $qr = '<img src="' . esc_attr((string) $trackingBlock['data_uri']) . '" style="width:' . $qrSize . 'px;height:' . $qrSize . 'px;" />';
        $url = esc_html((string) ($trackingBlock['url'] ?? ''));

        return [
            'order' => $order,
            'index' => 1,
            'html' => '<div class="qr-card ' . esc_attr($alignClass) . '"><strong>Tracking QR</strong><br><br>' . $qr . '<div class="mono">' . $url . '</div></div>',
        ];
    }

    private function renderPaymentCard(array $paymentBlock, array $layout): array
    {
        $qrSize = (int) (($layout['qr']['size'] ?? 120));
        $position = (string) (($layout['qr']['payment_position'] ?? 'right'));
        $alignClass = $position === 'left' ? 'qr-align-left' : 'qr-align-right';
        $order = $position === 'left' ? 1 : 2;
        $qr = '<img src="' . esc_attr((string) $paymentBlock['data_uri']) . '" style="width:' . $qrSize . 'px;height:' . $qrSize . 'px;" />';
        $uri = esc_html((string) ($paymentBlock['uri'] ?? ''));

        return [
            'order' => $order,
            'index' => 2,
            'html' => '<div class="qr-card ' . esc_attr($alignClass) . '"><strong>Bitcoin Wallet QR</strong><br><br>' . $qr . '<div class="mono">' . $uri . '</div></div>',
        ];
    }

    private function renderFooterSection(string $trackingCode): string
    {
        return '<div class="section"><div class="muted">Generated by CargoDocs Studio. Tracking: ' . esc_html($trackingCode) . '</div></div>';
    }
}
