<?php

namespace CargoDocsStudio\Domain\Render;

use CargoDocsStudio\Database\Repository\SettingsRepository;
use CargoDocsStudio\Database\Repository\TemplateRepository;
use CargoDocsStudio\Domain\Render\Blocks\PaymentQrBlock;
use CargoDocsStudio\Domain\Render\Blocks\TrackingQrBlock;

class RenderPipeline
{
    private HtmlComposer $composer;
    private PdfAdapterInterface $pdf;
    private TrackingQrBlock $trackingQr;
    private PaymentQrBlock $paymentQr;
    private SettingsRepository $settings;
    private TemplateRepository $templates;
    private string $selectedEngine = 'tcpdf';

    public function __construct()
    {
        $this->composer = new HtmlComposer();
        $this->trackingQr = new TrackingQrBlock();
        $this->paymentQr = new PaymentQrBlock();
        $this->settings = new SettingsRepository();
        $this->templates = new TemplateRepository();

        $engine = (string) $this->settings->get('pdf_engine', 'tcpdf');
        $this->selectedEngine = $engine === 'mpdf' ? 'mpdf' : 'tcpdf';
        $this->pdf = $this->selectedEngine === 'mpdf' ? new MpdfAdapter() : new TcpdfAdapter();
    }

    public function generateInvoicePdf(array $payload, array $trackingData): array
    {
        return $this->generateDocumentPdf('invoice', $payload, $trackingData);
    }

    public function generateDocumentPdf(string $docTypeKey, array $payload, array $trackingData): array
    {
        $docTypeKey = sanitize_key($docTypeKey);
        if ($docTypeKey === '') {
            $docTypeKey = 'invoice';
        }

        $trackingCode = (string) ($trackingData['tracking_code'] ?? '');
        $trackingUrl = (string) ($trackingData['tracking_url'] ?? '');
        $payload['tracking_code'] = $trackingCode;
        $payload['doc_type_key'] = $docTypeKey;

        $trackingBlock = $this->trackingQr->build($trackingUrl);

        $btcSettings = $this->settings->get('bitcoin_payment', [
            'enabled' => true,
            'address' => '',
            'label' => 'Cargo Payment',
            'amount_mode' => 'none',
            'fixed_amount_btc' => '',
        ]);
        $btcEnabled = array_key_exists('bitcoin_enabled', $payload)
            ? !empty($payload['bitcoin_enabled'])
            : !empty($btcSettings['enabled']);

        $btcAddress = sanitize_text_field((string) ($payload['bitcoin_wallet_address'] ?? ($btcSettings['address'] ?? '')));
        $btcLabel = sanitize_text_field((string) ($payload['bitcoin_label'] ?? ($btcSettings['label'] ?? 'Cargo Payment')));
        $btcAmount = null;

        $amountMode = (string) ($payload['bitcoin_amount_mode'] ?? ($btcSettings['amount_mode'] ?? 'none'));
        if ($amountMode === 'fixed') {
            $btcAmount = sanitize_text_field((string) ($payload['bitcoin_amount_btc'] ?? ($btcSettings['fixed_amount_btc'] ?? '')));
        }

        $paymentBlock = null;
        if ($btcEnabled && !empty($btcAddress)) {
            $paymentBlock = $this->paymentQr->buildBitcoin($btcAddress, $btcAmount, $btcLabel);
        }

        $templateConfig = $this->resolveTemplateConfig($payload);
        $filename = $docTypeKey . '-' . ($trackingCode ?: wp_generate_password(12, false, false)) . '-' . time() . '.pdf';
        $renderOptions = $this->buildRenderOptions($templateConfig);

        $html = $this->composer->composeInvoice($payload, $trackingBlock, $paymentBlock, $templateConfig);
        $result = $this->renderWithFallback($html, $filename, $renderOptions);

        if (!$result['success']) {
            return $result;
        }

        $result['tracking_qr_data_uri'] = $trackingBlock['data_uri'] ?? '';
        $result['payment_qr_data_uri'] = $paymentBlock['data_uri'] ?? '';
        $result['payment_uri'] = $paymentBlock['uri'] ?? '';
        $result['template_revision_id'] = $templateConfig['revision_id'] ?? 0;
        $result['template_id'] = $templateConfig['template_id'] ?? 0;

        return $result;
    }

