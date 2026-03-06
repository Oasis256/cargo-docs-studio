<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;
use CargoDocsStudio\Domain\Render\Composer\Shared\NumberFormatter;
use CargoDocsStudio\Domain\Render\Composer\Shared\FinancialCalculator;

class InvoiceRenderer
{
    private NumberFormatter $formatter;
    private ImageResolver $images;
    private FinancialCalculator $calculator;

    public function __construct(?NumberFormatter $formatter = null, ?ImageResolver $images = null, ?FinancialCalculator $calculator = null)
    {
        $this->formatter = $formatter ?: new NumberFormatter();
        $this->images = $images ?: new ImageResolver();
        $this->calculator = $calculator ?: new FinancialCalculator();
    }

    public function render(array $payload, ?array $paymentBlock, array $theme): string
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
        $unit = strtoupper(sanitize_text_field((string) ($payload['unit'] ?? 'KGS')));
        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'USD')));
        $purity = sanitize_text_field((string) ($payload['purity'] ?? ''));
        $caratsRaw = sanitize_text_field((string) ($payload['carats'] ?? $payload['carats_percent'] ?? ''));
        $purityDisplay = $purity;
        if ($purityDisplay !== '' && !str_contains($purityDisplay, '%')) {
            $purityDisplay .= '%';
        }
        $detailParts = [];
        if ($purityDisplay !== '') {
            $detailParts[] = $purityDisplay;
        }
        if ($purityDisplay !== '' && $caratsRaw === '') {
            $lastIdx = count($detailParts) - 1;
            $detailParts[$lastIdx] .= ' Pure';
        }
        if ($caratsRaw !== '') {
            $detailParts[] = is_numeric($caratsRaw)
                ? $this->formatter->formatSmart((float) $caratsRaw, 2) . ' carats'
                : $caratsRaw;
        }
        $caratsPart = !empty($detailParts) ? (' (' . implode(' + ', $detailParts) . ')') : '';
        $paymentQrUri = $paymentBlock && !empty($paymentBlock['data_uri']) ? esc_url_raw((string) $paymentBlock['data_uri']) : '';
        $paymentAddress = sanitize_text_field((string) ($payload['payment_wallet_address'] ?? $payload['wallet_address'] ?? $payload['payment_address'] ?? ''));
        $paymentNetwork = sanitize_text_field((string) ($payload['payment_network'] ?? 'TRON (TRC20)'));
        $bankName = sanitize_text_field((string) ($payload['bank_name'] ?? 'ECO BANK UGANDA LIMITED'));
        $bankAccountNumber = sanitize_text_field((string) ($payload['bank_account_number'] ?? '7170009076'));
        $bankAccountName = sanitize_text_field((string) ($payload['bank_account_name'] ?? 'WAKALA MINERALS LIMITED'));
        $bankSwiftCode = sanitize_text_field((string) ($payload['bank_swift_code'] ?? 'ECOCUGKA'));
        $bankAddress = sanitize_text_field((string) ($payload['bank_address'] ?? 'PLOT 8A KAFU ROAD KAMPALA UGANDA'));
        $paymentUri = (string) ($paymentBlock['uri'] ?? '');
        if ($paymentUri === '' && $paymentAddress !== '') {
            $paymentUri = 'bitcoin:' . $paymentAddress;
        }
        $paymentQrSource = $this->images->resolveQrImageSource(
            $paymentQrUri,
            $paymentUri,
            180
        );
        $logoCandidate = (string) ($payload['company_logo_url'] ?? $payload['logo_url'] ?? '');
        $logoUrl = $this->images->resolveImageSource($logoCandidate);
        if ($logoUrl === '' && preg_match('#^https?://#i', $logoCandidate)) {
            $logoUrl = esc_url_raw($logoCandidate);
        }
        $watermarkUrl = $this->images->resolveImageSource((string) ($payload['watermark_url'] ?? ''));

        $taxRate = $this->calculator->normalizeRatePercent($payload['tax_rate'] ?? 5.0, 5.0);
        $insuranceRate = $this->calculator->normalizeRatePercent($payload['insurance_rate'] ?? 1.5, 1.5);
        $smeltingCost = (float) ($payload['smelting_cost'] ?? 0);
        $certOrigin = (float) ($payload['cert_origin'] ?? 0);
        $certOwnership = (float) ($payload['cert_ownership'] ?? 0);
        $exportPermit = (float) ($payload['export_permit'] ?? 0);
        $freightCost = (float) ($payload['freight_cost'] ?? 0);
        $agentFees = (float) ($payload['agent_fees'] ?? 0);

        $declaredSubtotal = $taxableValue * $quantity;
        $taxAmount = $declaredSubtotal * ($taxRate / 100);
        $insuranceAmount = $declaredSubtotal * ($insuranceRate / 100);
        $smeltingTotal = $smeltingCost * $quantity;
        $freightTotal = $freightCost * $quantity;
        $agentTotal = $agentFees;
        $totalAmount = $taxAmount + $insuranceAmount + $smeltingTotal + $certOrigin + $certOwnership + $exportPermit + $freightTotal + $agentTotal;
        if ($totalAmount <= 0) {
            $totalAmount = 0.0;
        }
        $totalAmountWords = $this->formatter->moneyToWords($totalAmount, $currency);

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;margin:0;padding:0;color:#333;font-size:10pt;}
.skr-sheet{position:relative;}
.skr-content{position:relative;z-index:2;}
.invoice-watermark{position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:58%;max-width:140mm;opacity:0.2;z-index:1;pointer-events:none;}
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
.total-words-row td{padding:8px 10px;font-size:10pt;text-align:right;color:#fff;}
.network-section{font-size:9pt;color:#666;text-align:center;}
.mono{font-family:monospace;font-size:9pt;word-break:break-all;}
.bank-qr-table{width:100%;border-collapse:collapse;margin-top:10px;}
.bank-qr-table td{vertical-align:top;padding:6px 8px;}
.signature-block{width:100%;text-align:center;margin-top:6px;}
</style>
</head>
<body>
<div class="skr-sheet">
' . ($watermarkUrl !== '' ? '<img src="' . esc_attr($watermarkUrl) . '" class="invoice-watermark" alt="" />' : '') . '
<div class="skr-content">
<table class="header-table">
  <tr>
    <td style="width:30%;">
      ' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="Company Logo" />' : '') . '
    </td>
    <td style="text-align:right;width:30%;vertical-align:bottom;">
      <div class="invoice-title">TAX INVOICE</div>
    </td>
    <td style="text-align:right;width:30%;vertical-align:top;">
      <div style="margin-bottom:15px;">
        <strong>Date:</strong> ' . esc_html($date) . '<br>
        <strong>Invoice:</strong> ' . esc_html($invoiceNumber) . '
      </div>
    </td>
  </tr>
</table>

<div class="product-info">
  <h3>' . esc_html($cargoType) . '</h3>
  <div class="product-details">
    ' . $this->formatter->formatSmart($quantity, 1) . ' ' . esc_html($unit) . $caratsPart . '<br>
    Declared taxable value: ' . $this->formatter->formatSmart($taxableValue, 3) . ' ' . esc_html($currency) . ' per kg
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
    <td>Tax on declared subtotal</td>
    <td class="amount-column">' . $this->formatter->formatSmart($taxRate, 2) . '%</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($taxAmount, 2) . '</td>
  </tr>
  <tr>
    <td>Insurance on declared subtotal</td>
    <td class="amount-column">' . $this->formatter->formatSmart($insuranceRate, 2) . '%</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($insuranceAmount, 2) . '</td>
  </tr>
  <tr>
    <td>Smelting</td>
    <td class="amount-column">' . $this->formatter->formatSmart($smeltingCost, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' KGS</td>
    <td class="amount-column">' . $this->formatter->formatSmart($smeltingTotal, 2) . '</td>
  </tr>
  <tr>
    <td>Certificate of Origin</td>
    <td class="amount-column">-</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($certOrigin, 2) . '</td>
  </tr>
  <tr>
    <td>Certificate of Ownership</td>
    <td class="amount-column">-</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($certOwnership, 2) . '</td>
  </tr>
  <tr>
    <td>Export Permit</td>
    <td class="amount-column">-</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($exportPermit, 2) . '</td>
  </tr>
  <tr>
    <td>Freight charges</td>
    <td class="amount-column">' . $this->formatter->formatSmart($freightCost, 2) . '</td>
    <td class="amount-column">' . number_format($quantity, 0) . ' ' . esc_html($unit) . '</td>
    <td class="amount-column">' . $this->formatter->formatSmart($freightTotal, 2) . '</td>
  </tr>
  <tr>
    <td>Agent fees, Security &amp; handling</td>
    <td class="amount-column">' . $this->formatter->formatSmart($agentFees, 2) . '</td>
    <td class="amount-column">-</td>
    <td class="amount-column">' . $this->formatter->formatSmart($agentTotal, 2) . '</td>
  </tr>
  <tr class="total-row">
    <td colspan="3" style="text-align:center;font-size:14pt;"><strong>TOTAL (' . esc_html($currency) . ')</strong></td>
    <td class="amount-column" style="font-size:14pt;"><strong>' . $this->formatter->formatSmart($totalAmount, 2) . '</strong></td>
  </tr>
  <tr class="total-row total-words-row">
    <td colspan="4">
      <strong>' . esc_html($totalAmountWords) . ' only.</strong>
    </td>
  </tr>
</table>

<table class="bank-qr-table" style="margin:45% 0 0 0;">
  <tr>
    <td style="width:60%;text-align:left;vertical-align:top;">
  
      <div style="font-size:10pt;line-height:2;color:#333;">
        <strong>BANK NAME:</strong> ' . esc_html($bankName) . '<br>
        <strong>ACCOUNT NUMBER:</strong> ' . esc_html($bankAccountNumber) . '<br>
        <strong>ACCOUNT NAME:</strong> ' . esc_html($bankAccountName) . '<br>
        <strong>SWIFT CODE:</strong> ' . esc_html($bankSwiftCode) . '<br>
        <strong>BANK ADDRESS:</strong> ' . esc_html($bankAddress) . '
      </div>
    </td>
    <td style="width:15%;text-align:right;">
    
      <strong>NETWORK:</strong>'. esc_html($paymentNetwork) .'<br>
      ' . ($paymentQrSource !== '' ? '<img src="' . esc_attr($paymentQrSource) . '" style="width:120px;height:120px;border:1px solid #ddd;" alt="Payment QR" />' : '') . '<br>
      <strong>ADDRESS:</strong>'. esc_html($paymentAddress) .'
    </td>
  </tr>
</table>


<div class="signature-block" style="text-align:left">
  <div style="margin-bottom:5px;">
    <strong>For and on behalf of</strong></div>
  <div style="height:10px;min-height:5px;">&nbsp;</div>
  <div style="border-top:1px solid #333;padding-top:1px;width:300px;margin:0;">
    <strong>' . esc_html((string) ($payload['company_name'] ?? 'WAKALA Minerals Limited')) . '</strong>
  </div>
</div>

<div style="border-top:2px solid #ff0000;margin:20px 0 100px 0;"></div>
<table style="width:100%;border-collapse:collapse;font-size:9pt;color:#333;">
  <tr>
    <td style="width:33%;text-align:left;vertical-align:top;padding:5px;"><strong>☎ ' . esc_html((string) ($payload['company_phone'] ?? '+256-751896060')) . '</strong></td>
    <td style="width:34%;text-align:center;vertical-align:top;padding:5px;"><strong style="color:#0066cc;">✉ ' . esc_html((string) ($payload['company_email'] ?? 'info@wakalaminerals.com')) . '</strong></td>
    <td style="width:33%;text-align:right;vertical-align:top;padding:5px;"><strong>⌂ ' . nl2br(esc_html((string) ($payload['company_address'] ?? 'TANK HILL ROAD, MUYENGA' . "\n" . 'P.O.BOX 124439 KAMPALA-CPO'))) . '</strong></td>
    </tr>
</table>
</div>
</div>
</body>
</html>';
    }
}
