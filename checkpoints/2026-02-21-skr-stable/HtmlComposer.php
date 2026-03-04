<?php

namespace CargoDocsStudio\Domain\Render;

class HtmlComposer
{
    public function composeInvoice(array $payload, array $trackingBlock, ?array $paymentBlock = null, array $templateConfig = []): string
    {
        $docTypeKey = sanitize_key((string) ($templateConfig['doc_type_key'] ?? ($payload['doc_type_key'] ?? 'invoice')));
        $theme = $this->normalizeTheme(is_array($templateConfig['theme'] ?? null) ? $templateConfig['theme'] : []);
        $layout = $this->normalizeLayout(is_array($templateConfig['layout'] ?? null) ? $templateConfig['layout'] : [], $docTypeKey);
        $schema = $this->normalizeSchema(is_array($templateConfig['schema'] ?? null) ? $templateConfig['schema'] : []);
        $computed = $this->computeFinancials($payload);

        if ($docTypeKey === 'invoice') {
            return $this->renderReferenceInvoice($payload, $trackingBlock, $paymentBlock, $computed, $theme);
        }
        if ($docTypeKey === 'skr') {
            return $this->renderReferenceSkrStable($payload, $trackingBlock, $theme);
        }

        $trackingCode = esc_html((string) ($payload['tracking_code'] ?? ''));
        $issuedAt = esc_html(current_time('Y-m-d H:i:s'));
        $title = esc_html((string) ($layout['title'] ?? $this->defaultTitleForDocType($docTypeKey)));
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
.wrap{width:100%;}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid ' . esc_attr($theme['primary_color']) . ';padding-bottom:' . (int) $theme['space_sm'] . 'px;margin-bottom:' . (int) $theme['space_md'] . 'px;}
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
  ' . ($showHeader ? '<div class="header"><div><div class="title">' . $title . '</div><div class="muted">Issued: ' . $issuedAt . '</div><div class="muted">Tracking Code: ' . $trackingCode . '</div></div></div>' : '') . '
  ' . $body . '
</div>
</body>
</html>';
    }

    private function normalizeTheme(array $theme): array
    {
        return [
            'primary_color' => sanitize_hex_color((string) ($theme['primary_color'] ?? '')) ?: '#0b5fff',
            'accent_color' => sanitize_hex_color((string) ($theme['accent_color'] ?? '')) ?: '#101828',
            'text_color' => sanitize_hex_color((string) ($theme['text_color'] ?? '')) ?: '#111827',
            'font_family' => sanitize_text_field((string) ($theme['font_family'] ?? 'DejaVu Sans')),
            'heading_weight' => (int) ($theme['heading_weight'] ?? 700),
            'table_header_bg' => sanitize_hex_color((string) ($theme['table_header_bg'] ?? '')) ?: '#f5f5f5',
            'table_cell_padding' => max(4, min(16, (int) ($theme['table_cell_padding'] ?? 8))),
            'space_sm' => max(6, min(20, (int) ($theme['space_sm'] ?? 10))),
            'space_md' => max(8, min(30, (int) ($theme['space_md'] ?? 14))),
        ];
    }

    private function normalizeLayout(array $layout, string $docTypeKey = 'invoice'): array
    {
        $defaultSections = $this->defaultSectionsForDocType($docTypeKey);
        $sections = $layout['sections'] ?? $defaultSections;
        if (!is_array($sections) || empty($sections)) {
            $sections = $defaultSections;
        }

        return [
            'title' => sanitize_text_field((string) ($layout['title'] ?? $this->defaultTitleForDocType($docTypeKey))),
            'page' => strtoupper(sanitize_text_field((string) ($layout['page'] ?? 'A4'))),
            'sections' => array_values(array_filter(array_map('sanitize_key', $sections))),
            'qr' => [
                'tracking_position' => sanitize_key((string) (($layout['qr']['tracking_position'] ?? 'right'))),
                'payment_position' => sanitize_key((string) (($layout['qr']['payment_position'] ?? 'right'))),
                'size' => max(64, min(220, (int) (($layout['qr']['size'] ?? 120)))),
            ],
        ];
    }

    private function normalizeSchema(array $schema): array
    {
        return [
            'fields' => is_array($schema['fields'] ?? null) ? $schema['fields'] : [],
            'groups' => is_array($schema['groups'] ?? null) ? $schema['groups'] : [],
        ];
    }

    private function renderReferenceInvoice(array $payload, array $trackingBlock, ?array $paymentBlock, array $computed, array $theme): string
    {
        $quantity = (float) ($payload['quantity'] ?? 0);
        $taxableValue = (float) ($payload['taxable_value'] ?? 0);
        $invoiceNumber = sanitize_text_field((string) ($payload['invoice_number'] ?? $payload['document_number'] ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = sanitize_text_field((string) ($payload['tracking_code'] ?? ''));
        }

        $date = sanitize_text_field((string) ($payload['invoice_date'] ?? current_time('Y-m-d')));
        $clientName = sanitize_text_field((string) ($payload['client_name'] ?? ''));
        $clientEmail = sanitize_email((string) ($payload['client_email'] ?? ''));
        $destination = sanitize_text_field((string) ($payload['destination'] ?? ''));
        $cargoType = sanitize_text_field((string) ($payload['cargo_type'] ?? 'Cargo'));
        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'USD')));
        $trackingQrUri = esc_url_raw((string) ($trackingBlock['data_uri'] ?? ''));
        $paymentQrUri = $paymentBlock && !empty($paymentBlock['data_uri']) ? esc_url_raw((string) $paymentBlock['data_uri']) : '';
        $paymentAddress = sanitize_text_field((string) ($payload['payment_wallet_address'] ?? $payload['wallet_address'] ?? $payload['payment_address'] ?? ''));
        $paymentNetwork = sanitize_text_field((string) ($payload['payment_network'] ?? 'TRON (TRC20)'));
        $logoUrl = esc_url_raw((string) ($payload['company_logo_url'] ?? 'https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png'));

        $taxRate = isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : 5.0;
        $insuranceRate = isset($payload['insurance_rate']) ? (float) $payload['insurance_rate'] : 1.5;
        $smeltingCost = (float) ($payload['smelting_cost'] ?? 0);
        $certOrigin = (float) ($payload['cert_origin'] ?? 0);
        $certOwnership = (float) ($payload['cert_ownership'] ?? 0);
        $exportPermit = (float) ($payload['export_permit'] ?? 0);
        $freightCost = (float) ($payload['freight_cost'] ?? 0);
        $agentFees = (float) ($payload['agent_fees'] ?? 0);

        $taxAmount = isset($payload['tax_amount']) ? (float) $payload['tax_amount'] : ($taxableValue * ($taxRate / 100));
        $insuranceAmount = isset($payload['insurance_amount']) ? (float) $payload['insurance_amount'] : ($taxableValue * ($insuranceRate / 100));
        $smeltingTotal = $smeltingCost * $quantity;
        $freightTotal = $freightCost * $quantity;
        $agentTotal = $agentFees * $quantity;
        $totalAmount = isset($payload['grand_total'])
            ? (float) $payload['grand_total']
            : ($taxAmount + $insuranceAmount + $smeltingTotal + $certOrigin + $certOwnership + $exportPermit + $freightTotal + $agentTotal);
        if ($totalAmount <= 0) {
            $totalAmount = (float) ($computed['grand_total'] ?? 0);
        }

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;margin:0;padding:0;color:#333;font-size:10pt;}
.header-table{width:100%;margin-bottom:5px;border-collapse:collapse;}
.header-table td{vertical-align:top;padding:0;}
.logo{width:180px;height:auto;}
.invoice-title{font-size:22pt;font-weight:700;color:#000;margin:1px 0;}
.product-info{background:#f5f5f5;padding:10px;margin:20px 0;text-align:center;border:1px solid #ddd;}
.product-info h3{margin:0;font-size:12pt;color:#333;}
.product-details{margin:5px 0;font-size:10pt;color:#666;}
.section-header{background:#F4A460;color:#fff;padding:8px 10px;font-weight:700;font-size:10pt;}
.billing-table,.cost-table,.footer-table{width:100%;border-collapse:collapse;}
.billing-table{margin:20px 0;}
.billing-table td{padding:8px 10px;border:1px solid #ddd;font-size:10pt;}
.cost-table{margin:20px 0;}
.cost-table th{background:#F4A460;color:#fff;padding:8px 10px;font-weight:700;font-size:10pt;text-align:left;border:1px solid #ddd;}
.cost-table td{padding:6px 10px;border:1px solid #ddd;font-size:10pt;}
.cost-table tr:nth-child(even){background:#f9f9f9;}
.amount-column{text-align:right;}
.total-row{background:#F4A460 !important;color:#fff;font-weight:700;font-size:12pt;}
.total-row td{padding:12px 10px;}
.network-section{font-size:9pt;color:#666;text-align:center;}
.mono{font-family:monospace;font-size:9pt;word-break:break-all;}
</style>
</head>
<body>
<table class="header-table">
  <tr>
    <td style="width:30%;">
      ' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="Company Logo" />' : '') . '
    </td>
    <td style="text-align:right;width:200px;vertical-align:top;">
      <div style="margin-bottom:15px;">
        <strong>Date:</strong> ' . esc_html($date) . '<br>
        <strong>Invoice:</strong> ' . esc_html($invoiceNumber) . '
      </div>
      <div style="text-align:right;">
        ' . ($trackingQrUri !== '' ? '<img src="' . esc_attr($trackingQrUri) . '" style="width:120px;height:120px;border:1px solid #ddd;" alt="Tracking QR" />' : '') . '
      </div>
    </td>
  </tr>
</table>
<div class="invoice-title">TAX INVOICE</div>
<div class="product-info">
  <h3>' . esc_html($cargoType) . '</h3>
  <div class="product-details">
    ' . number_format($quantity, 1) . ' KGS (' . number_format($quantity, 1) . ' + 0.0% carats)<br>
    Declared taxable value: ' . number_format($taxableValue, 3) . ' ' . esc_html($currency) . ' per kg
  </div>
</div>
<table class="billing-table">
  <tr>
    <td class="section-header" style="width:50%;">Billed to</td>
    <td class="section-header" style="width:50%;">Destination</td>
  </tr>
  <tr>
    <td style="vertical-align:top;">
      <strong>' . esc_html($clientName) . '</strong><br>
      ' . esc_html($clientEmail) . '
    </td>
    <td style="vertical-align:top;">' . esc_html($destination) . '</td>
  </tr>
</table>
<table class="cost-table">
  <tr>
    <th style="width:35%;">Description</th>
    <th style="width:15%;">Unit cost ($)</th>
    <th style="width:20%;">Quantity</th>
    <th style="width:30%;">Amount (' . esc_html($currency) . ')</th>
  </tr>
  <tr>
    <td>Tax ' . number_format($taxRate, 1) . '% on declared taxable value</td>
    <td class="amount-column">' . number_format($taxableValue > 0 ? ($taxableValue * ($taxRate / 100)) : 0, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($taxAmount, 2) . '</td>
  </tr>
  <tr>
    <td>Insurance ' . number_format($insuranceRate, 1) . '% on declared taxable value</td>
    <td class="amount-column">' . number_format($taxableValue > 0 ? ($taxableValue * ($insuranceRate / 100)) : 0, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($insuranceAmount, 2) . '</td>
  </tr>
  <tr>
    <td>Smelting</td>
    <td class="amount-column">' . number_format($smeltingCost, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($smeltingTotal, 2) . '</td>
  </tr>
  <tr>
    <td>Certificate of Origin</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($certOrigin, 2) . '</td>
  </tr>
  <tr>
    <td>Certificate of Ownership</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($certOwnership, 2) . '</td>
  </tr>
  <tr>
    <td>Export Permit</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($exportPermit, 2) . '</td>
  </tr>
  <tr>
    <td>Freight charges</td>
    <td class="amount-column">' . number_format($freightCost, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($freightTotal, 2) . '</td>
  </tr>
  <tr>
    <td>Agent fees, Security &amp; handling</td>
    <td class="amount-column">' . number_format($agentFees, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . number_format($agentTotal, 2) . '</td>
  </tr>
  <tr class="total-row">
    <td colspan="3" style="text-align:center;font-size:14pt;"><strong>TOTAL (' . esc_html($currency) . ')</strong></td>
    <td class="amount-column" style="font-size:14pt;"><strong>' . number_format($totalAmount, 2) . '</strong></td>
  </tr>
</table>
<table class="footer-table">
  <tr>
    <td style="width:60%;text-align:center;vertical-align:top;padding:10px;">
      <div style="margin-bottom:5px;"><strong>For and on behalf of 5</strong></div>
      <div style="height:100px;min-height:100px;margin-bottom:15px;">&nbsp;</div>
      <div style="border-top:1px solid #333;padding-top:1px;width:300px;margin:0 auto;">
        <strong>' . esc_html((string) ($payload['company_name'] ?? 'WAKALA Minerals Limited')) . '</strong>
      </div>
    </td>
    <td class="network-section" style="width:40%;vertical-align:top;padding:10px;">
      ' . ($paymentQrUri !== '' ? '<img src="' . esc_attr($paymentQrUri) . '" style="width:100px;height:100px;border:1px solid #ddd;" alt="Payment QR" /><br><br>' : '') . '
      <strong>Network:</strong> ' . esc_html($paymentNetwork) . '<br>
      <strong>Address:</strong> <span class="mono">' . esc_html($paymentAddress) . '</span>
    </td>
  </tr>
</table>
<div style="border-top:2px solid #ff0000;margin:20px 0 10px 0;"></div>
<table style="width:100%;border-collapse:collapse;font-size:9pt;color:#333;">
  <tr>
    <td style="width:33%;text-align:left;vertical-align:top;padding:5px;"><strong>☎ ' . esc_html((string) ($payload['company_phone'] ?? '+256-751896060')) . '</strong></td>
    <td style="width:34%;text-align:center;vertical-align:top;padding:5px;"><strong style="color:#0066cc;">✉ ' . esc_html((string) ($payload['company_email'] ?? 'info@wakalaminerals.com')) . '</strong></td>
    <td style="width:33%;text-align:right;vertical-align:top;padding:5px;"><strong>⌂ ' . nl2br(esc_html((string) ($payload['company_address'] ?? 'TANK HILL ROAD, MUYENGA' . "\n" . 'P.O.BOX 124439 KAMPALA-CPO'))) . '</strong></td>
    </tr>
</table>
</body>
</html>';
    }

    private function renderReferenceSkr(array $payload, array $trackingBlock, array $theme): string
    {
        $companyName = sanitize_text_field((string) ($payload['skr_company_name'] ?? 'EXPRESS SECURITY LIMITED'));
        $companyRc = sanitize_text_field((string) ($payload['skr_company_rc'] ?? '1234567'));
        $companyLicense = sanitize_text_field((string) ($payload['skr_license_number'] ?? '77-7477'));
        $companyPhone = sanitize_text_field((string) ($payload['skr_company_phone'] ?? '+256 778 223 344'));
        $companyEmail = sanitize_email((string) ($payload['skr_company_email'] ?? 'expresssecurity@hotmail.com'));
        $companyAddress = sanitize_text_field((string) ($payload['skr_company_address'] ?? 'PLOT 32A KAMPALA ROAD KAMPALA UGANDA'));
        $logoUrl = esc_url_raw((string) ($payload['company_logo_url'] ?? 'https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png'));
        $watermarkUrl = esc_url_raw((string) ($payload['skr_watermark_url'] ?? ($payload['watermark_url'] ?? '')));

        $custodyType = sanitize_text_field((string) ($payload['custody_type'] ?? 'SAFE CUSTODY'));
        $depositorName = sanitize_text_field((string) ($payload['depositor_name'] ?? ($payload['client_name'] ?? '')));
        $depositNumber = sanitize_text_field((string) ($payload['deposit_number'] ?? ($payload['tracking_code'] ?? '')));
        $projectedDays = sanitize_text_field((string) ($payload['projected_days'] ?? 'N/A'));
        $contentDescription = sanitize_text_field((string) ($payload['content_description'] ?? ($payload['cargo_type'] ?? 'PREVIOUS METAL')));
        $quantity = sanitize_text_field((string) ($payload['quantity'] ?? ''));
        $unit = sanitize_text_field((string) ($payload['unit'] ?? 'KGS'));
        $packagesNumber = sanitize_text_field((string) ($payload['packages_number'] ?? ''));
        $declaredValue = (float) ($payload['declared_value'] ?? ($payload['taxable_value'] ?? 0));
        $originOfGoods = sanitize_text_field((string) ($payload['origin_of_goods'] ?? ($payload['origin'] ?? '')));
        $depositType = sanitize_text_field((string) ($payload['deposit_type'] ?? ''));
        $insuranceRate = sanitize_text_field((string) ($payload['insurance_rate'] ?? '1.5% OF THE TOTAL VALUE'));
        $storageFees = (float) ($payload['storage_fees'] ?? 0);
        $supportingDocuments = sanitize_textarea_field((string) ($payload['supporting_documents'] ?? 'PRELIMINARY DOCUMENTATION'));
        $depositInstructions = sanitize_textarea_field((string) ($payload['deposit_instructions'] ?? ''));
        $depositorSignature = sanitize_textarea_field((string) ($payload['depositor_signature'] ?? ''));
        $additionalNotes = sanitize_textarea_field((string) ($payload['additional_notes'] ?? ''));
        $trackingCode = sanitize_text_field((string) ($payload['tracking_code'] ?? $depositNumber));
        $trackingQrUri = esc_url_raw((string) ($trackingBlock['data_uri'] ?? ''));

        $todayDmY = esc_html(current_time('d-m-Y'));
        $todayPretty = esc_html(current_time('d M Y'));

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
@page{size:A4;margin:6mm;}
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;color:#333;line-height:1.2;margin:0;padding:0;font-size:10pt;}
.header{width:100%;border-collapse:collapse;margin-bottom:4px;}
.header td{border:none;vertical-align:top;padding:0;}
.logo{width:140px;height:auto;}
.company-name{font-size:17px;font-weight:700;color:#1e4d72;text-align:right;margin:0 0 1px 0;}
.company-info{font-size:10px;text-align:right;margin:0;line-height:1.2;}
.title{font-size:15px;font-weight:700;color:#1e4d72;text-align:center;margin:5px 0 0;}
.subtitle{font-size:11px;font-style:italic;text-align:center;margin:2px 0 6px;}
.intro,.footer{font-size:10pt;line-height:1.22;text-align:justify;margin:4px 0;}
table.grid{width:100%;border-collapse:collapse;margin:5px 0;table-layout:fixed;}
table.grid th,table.grid td{border:1px solid #222;padding:4px 5px;vertical-align:top;font-size:10pt;}
table.grid th{background:#ececec;font-weight:700;}
.red{color:#d32f2f;font-weight:700;}
.blue{color:#1e4d72;font-weight:700;}
.tracking{text-align:center;color:#b71c1c;font-weight:700;font-size:10pt;margin:6px 0 3px;}
.sign{width:100%;border-collapse:collapse;margin-top:6px;}
.sign td{border:none;font-size:10pt;}
.sign-right{text-align:right;}
.watermark-wrap{text-align:center;margin:3px 0 5px;}
.watermark{width:55%;max-width:120mm;opacity:0.11;}
</style>
</head>
<body>
<table class="header">
  <tr>
    <td style="width:30%;">' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="' . esc_attr($companyName) . '" />' : '') . '</td>
    <td style="width:70%;">
      <div class="company-name">' . esc_html($companyName) . '</div>
      <p class="company-info">RC-Number: ' . esc_html($companyRc) . '</p>
      <p class="company-info">License Number: ' . esc_html($companyLicense) . '</p>
      <p class="company-info">Tel: ' . esc_html($companyPhone) . '</p>
      <p class="company-info">Email: ' . esc_html($companyEmail) . '</p>
      <p class="company-info">' . esc_html($companyAddress) . '</p>
    </td>
  </tr>
</table>
<div class="title">SAFE KEEPING RECEIPT</div>
<div class="subtitle">Valid Only In Original</div>
<div class="intro">
  We, <strong>' . esc_html($companyName) . '</strong>, a customs licensed bonded warehouse located in Kampala, Republic of Uganda hereby confirm with full legal and corporate responsibility that we have received in our safe keeping the following as detailed below.
</div>
' . ($watermarkUrl !== '' ? '<div class="watermark-wrap"><img src="' . esc_attr($watermarkUrl) . '" class="watermark" alt="" /></div>' : '') . '

<table class="grid">
  <tr>
    <th style="width:24%;">CUSTODY TYPE</th>
    <th style="width:26%;">Depositors Booking Number</th>
    <th style="width:20%;">Date of Receipt</th>
    <th style="width:30%;">Projected Days of Custody</th>
  </tr>
  <tr>
    <td><span class="red">' . esc_html($custodyType) . '</span></td>
    <td><strong>' . esc_html($depositNumber) . '</strong></td>
    <td>' . $todayDmY . '</td>
    <td>' . esc_html($projectedDays) . '</td>
  </tr>
  <tr>
    <th>DEPOSITOR(S) NAME AND ADDRESS</th>
    <th>Documented Custom Value Amount (US$)</th>
    <th>Represented Date</th>
    <th>Represented BY</th>
  </tr>
  <tr>
    <td><span class="red">' . esc_html($depositorName) . '</span></td>
    <td>TBA</td>
    <td>' . $todayDmY . '</td>
    <td>N/A</td>
  </tr>
  <tr>
    <th>Reg Number</th>
    <th colspan="2"></th>
    <th>Receiving Officer</th>
  </tr>
  <tr>
    <td><strong>ESL-A-205</strong></td>
    <td colspan="2"></td>
    <td><strong>MR.KIMBUGWE FAISAL</strong></td>
  </tr>
</table>

<table class="grid">
  <tr>
    <th style="width:22%;">Details Description of Contents</th>
    <th style="width:10%;">Quantity</th>
    <th style="width:15%;">Number of Packages</th>
    <th style="width:18%;">Value (US$)</th>
    <th style="width:35%;">Origin of Goods</th>
  </tr>
  <tr>
    <td><span class="red">' . esc_html($contentDescription) . '</span></td>
    <td>' . esc_html($quantity) . ' ' . esc_html($unit) . '</td>
    <td>' . esc_html($packagesNumber) . '</td>
    <td>USD: ' . number_format($declaredValue, 2) . '</td>
    <td>' . esc_html($originOfGoods) . '</td>
  </tr>
  <tr>
    <th>Type of Deposit</th>
    <th colspan="2">Total Value</th>
    <th colspan="2">Insurance Value</th>
  </tr>
  <tr>
    <td>' . esc_html($depositType) . '</td>
    <td colspan="2">TBA</td>
    <td colspan="2">' . esc_html($insuranceRate) . '</td>
  </tr>
  <tr>
    <th colspan="3">Supporting Documents of Goods</th>
    <th colspan="2">CD Storage Fees</th>
  </tr>
  <tr>
    <td colspan="3">' . nl2br(esc_html($supportingDocuments)) . '</td>
    <td colspan="2">PER DAY = $' . number_format($storageFees, 2) . '</td>
  </tr>
  <tr>
    <th colspan="3">Deposition Instructions (if any)</th>
    <th colspan="2">Date</th>
  </tr>
  <tr>
    <td colspan="3">' . nl2br(esc_html($depositInstructions)) . '</td>
    <td colspan="2">' . $todayPretty . '</td>
  </tr>
  <tr>
    <th style="width:22%;">Date</th>
    <th colspan="2">Depositor&apos;s Signature</th>
    <th colspan="2">Additional Information</th>
  </tr>
  <tr>
    <td><span class="red">' . $todayPretty . '</span></td>
    <td colspan="2">' . nl2br(esc_html($depositorSignature)) . '</td>
    <td colspan="2">' . nl2br(esc_html($additionalNotes)) . '</td>
  </tr>
</table>

<div class="footer">
  We, <strong>' . esc_html($companyName) . '</strong>, further confirm that these goods in our safe keeping are legally earned, good, clean and cleared of non-criminal origin, free of any liens or encumbrances and are freely transferable upon the instructions of an authorized signatory (the depositor).
</div>

<div class="tracking">TRACK YOUR CONSIGNMENT ON OUR OFFICIAL WEBSITE WITH THE TRACKING NUMBER ' . esc_html($trackingCode) . '</div>
' . ($trackingQrUri !== '' ? '<div style="text-align:center;margin:2px 0 6px;"><img src="' . esc_attr($trackingQrUri) . '" style="width:52px;height:52px;border:1px solid #ddd;" alt="Tracking QR" /></div>' : '') . '

<table class="sign">
  <tr>
    <td style="width:50%;">Kimbugwe Faisal<br><strong>ISSUING OFFICER LIMITED</strong></td>
    <td class="sign-right" style="width:50%;">' . $todayPretty . '<br><strong>Official Stamp / Signature</strong></td>
  </tr>
</table>
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
                $value = $this->formatFieldValue($raw, $fields[$fieldKey]['type']);
                $html .= '<tr><th style="width:30%;">' . esc_html($label) . '</th><td>' . $value . '</td></tr>';
                unset($fields[$fieldKey]);
            }
        }

        foreach ($fields as $key => $field) {
            $raw = array_key_exists($key, $payload) ? $payload[$key] : ($computed[$key] ?? '');
            $value = $this->formatFieldValue($raw, $field['type']);
            $html .= '<tr><th style="width:30%;">' . esc_html($field['label']) . '</th><td>' . $value . '</td></tr>';
        }

        return $html . '</table></div>';
    }

    private function renderLineItemsSection(array $payload, array $computed = []): string
    {
        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'USD')));
        $items = $this->resolveLineItems($payload);
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>' .
                '<td>' . esc_html((string) $item['description']) . '</td>' .
                '<td>' . number_format((float) $item['quantity'], 3) . '</td>' .
                '<td>' . esc_html($currency) . ' ' . number_format((float) $item['unit_price'], 2) . '</td>' .
                '<td>' . esc_html($currency) . ' ' . number_format((float) $item['total'], 2) . '</td>' .
                '</tr>';
        }

        $subtotal = (float) ($computed['subtotal'] ?? 0);
        $taxAmount = (float) ($computed['tax_amount'] ?? 0);
        $grandTotal = (float) ($computed['grand_total'] ?? $subtotal);

        return '<div class="section"><h3>Line Items</h3><table>' .
            '<tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr>' .
            $rows .
            '<tr><th colspan="3" style="text-align:right;">Subtotal</th><td>' . esc_html($currency) . ' ' . number_format($subtotal, 2) . '</td></tr>' .
            '<tr><th colspan="3" style="text-align:right;">Tax</th><td>' . esc_html($currency) . ' ' . number_format($taxAmount, 2) . '</td></tr>' .
            '<tr><th colspan="3" style="text-align:right;">Grand Total</th><td><strong>' . esc_html($currency) . ' ' . number_format($grandTotal, 2) . '</strong></td></tr>' .
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

    private function defaultSectionsForDocType(string $docTypeKey): array
    {
        return match ($docTypeKey) {
            'receipt' => ['header', 'summary', 'line_items', 'payment_qr', 'footer'],
            'skr' => ['header', 'summary', 'tracking_qr', 'footer'],
            default => ['header', 'summary', 'line_items', 'tracking_qr', 'payment_qr', 'footer'],
        };
    }

    private function defaultTitleForDocType(string $docTypeKey): string
    {
        return match ($docTypeKey) {
            'receipt' => 'Payment Receipt',
            'skr' => 'Safe Keeping Receipt',
            default => 'Cargo Invoice',
        };
    }

    private function renderFooterSection(string $trackingCode): string
    {
        return '<div class="section"><div class="muted">Generated by CargoDocs Studio. Tracking: ' . esc_html($trackingCode) . '</div></div>';
    }

    private function resolveLineItems(array $payload): array
    {
        $rawItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
        $items = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $description = sanitize_text_field((string) ($row['description'] ?? $row['label'] ?? 'Item'));
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            $total = array_key_exists('total', $row) ? (float) $row['total'] : ($quantity * $unitPrice);
            if ($description === '' && $quantity === 0.0 && $unitPrice === 0.0 && $total === 0.0) {
                continue;
            }
            $items[] = [
                'description' => $description !== '' ? $description : 'Item',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
            ];
        }

        if (!empty($items)) {
            return $items;
        }

        $quantity = (float) ($payload['quantity'] ?? 0);
        $declared = (float) ($payload['taxable_value'] ?? 0);
        $unitPrice = $quantity > 0 ? $declared / $quantity : $declared;
        $cargo = sanitize_text_field((string) ($payload['cargo_type'] ?? 'Cargo'));

        return [[
            'description' => $cargo !== '' ? $cargo : 'Cargo',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $declared,
        ]];
    }

    private function computeFinancials(array $payload): array
    {
        $items = $this->resolveLineItems($payload);
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['total'] ?? 0);
        }

        if ($subtotal <= 0 && isset($payload['taxable_value'])) {
            $subtotal = (float) $payload['taxable_value'];
        }

        $taxRate = isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : 0.0;
        $taxAmount = isset($payload['tax_amount']) ? (float) $payload['tax_amount'] : ($subtotal * ($taxRate / 100));
        $grandTotal = isset($payload['grand_total']) ? (float) $payload['grand_total'] : ($subtotal + $taxAmount);

        return [
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
        ];
    }

    private function formatFieldValue(mixed $value, string $type): string
    {
        if ($type === 'textarea') {
            return nl2br(esc_html((string) $value));
        }
        if ($type === 'number') {
            return esc_html(number_format((float) $value, 2));
        }
        if ($type === 'currency') {
            return esc_html(number_format((float) $value, 2));
        }
        return esc_html((string) $value);
    }

    private function renderReferenceSkrStable(array $payload, array $trackingBlock, array $theme): string
    {
        $companyName = sanitize_text_field((string) ($payload['skr_company_name'] ?? 'EXPRESS SECURITY LIMITED'));
        $companyRc = sanitize_text_field((string) ($payload['skr_company_rc'] ?? '1234567'));
        $companyLicense = sanitize_text_field((string) ($payload['skr_license_number'] ?? '77-7477'));
        $companyPhone = sanitize_text_field((string) ($payload['skr_company_phone'] ?? '+256 778 223 344'));
        $companyEmail = sanitize_email((string) ($payload['skr_company_email'] ?? 'expresssecurity@hotmail.com'));
        $companyAddress = sanitize_text_field((string) ($payload['skr_company_address'] ?? 'PLOT 32A KAMPALA ROAD KAMPALA UGANDA'));
        $logoUrl = esc_url_raw((string) ($payload['company_logo_url'] ?? 'https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png'));

        $depositNumber = sanitize_text_field((string) ($payload['deposit_number'] ?? ($payload['tracking_code'] ?? '')));
        $depositorName = sanitize_text_field((string) ($payload['depositor_name'] ?? ($payload['client_name'] ?? '')));
        $custodyType = sanitize_text_field((string) ($payload['custody_type'] ?? 'SAFE CUSTODY'));
        $contentDescription = sanitize_text_field((string) ($payload['content_description'] ?? ($payload['cargo_type'] ?? 'PRECIOUS METAL')));
        $quantity = sanitize_text_field((string) ($payload['quantity'] ?? ''));
        $unit = sanitize_text_field((string) ($payload['unit'] ?? 'KGS'));
        $packagesNumber = sanitize_text_field((string) ($payload['packages_number'] ?? ''));
        $declaredValue = (float) ($payload['declared_value'] ?? ($payload['taxable_value'] ?? 0));
        $originOfGoods = sanitize_text_field((string) ($payload['origin_of_goods'] ?? ($payload['origin'] ?? '')));
        $depositType = sanitize_text_field((string) ($payload['deposit_type'] ?? ''));
        $insuranceRate = sanitize_text_field((string) ($payload['insurance_rate'] ?? '1.5% OF THE TOTAL VALUE'));
        $storageFees = (float) ($payload['storage_fees'] ?? 0);
        $supportingDocuments = sanitize_textarea_field((string) ($payload['supporting_documents'] ?? 'PRELIMINARY DOCUMENTATION'));
        $depositInstructions = sanitize_textarea_field((string) ($payload['deposit_instructions'] ?? ''));
        $depositorSignature = sanitize_textarea_field((string) ($payload['depositor_signature'] ?? ''));
        $additionalNotes = sanitize_textarea_field((string) ($payload['additional_notes'] ?? ''));

        $todayDmY = esc_html(current_time('d-m-Y'));
        $todayPretty = esc_html(current_time('d M Y'));
        $trackingCode = sanitize_text_field((string) ($payload['tracking_code'] ?? $depositNumber));
        $trackingQrUri = esc_url_raw((string) ($trackingBlock['data_uri'] ?? ''));

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;margin:0;padding:0;color:#333;font-size:10pt;}
.header-table{width:100%;margin-bottom:5px;border-collapse:collapse;}
.header-table td{vertical-align:top;padding:0;}
.logo{width:170px;height:auto;}
.doc-title{font-size:18pt;font-weight:700;color:#1e4d72;margin:2px 0;text-align:center;}
.doc-subtitle{font-size:11pt;font-style:italic;margin:0 0 8px 0;text-align:center;}
.section-header{background:#F4A460;color:#fff;padding:8px 10px;font-weight:700;font-size:10pt;}
.table{width:100%;border-collapse:collapse;margin:8px 0;}
.table th{background:#e9e9e9;padding:6px 8px;border:1px solid #222;font-size:10pt;text-align:left;}
.table td{padding:6px 8px;border:1px solid #222;font-size:10pt;vertical-align:top;}
.red{color:#d32f2f;font-weight:700;}
.tracking{text-align:center;color:#b71c1c;font-weight:700;margin:10px 0 4px;}
</style>
</head>
<body>
<table class="header-table">
  <tr>
    <td style="width:32%;">' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="' . esc_attr($companyName) . '" />' : '') . '</td>
    <td style="width:68%;text-align:right;">
      <div style="font-size:16pt;font-weight:700;color:#1e4d72;">' . esc_html($companyName) . '</div>
      <div style="font-size:10pt;">RC-Number: ' . esc_html($companyRc) . '</div>
      <div style="font-size:10pt;">License Number: ' . esc_html($companyLicense) . '</div>
      <div style="font-size:10pt;">Tel: ' . esc_html($companyPhone) . '</div>
      <div style="font-size:10pt;">Email: ' . esc_html($companyEmail) . '</div>
      <div style="font-size:10pt;">' . esc_html($companyAddress) . '</div>
    </td>
  </tr>
</table>
<div class="doc-title">SAFE KEEPING RECEIPT</div>
<div class="doc-subtitle">Valid Only In Original</div>

<div style="font-size:10pt;margin:6px 0;">
  We, <strong>' . esc_html($companyName) . '</strong>, confirm that we have received in safe keeping the following cargo details.
</div>

<table class="table">
  <tr><th style="width:30%;">Custody Type</th><th style="width:35%;">Depositors Booking Number</th><th>Date of Receipt</th></tr>
  <tr><td><span class="red">' . esc_html($custodyType) . '</span></td><td><strong>' . esc_html($depositNumber) . '</strong></td><td>' . $todayDmY . '</td></tr>
  <tr><th>Depositor(s) Name and Address</th><th>Projected Days of Custody</th><th>Origin of Goods</th></tr>
  <tr><td><span class="red">' . esc_html($depositorName) . '</span></td><td>' . esc_html((string) ($payload['projected_days'] ?? 'N/A')) . '</td><td>' . esc_html($originOfGoods) . '</td></tr>
</table>

<table class="table">
  <tr><th>Details Description of Contents</th><th>Quantity</th><th>Packages</th><th>Declared Value (US$)</th></tr>
  <tr><td><span class="red">' . esc_html($contentDescription) . '</span></td><td>' . esc_html($quantity) . ' ' . esc_html($unit) . '</td><td>' . esc_html($packagesNumber) . '</td><td>USD: ' . number_format($declaredValue, 2) . '</td></tr>
  <tr><th>Type of Deposit</th><th>Insurance Value</th><th colspan="2">CD Storage Fees</th></tr>
  <tr><td>' . esc_html($depositType) . '</td><td>' . esc_html($insuranceRate) . '</td><td colspan="2">PER DAY = $' . number_format($storageFees, 2) . '</td></tr>
  <tr><th>Supporting Documents of Goods</th><th colspan="3">Deposition Instructions (if any)</th></tr>
  <tr><td>' . nl2br(esc_html($supportingDocuments)) . '</td><td colspan="3">' . nl2br(esc_html($depositInstructions)) . '</td></tr>
  <tr><th>Date</th><th>Depositor&apos;s Signature</th><th colspan="2">Additional Information</th></tr>
  <tr><td><span class="red">' . $todayPretty . '</span></td><td>' . nl2br(esc_html($depositorSignature)) . '</td><td colspan="2">' . nl2br(esc_html($additionalNotes)) . '</td></tr>
</table>

<div style="font-size:10pt;margin:8px 0;">
  We, <strong>' . esc_html($companyName) . '</strong>, further confirm these goods are clear and transferable by authorized signatory instructions.
</div>

<div class="tracking">TRACK YOUR CONSIGNMENT ON OUR OFFICIAL WEBSITE WITH THE TRACKING NUMBER ' . esc_html($trackingCode) . '</div>
' . ($trackingQrUri !== '' ? '<div style="text-align:center;"><img src="' . esc_attr($trackingQrUri) . '" style="width:52px;height:52px;border:1px solid #ddd;" alt="Tracking QR" /></div>' : '') . '

<table style="width:100%;border-collapse:collapse;margin-top:8px;">
  <tr>
    <td style="width:50%;font-size:10pt;border:none;">Kimbugwe Faisal<br><strong>ISSUING OFFICER LIMITED</strong></td>
    <td style="width:50%;font-size:10pt;border:none;text-align:right;">' . $todayPretty . '<br><strong>Official Stamp / Signature</strong></td>
  </tr>
</table>
</body>
</html>';
    }

}