    public function generateInvoicePreviewPdf(array $templateConfig, array $payload = []): array
    {
        $payload = wp_parse_args($payload, [
            'tracking_code' => 'PREVIEW-' . wp_generate_password(6, false, false),
            'client_name' => 'Preview Client',
            'client_email' => 'preview@example.com',
            'client_address' => '123 Preview Street',
            'cargo_type' => 'General Cargo',
            'quantity' => 1,
            'taxable_value' => 100.00,
        ]);

        $trackingUrl = home_url('/cargo-track/' . rawurlencode((string) $payload['tracking_code']) . '?t=preview');
        $trackingBlock = $this->trackingQr->build($trackingUrl);

        $btcSettings = $this->settings->get('bitcoin_payment', [
            'enabled' => true,
            'address' => '',
            'label' => 'Cargo Payment',
            'amount_mode' => 'none',
            'fixed_amount_btc' => '',
        ]);
        $btcEnabled = array_key_exists('bitcoin_enabled', $payload)
            ? !empty($payload['bitcoin_enabled'])
            : !empty($btcSettings['enabled']);

        $paymentBlock = null;
        $btcAddress = sanitize_text_field((string) ($payload['bitcoin_wallet_address'] ?? ($btcSettings['address'] ?? '')));
        if ($btcEnabled && $btcAddress !== '') {
            $btcLabel = sanitize_text_field((string) ($payload['bitcoin_label'] ?? ($btcSettings['label'] ?? 'Cargo Payment')));
            $amountMode = (string) ($payload['bitcoin_amount_mode'] ?? ($btcSettings['amount_mode'] ?? 'none'));
            $btcAmount = null;
            if ($amountMode === 'fixed') {
                $btcAmount = sanitize_text_field((string) ($payload['bitcoin_amount_btc'] ?? ($btcSettings['fixed_amount_btc'] ?? '')));
            }
            $paymentBlock = $this->paymentQr->buildBitcoin($btcAddress, $btcAmount, $btcLabel);
        }

        $html = $this->composer->composeInvoice($payload, $trackingBlock, $paymentBlock, $templateConfig);
        $docTypeKey = sanitize_key((string) ($templateConfig['doc_type_key'] ?? ($payload['doc_type_key'] ?? 'invoice')));
        if ($docTypeKey === '') {
            $docTypeKey = 'invoice';
        }
        $filename = $docTypeKey . '-preview-' . time() . '.pdf';
        $renderOptions = $this->buildRenderOptions($templateConfig);

        $result = $this->renderWithFallback($html, $filename, $renderOptions);

        if (!$result['success']) {
            return $result;
        }

        $result['tracking_qr_data_uri'] = $trackingBlock['data_uri'] ?? '';
        $result['payment_qr_data_uri'] = $paymentBlock['data_uri'] ?? '';
        $result['payment_uri'] = $paymentBlock['uri'] ?? '';

        return $result;
    }

    public function generateInvoicePreviewHtml(array $templateConfig, array $payload = []): array
    {
        $payload = wp_parse_args($payload, [
            'tracking_code' => 'PREVIEW-' . wp_generate_password(6, false, false),
            'client_name' => 'Preview Client',
            'client_email' => 'preview@example.com',
            'client_address' => '123 Preview Street',
            'cargo_type' => 'General Cargo',
            'quantity' => 1,
            'taxable_value' => 100.00,
        ]);

        $trackingUrl = home_url('/cargo-track/' . rawurlencode((string) $payload['tracking_code']) . '?t=preview');
        $trackingBlock = $this->trackingQr->build($trackingUrl);

        $btcSettings = $this->settings->get('bitcoin_payment', [
            'enabled' => true,
            'address' => '',
            'label' => 'Cargo Payment',
            'amount_mode' => 'none',
            'fixed_amount_btc' => '',
        ]);
        $btcEnabled = array_key_exists('bitcoin_enabled', $payload)
            ? !empty($payload['bitcoin_enabled'])
            : !empty($btcSettings['enabled']);

        $paymentBlock = null;
        $btcAddress = sanitize_text_field((string) ($payload['bitcoin_wallet_address'] ?? ($btcSettings['address'] ?? '')));
        if ($btcEnabled && $btcAddress !== '') {
            $btcLabel = sanitize_text_field((string) ($payload['bitcoin_label'] ?? ($btcSettings['label'] ?? 'Cargo Payment')));
            $amountMode = (string) ($payload['bitcoin_amount_mode'] ?? ($btcSettings['amount_mode'] ?? 'none'));
            $btcAmount = null;
            if ($amountMode === 'fixed') {
                $btcAmount = sanitize_text_field((string) ($payload['bitcoin_amount_btc'] ?? ($btcSettings['fixed_amount_btc'] ?? '')));
            }
            $paymentBlock = $this->paymentQr->buildBitcoin($btcAddress, $btcAmount, $btcLabel);
        }

        $html = $this->composer->composeInvoice($payload, $trackingBlock, $paymentBlock, $templateConfig);

        return [
            'success' => true,
            'html' => $html,
            'tracking_qr_data_uri' => $trackingBlock['data_uri'] ?? '',
            'payment_qr_data_uri' => $paymentBlock['data_uri'] ?? '',
            'payment_uri' => $paymentBlock['uri'] ?? '',
        ];
    }

