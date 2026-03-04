<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;
use CargoDocsStudio\Domain\Render\Composer\Shared\NumberFormatter;

class SkrRenderer
{
    private NumberFormatter $formatter;
    private ImageResolver $images;
    private SkrStableViewBuilder $stableViewBuilder;

    public function __construct(?NumberFormatter $formatter = null, ?ImageResolver $images = null)
    {
        $this->formatter = $formatter ?: new NumberFormatter();
        $this->images = $images ?: new ImageResolver();
        $this->stableViewBuilder = new SkrStableViewBuilder($this->formatter, $this->images);
    }

    public function renderStable(array $payload, array $trackingBlock, array $theme): string
    {
        [
            'companyName' => $companyName,
            'companyRc' => $companyRc,
            'companyLicense' => $companyLicense,
            'companyPhone' => $companyPhone,
            'companyEmail' => $companyEmail,
            'companyAddress' => $companyAddress,
            'logoUrl' => $logoUrl,
            'watermarkUrl' => $watermarkUrl,
            'depositNumber' => $depositNumber,
            'depositorName' => $depositorName,
            'custodyType' => $custodyType,
            'contentDescription' => $contentDescription,
            'quantity' => $quantity,
            'unit' => $unit,
            'packagesNumber' => $packagesNumber,
            'declaredValue' => $declaredValue,
            'originOfGoods' => $originOfGoods,
            'depositType' => $depositType,
            'insuranceRate' => $insuranceRate,
            'totalValue' => $totalValue,
            'documentedCustomValue' => $documentedCustomValue,
            'representedBy' => $representedBy,
            'receivingOfficer' => $receivingOfficer,
            'regNumber' => $regNumber,
            'supportingDocuments' => $supportingDocuments,
            'depositInstructions' => $depositInstructions,
            'depositorSignature' => $depositorSignature,
            'additionalNotes' => $additionalNotes,
            'affidavitText' => $affidavitText,
            'issuerName' => $issuerName,
            'issuerTitle' => $issuerTitle,
            'stampLabel' => $stampLabel,
            'todayDmY' => $todayDmY,
            'displayRepresentedDate' => $displayRepresentedDate,
            'displayDateLabel' => $displayDateLabel,
            'displayStorageFees' => $displayStorageFees,
            'trackingCode' => $trackingCode,
            'trackingQrUri' => $trackingQrUri,
            'trackingFontSizeCss' => $trackingFontSizeCss,
            'fitClass' => $fitClass,
            'addressFontCss' => $addressFontCss,
            'openingFontCss' => $openingFontCss,
            'postFontCss' => $postFontCss,
            'footerSpacer' => $footerSpacer,
            'projectedDays' => $projectedDays,
        ] = $this->stableViewBuilder->build($payload, $trackingBlock);

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;margin:0;padding:0;color:#333;font-size:10pt;}
.skr-sheet{position:relative;min-height:285mm;}
.skr-content{position:relative;z-index:2;}
.header-table{width:100%;margin-bottom:5px;border-collapse:collapse;}
.header-table td{vertical-align:top;padding:0;}
.logo{width:170px;height:auto;}
.doc-title{font-size:17pt;font-weight:700;color:#1e4d72;margin:2px 0;text-align:center;}
.doc-subtitle{font-size:11pt;font-style:italic;margin:0 0 8px 0;text-align:center;}
.table{width:100%;border-collapse:collapse;margin:8px 0;}
.table th{background:#efefef;padding:5px 7px;border:1px solid #222;font-size:10pt;text-align:left;color:#1e4d72;}
.table td{padding:6px 8px;border:1px solid #222;font-size:10pt;vertical-align:top;}
.red{color:#d32f2f;font-weight:700;}
.blue{color:#1e4d72;font-weight:700;}
.tracking{text-align:center;color:#b71c1c;font-weight:700;margin:10px 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.watermark-fallback{position:absolute;left:50%;top:58%;transform:translate(-50%,-50%);width:120mm;max-width:72%;opacity:0.5;z-index:1;pointer-events:none;}
.fit-compact .header-table{margin-bottom:3px;}
.fit-compact .doc-title{margin:1px 0;}
.fit-compact .doc-subtitle{margin:0 0 5px 0;}
.fit-compact .tracking{margin:6px 0 2px;}
.fit-compact .sign-left,.fit-compact .sign-right{font-size:9.2pt;line-height:1.05;}
.fit-compact .sign-left .spacer{display:none;}
.fit-tight .header-table{margin-bottom:2px;}
.fit-tight .doc-title{font-size:16pt;margin:0;}
.fit-tight .doc-subtitle{font-size:10pt;margin:0 0 4px 0;}
.fit-tight .tracking{margin:4px 0 1px;}
.fit-tight .sign-left,.fit-tight .sign-right{font-size:8.8pt;line-height:1.02;}
.fit-tight .sign-left .spacer{display:none;}
</style>
</head>
<body>
<div class="skr-sheet' . esc_attr($fitClass) . '">
' . ($watermarkUrl !== '' ? '<img src="' . esc_attr($watermarkUrl) . '" class="watermark-fallback" alt="" />' : '') . '
<div class="skr-content">
<table class="header-table">
  <tr>
    <td style="width:32%;">' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="' . esc_attr($companyName) . '" />' : '') . '</td>
    <td style="width:68%;text-align:right;">
      <div style="font-size:16pt;font-weight:700;color:#1e4d72;">' . esc_html($companyName) . '</div>
      <div style="font-size:' . esc_attr($addressFontCss) . 'pt;">RC-Number: ' . esc_html($companyRc) . '</div>
      <div style="font-size:' . esc_attr($addressFontCss) . 'pt;">License Number: ' . esc_html($companyLicense) . '</div>
      <div style="font-size:' . esc_attr($addressFontCss) . 'pt;">Tel: ' . esc_html($companyPhone) . '</div>
      <div style="font-size:' . esc_attr($addressFontCss) . 'pt;">Email: ' . esc_html($companyEmail) . '</div>
      <div style="font-size:' . esc_attr($addressFontCss) . 'pt;">' . esc_html($companyAddress) . '</div>
    </td>
  </tr>
</table>
<div class="doc-title">SAFE KEEPING RECEIPT</div>
<div class="doc-subtitle">Valid Only In Original</div>

<div style="font-size:' . esc_attr($openingFontCss) . 'pt;margin:6px 0;">
  We, <strong>' . esc_html($companyName) . '</strong>, a customs licensed bonded warehouse located in Kampala, Republic of Uganda hereby confirm with full legal and corporate responsibility that we have received in our safe Keeping the following as detailed below.
</div>

<table class="table">
  <tr><th style="width:30%;">CUSTODY TYPE</th><th style="width:35%;">Depositors Tracking Number</th><th>Date of Receipt</th></tr>
  <tr><td><span class="red">' . esc_html($custodyType) . '</span></td><td><strong>' . esc_html($depositNumber) . '</strong></td><td>' . $todayDmY . '</td></tr>
  <tr><th>DEPOSITOR(S) NAME AND ADDRESS</th><th>Projected Days of Custody</th><th>Documented Custom Value Amount (US$)</th></tr>
  <tr><td><span class="red">' . esc_html($depositorName) . '</span></td><td>' . esc_html($projectedDays) . '</td><td>' . esc_html($documentedCustomValue) . '</td></tr>
  <tr><th>Represented Date</th><th>Represented BY</th><th>Receiving Officer</th></tr>
  <tr><td>' . $displayRepresentedDate . '</td><td>' . esc_html($representedBy) . '</td><td><strong>' . esc_html($receivingOfficer) . '</strong></td></tr>
  <tr><th colspan="3">Reg Number: <strong>' . esc_html($regNumber) . '</strong></th></tr>
</table>

<table class="table">
  <tr><th style="width:28%;">Details Description of Contents</th><th style="width:14%;">Quantity</th><th style="width:16%;">Number of Packages</th><th style="width:18%;">Value (US$)</th><th style="width:24%;">Origin of Goods</th></tr>
  <tr><td><span class="red">' . esc_html($contentDescription) . '</span></td><td>' . esc_html($quantity) . ' ' . esc_html($unit) . '</td><td>' . esc_html($packagesNumber) . '</td><td>USD: ' . $this->formatter->formatSmart($declaredValue, 2) . '</td><td>' . esc_html($originOfGoods) . '</td></tr>
  <tr><th>Type of Deposit</th><th colspan="2">Total Value</th><th colspan="2">Insurance Value</th></tr>
  <tr><td>' . esc_html($depositType) . '</td><td colspan="2">' . esc_html($totalValue) . '</td><td colspan="2">' . esc_html($insuranceRate) . '</td></tr>
  <tr><th colspan="3">Supporting Documents of Goods</th><th colspan="2">CD Storage Fees</th></tr>
  <tr><td colspan="3">' . nl2br(esc_html($supportingDocuments)) . '</td><td colspan="2">' . $displayStorageFees . '</td></tr>
  <tr><th colspan="3">Deposition Instructions (if any)</th><th colspan="2">Date</th></tr>
  <tr><td colspan="3">' . nl2br(esc_html($depositInstructions)) . '</td><td colspan="2">' . $displayDateLabel . '</td></tr>
  <tr><th style="width:22%;">Date</th><th colspan="2">Depositor&apos;s Signature</th><th colspan="2">Additional Information</th></tr>
  <tr><td><span class="red">' . $displayDateLabel . '</span></td><td colspan="2">' . nl2br(esc_html($depositorSignature)) . '</td><td colspan="2">' . nl2br(esc_html($additionalNotes)) . '</td></tr>
</table>

<div style="font-size:' . esc_attr($postFontCss) . 'pt;margin:8px 0;">
  We, <strong>' . esc_html($companyName) . '</strong>, further confirm that these goods in our safe Keeping are legally earned, good, clean and cleared of non-criminal origin, free of any liens or encumbrances and are freely transferable upon the instructions of an authorized signatory (The depositor).
</div>

<div class="tracking" style="font-size:' . esc_attr($trackingFontSizeCss) . 'pt;">TRACK YOUR CONSIGNMENT ON OUR OFFICIAL WEBSITE WITH THE TRACKING NUMBER ' . esc_html($trackingCode) . '</div>
' . ($affidavitText !== '' ? '<div style="font-size:' . esc_attr($postFontCss) . 'pt;margin:6px 0;">' . nl2br(esc_html($affidavitText)) . '</div>' : '') . '
' . ($trackingQrUri !== '' ? '<div style="text-align:center;"><img src="' . esc_attr($trackingQrUri) . '" style="width:52px;height:52px;border:1px solid #ddd;" alt="Tracking QR" /></div>' : '') . '
<br>
<table style="width:100%;border-collapse:collapse;margin-top:8px;">
  <tr>
    <td class="sign-left" style="width:50%;font-size:10pt;border:none;"><span class="spacer">' . $footerSpacer . '</span>' . esc_html($issuerName) . '<span class="spacer">' . $footerSpacer . '</span><strong style="border-top:2px solid #000;"><br><br><br><br><br><br>' . esc_html($issuerTitle) . '<span class="spacer">' . $footerSpacer . '</span></strong></td>
    <td class="sign-right" style="width:50%;font-size:10pt;border:none;text-align:right;"><br><br><br>' . $displayDateLabel . '<br><strong>' . esc_html($stampLabel) . '</strong></td>
  </tr>
</table>
</div>
</div>
</body>
</html>';
    }

    public function computeTrackingFontSize(string $trackingCode): float
    {
        $length = function_exists('mb_strlen') ? (int) mb_strlen($trackingCode) : (int) strlen($trackingCode);
        $base = 10.0;
        if ($length <= 12) {
            return $base;
        }

        $size = $base - (($length - 12) * 0.18);

        return max(4.5, min($base, $size));
    }

    public function buildSkrFitProfile(array $content): array
    {
        $address = (string) ($content['company_address'] ?? '');
        $opening = (string) ($content['opening'] ?? '');
        $supporting = (string) ($content['supporting_documents'] ?? '');
        $instructions = (string) ($content['deposit_instructions'] ?? '');
        $additional = (string) ($content['additional_notes'] ?? '');
        $affidavit = (string) ($content['affidavit'] ?? '');
        $signature = (string) ($content['depositor_signature'] ?? '');

        $len = fn(string $v): int => function_exists('mb_strlen') ? (int) mb_strlen($v) : (int) strlen($v);
        $lineCount = fn(string $v): int => max(1, substr_count($v, "\n") + 1);

        $score = 0;
        $score += max(0, $len($address) - 48);
        $score += max(0, $len($opening) - 180);
        $score += max(0, $len($supporting) - 80);
        $score += max(0, $len($instructions) - 70);
        $score += max(0, $len($additional) - 70);
        $score += max(0, $len($affidavit) - 120);
        $score += max(0, $len($signature) - 70);
        $score += max(0, ($lineCount($supporting) - 4) * 24);
        $score += max(0, ($lineCount($affidavit) - 3) * 26);

        $fitLevel = 'normal';
        if ($score >= 160) {
            $fitLevel = 'tight';
        } elseif ($score >= 70) {
            $fitLevel = 'compact';
        }

        $addressFont = 10.0;
        $openingFont = 10.0;
        $postFont = 10.0;
        $footerSpacerLines = 3;
        $cssClass = '';

        if ($fitLevel === 'compact') {
            $addressFont = 9.2;
            $openingFont = 9.2;
            $postFont = 9.1;
            $footerSpacerLines = 1;
            $cssClass = 'fit-compact';
        } elseif ($fitLevel === 'tight') {
            $addressFont = 8.9;
            $openingFont = 8.9;
            $postFont = 8.7;
            $footerSpacerLines = 0;
            $cssClass = 'fit-tight';
        }

        return [
            'fit_level' => $fitLevel,
            'css_class' => $cssClass,
            'address_font' => $addressFont,
            'opening_font' => $openingFont,
            'post_font' => $postFont,
            'footer_spacer_lines' => $footerSpacerLines,
        ];
    }
}
