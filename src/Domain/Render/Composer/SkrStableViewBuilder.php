<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;
use CargoDocsStudio\Domain\Render\Composer\Shared\NumberFormatter;

class SkrStableViewBuilder
{
    private NumberFormatter $formatter;
    private ImageResolver $images;

    public function __construct(NumberFormatter $formatter, ImageResolver $images)
    {
        $this->formatter = $formatter;
        $this->images = $images;
    }

    public function build(array $payload, array $trackingBlock): array
    {
        $companyName = sanitize_text_field((string) ($payload['skr_company_name'] ?? 'EXPRESS SECURITY LIMITED'));
        $companyRc = sanitize_text_field((string) ($payload['skr_company_rc'] ?? '1234567'));
        $companyLicense = sanitize_text_field((string) ($payload['skr_license_number'] ?? '77-7477'));
        $companyPhone = sanitize_text_field((string) ($payload['skr_company_phone'] ?? '+256 778 223 344'));
        $companyEmail = sanitize_email((string) ($payload['skr_company_email'] ?? 'expresssecurity@hotmail.com'));
        $companyAddress = sanitize_text_field((string) ($payload['skr_company_address'] ?? 'PLOT 32A KAMPALA ROAD KAMPALA UGANDA'));
        $logoUrl = $this->images->resolveImageSource((string) ($payload['company_logo_url'] ?? ''));
        $watermarkUrl = esc_url_raw((string) ($payload['skr_watermark_url'] ?? ($payload['watermark_url'] ?? '')));

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
        $insuranceRate = sanitize_text_field((string) ($payload['insurance_rate'] ?? '1.5%'));
        $storageFees = (float) ($payload['storage_fees'] ?? 0);
        $storageFeesLabel = sanitize_text_field((string) ($payload['storage_fees_label'] ?? ''));
        $totalValue = sanitize_text_field((string) ($payload['total_value'] ?? 'TBA'));
        $documentedCustomValue = sanitize_text_field((string) ($payload['documented_custom_value'] ?? 'T.B.A'));
        $representedBy = sanitize_text_field((string) ($payload['represented_by'] ?? 'N/A'));
        $receivingOfficer = sanitize_text_field((string) ($payload['receiving_officer'] ?? 'MR.KIMBUGWE FAISAL'));
        $regNumber = sanitize_text_field((string) ($payload['reg_number'] ?? 'ESL-A-205'));
        $supportingDocuments = sanitize_textarea_field((string) ($payload['supporting_documents'] ?? 'PRELIMINARY DOCUMENTATION'));
        $depositInstructions = sanitize_textarea_field((string) ($payload['deposit_instructions'] ?? ''));
        $depositorSignature = sanitize_textarea_field((string) ($payload['depositor_signature'] ?? ''));
        $additionalNotes = sanitize_textarea_field((string) ($payload['additional_notes'] ?? ''));
        $affidavitText = sanitize_textarea_field((string) ($payload['affidavit_text'] ?? ''));
        $issuerName = sanitize_text_field((string) ($payload['issuer_name'] ?? 'Kimbugwe Faisal'));
        $issuerTitle = sanitize_text_field((string) ($payload['issuer_title'] ?? 'ISSUING OFFICIERY LIMITED'));
        $stampLabel = sanitize_text_field((string) ($payload['stamp_label'] ?? 'Official Stamp / Signature'));
        $representedDate = sanitize_text_field((string) ($payload['represented_date'] ?? ''));
        $dateLabel = sanitize_text_field((string) ($payload['date_label'] ?? ''));
        $projectedDays = sanitize_text_field((string) ($payload['projected_days'] ?? 'N/A'));

        $todayDmY = esc_html(current_time('d-m-Y'));
        $displayRepresentedDate = esc_html($representedDate !== '' ? $representedDate : current_time('d-m-Y'));
        $displayDateLabel = esc_html($dateLabel !== '' ? $dateLabel : current_time('d M Y'));
        $displayStorageFees = $storageFeesLabel !== '' ? esc_html($storageFeesLabel) : ('PER DAY = $' . $this->formatter->formatSmart($storageFees, 2));
        $trackingCode = sanitize_text_field((string) ($payload['tracking_code'] ?? $depositNumber));
        $trackingQrUri = esc_url_raw((string) ($trackingBlock['data_uri'] ?? ''));
        $trackingFontSize = $this->computeTrackingFontSize($trackingCode);
        $trackingFontSizeCss = rtrim(rtrim(number_format($trackingFontSize, 2, '.', ''), '0'), '.');
        $fitProfile = $this->buildSkrFitProfile([
            'company_address' => $companyAddress,
            'opening' => 'We, ' . $companyName . ', a customs licensed bonded warehouse located in Kampala, Republic of Uganda hereby confirm with full legal and corporate responsibility that we have received in our safe Keeping the following as detailed below.',
            'supporting_documents' => $supportingDocuments,
            'deposit_instructions' => $depositInstructions,
            'additional_notes' => $additionalNotes,
            'affidavit' => $affidavitText,
            'depositor_signature' => $depositorSignature,
        ]);
        $fitClass = $fitProfile['css_class'] !== '' ? (' ' . $fitProfile['css_class']) : '';
        $addressFontCss = rtrim(rtrim(number_format($fitProfile['address_font'], 2, '.', ''), '0'), '.');
        $openingFontCss = rtrim(rtrim(number_format($fitProfile['opening_font'], 2, '.', ''), '0'), '.');
        $postFontCss = rtrim(rtrim(number_format($fitProfile['post_font'], 2, '.', ''), '0'), '.');
        $footerSpacer = str_repeat('<br>', (int) $fitProfile['footer_spacer_lines']);

        return [
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
        ];
    }

    private function computeTrackingFontSize(string $trackingCode): float
    {
        $length = function_exists('mb_strlen') ? (int) mb_strlen($trackingCode) : (int) strlen($trackingCode);
        $base = 10.0;
        if ($length <= 12) {
            return $base;
        }

        $size = $base - (($length - 12) * 0.18);

        return max(4.5, min($base, $size));
    }

    private function buildSkrFitProfile(array $content): array
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