    private function resolveTemplateConfig(array $payload): array
    {
        $revisionId = isset($payload['template_revision_id']) ? (int) $payload['template_revision_id'] : 0;
        if ($revisionId > 0) {
            $revision = $this->templates->getRevisionById($revisionId);
            if ($revision) {
                return [
                    'revision_id' => (int) $revision['id'],
                    'template_id' => (int) ($revision['template_id'] ?? 0),
                    'doc_type_key' => sanitize_key((string) ($revision['doc_type_key'] ?? 'invoice')),
                    'schema' => is_array($revision['schema_json'] ?? null) ? $revision['schema_json'] : [],
                    'theme' => is_array($revision['theme_json'] ?? null) ? $revision['theme_json'] : [],
                    'layout' => is_array($revision['layout_json'] ?? null) ? $revision['layout_json'] : [],
                ];
            }
        }

        $docTypeKey = sanitize_key((string) ($payload['doc_type_key'] ?? 'invoice'));
        if ($docTypeKey === '') {
            $docTypeKey = 'invoice';
        }

        $published = $this->templates->getPublishedRevisionForDocType($docTypeKey);
        if ($published) {
            return [
                'revision_id' => (int) $published['id'],
                'template_id' => (int) ($published['template_id'] ?? 0),
                'doc_type_key' => sanitize_key((string) ($published['doc_type_key'] ?? 'invoice')),
                'schema' => is_array($published['schema_json'] ?? null) ? $published['schema_json'] : [],
                'theme' => is_array($published['theme_json'] ?? null) ? $published['theme_json'] : [],
                'layout' => is_array($published['layout_json'] ?? null) ? $published['layout_json'] : [],
            ];
        }

        return [
            'revision_id' => 0,
            'template_id' => 0,
            'doc_type_key' => sanitize_key((string) ($payload['doc_type_key'] ?? 'invoice')),
            'schema' => [],
            'theme' => [],
            'layout' => [],
        ];
    }

    private function buildRenderOptions(array $templateConfig): array
    {
        $layout = is_array($templateConfig['layout'] ?? null) ? $templateConfig['layout'] : [];
        $format = strtoupper((string) ($layout['page'] ?? 'A4'));
        if (!in_array($format, ['A4', 'LETTER'], true)) {
            $format = 'A4';
        }

        return [
            'page_format' => $format,
        ];
    }

    private function renderWithFallback(string $html, string $filename, array $renderOptions): array
    {
        $primary = $this->pdf->render($html, $filename, $renderOptions);
        if (!empty($primary['success'])) {
            return $this->withEngineMeta($primary, $this->selectedEngine, null);
        }

        if ($this->selectedEngine === 'mpdf') {
            $fallback = (new TcpdfAdapter())->render($html, $filename, $renderOptions);
            if (!empty($fallback['success'])) {
                return $this->withEngineMeta($fallback, 'tcpdf', 'mpdf_failed');
            }
        } else {
            $fallback = (new MpdfAdapter())->render($html, $filename, $renderOptions);
            if (!empty($fallback['success'])) {
                return $this->withEngineMeta($fallback, 'mpdf', 'tcpdf_failed');
            }
        }

        $failure = $primary;
        if (!isset($failure['error']) || $failure['error'] === '') {
            $failure['error'] = 'Failed to render PDF.';
        }

        return $this->withEngineMeta($failure, $this->selectedEngine, null);
    }

    private function withEngineMeta(array $result, string $usedEngine, ?string $fallbackReason): array
    {
        $result['selected_engine'] = $this->selectedEngine;
        $result['engine_used'] = $usedEngine;
        $result['engine_fallback'] = $usedEngine !== $this->selectedEngine ? $usedEngine : null;
        $result['engine_fallback_reason'] = $fallbackReason;

        return $result;
    }

}


