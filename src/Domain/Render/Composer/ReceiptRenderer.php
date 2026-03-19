<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;
use CargoDocsStudio\Domain\Render\Composer\Shared\NumberFormatter;

class ReceiptRenderer
{
    private NumberFormatter $formatter;
    private ImageResolver $images;

    public function __construct(?NumberFormatter $formatter = null, ?ImageResolver $images = null)
    {
        $this->formatter = $formatter ?: new NumberFormatter();
        $this->images = $images ?: new ImageResolver();
    }

    public function render(array $payload, ?array $paymentBlock, array $computed, array $theme): string
    {
        $dateRaw = sanitize_text_field((string) ($payload['receipt_date'] ?? $payload['invoice_date'] ?? current_time('Y-m-d')));
        $dateTs = strtotime($dateRaw);
        $date = $dateTs ? date_i18n('F j, Y', $dateTs) : $dateRaw;
        $receiptNumber = sanitize_text_field((string) ($payload['receipt_number'] ?? $payload['document_number'] ?? $payload['tracking_code'] ?? ''));
        $clientName = sanitize_text_field((string) ($payload['client_name'] ?? ''));
        $clientEmail = sanitize_email((string) ($payload['client_email'] ?? ''));
        $clientAddress = sanitize_textarea_field((string) ($payload['client_address'] ?? ''));
        $paymentMethod = sanitize_text_field((string) ($payload['payment_method'] ?? 'Bitcoin'));
        $paymentNetwork = sanitize_text_field((string) ($payload['payment_network'] ?? ''));
        $paymentReference = sanitize_text_field((string) ($payload['payment_reference'] ?? ''));
        $notes = sanitize_textarea_field((string) ($payload['notes'] ?? ''));
        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'USD')));
        $legacyQuantity = (float) ($payload['quantity'] ?? 0);
        $cargoType = sanitize_text_field((string) ($payload['cargo_type'] ?? $payload['content_description'] ?? 'Cargo'));
        $legacyUnitCost = (float) ($payload['taxable_value'] ?? 0);
        $legacyAmountPaid = isset($payload['amount_paid']) ? (float) $payload['amount_paid'] : 0.0;
        $logoInput = (string) ($payload['company_logo_url'] ?? $payload['logo_url'] ?? '');
        $logoUrl = $this->images->resolveImageSource($logoInput);
        if ($logoUrl === '' && preg_match('#^https?://#i', $logoInput)) {
            $logoUrl = esc_url_raw($logoInput);
        }
        $watermarkUrl = $this->images->resolveImageSource((string) ($payload['watermark_url'] ?? ''));
        $companyName = sanitize_text_field((string) ($payload['company_name'] ?? 'WAKALA MINERALS LIMITED'));
        $companyPhone = sanitize_text_field((string) ($payload['company_phone'] ?? '+256-751896060'));
        $companyEmail = sanitize_email((string) ($payload['company_email'] ?? 'info@wakalaminerals.com'));
        $companyWebsite = sanitize_text_field((string) ($payload['company_website'] ?? 'www.wakalaminerals.com'));
        $companyAddress = sanitize_textarea_field((string) ($payload['company_address'] ?? "TANK HILL ROAD, MUYENGA\nP.O.BOX 124439 KAMPALA-CPO"));
        $receiptTitle = sanitize_text_field((string) ($payload['receipt_title'] ?? 'PAYMENT RECEIPT'));
        $wetStampNote = sanitize_text_field((string) ($payload['wet_stamp_note'] ?? 'Valid Only If Wet Stamped.'));

        $paymentAddress = sanitize_text_field((string) ($payload['payment_wallet_address'] ?? $payload['bitcoin_wallet_address'] ?? $payload['wallet_address'] ?? $payload['payment_address'] ?? ''));
        $paymentUri = (string) ($paymentBlock['uri'] ?? '');
        if ($paymentUri === '' && $paymentAddress !== '') {
            $paymentUri = 'bitcoin:' . $paymentAddress;
        }
        if ($paymentUri === '' && $paymentReference !== '') {
            $paymentUri = 'payment-ref:' . $paymentReference;
        }
        if ($paymentUri === '') {
            $paymentUri = 'receipt:' . $receiptNumber;
        }
        $items = [];
        $hasLineItemsPayload = !empty($payload['line_items']) && is_array($payload['line_items']);
        $rawItemSets = [];
        if (!empty($payload['line_items']) && is_array($payload['line_items'])) {
            $rawItemSets[] = $payload['line_items'];
        }
        if (!empty($payload['items']) && is_array($payload['items'])) {
            $rawItemSets[] = $payload['items'];
        }
        if (!empty($payload['commodities']) && is_array($payload['commodities'])) {
            $rawItemSets[] = $payload['commodities'];
        }

        foreach ($rawItemSets as $rawItems) {
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $desc = sanitize_text_field((string) ($item['description'] ?? $item['cargo_type'] ?? $item['name'] ?? ''));
                $isOneTime = !empty($item['one_time']);
                $itemUnit = (float) ($item['unit_cost'] ?? $item['unit_price'] ?? ($item['amount'] ?? 0));
                $itemQty = $isOneTime ? 0.0 : (float) ($item['quantity'] ?? 0);
                $itemAmount = isset($item['amount']) ? (float) $item['amount'] : (isset($item['total']) ? (float) $item['total'] : ($itemUnit * $itemQty));
                if (!$isOneTime && $itemAmount <= 0 && $itemUnit > 0 && $itemQty > 0) {
                    $itemAmount = $itemUnit * $itemQty;
                }
                if ($desc === '') {
                    continue;
                }
                $items[] = [
                    'description' => $desc,
                    'unit' => $itemUnit,
                    'qty' => $itemQty,
                    'amount' => $itemAmount,
                    'one_time' => $isOneTime,
                ];
            }
            if (!empty($items)) {
                break;
            }
        }
        if (empty($items)) {
            $fallbackAmount = $legacyAmountPaid > 0
                ? $legacyAmountPaid
                : ((float) ($computed['grand_total'] ?? 0) > 0 ? (float) $computed['grand_total'] : ($legacyUnitCost * max($legacyQuantity, 1)));
            $fallbackQty = $legacyQuantity > 0 ? $legacyQuantity : 0.0;
            $isOneTimeFallback = $fallbackQty <= 0;
            $items[] = [
                'description' => $cargoType,
                'unit' => $isOneTimeFallback ? 0.0 : $legacyUnitCost,
                'qty' => $isOneTimeFallback ? 0.0 : $fallbackQty,
                'amount' => $fallbackAmount,
                'one_time' => $isOneTimeFallback,
            ];
        }
        if (count($items) === 1 && $legacyAmountPaid > 0 && !$hasLineItemsPayload) {
            if ((float) $items[0]['amount'] <= 0) {
                $items[0]['amount'] = $legacyAmountPaid;
            }
            if ((float) $items[0]['unit'] <= 0 && (float) $items[0]['qty'] > 0 && empty($items[0]['one_time'])) {
                $items[0]['unit'] = $legacyAmountPaid / (float) $items[0]['qty'];
            }
        }

        $rowsHtml = '';
        $itemsTotal = 0.0;
        foreach ($items as $index => $item) {
            $itemsTotal += (float) $item['amount'];
            $isOneTime = !empty($item['one_time']);
            $qtyLabel = '-';
            if (!$isOneTime) {
                $qtyLabel = rtrim(rtrim(number_format((float) $item['qty'], 3, '.', ''), '0'), '.');
                if ($qtyLabel === '') {
                    $qtyLabel = '0';
                }
            }
            $rowClass = ($index % 2 === 0) ? 'commodity-row-odd' : 'commodity-row-even';
            $rowsHtml .= '<tr class="' . esc_attr($rowClass) . '">
      <td>' . esc_html($item['description']) . '</td>
      <td class="amount-right">' . ($isOneTime ? '-' : ('$' . $this->formatter->formatSmart((float) $item['unit'], 2))) . '</td>
      <td class="center">' . esc_html($qtyLabel) . '</td>
      <td class="amount-right">$' . $this->formatter->formatSmart((float) $item['amount'], 2) . '</td>
    </tr>';
        }
        $displayTotal = $itemsTotal > 0 ? $itemsTotal : ($legacyAmountPaid > 0 ? $legacyAmountPaid : $itemsTotal);
        $displayTotalWords = ucwords(strtolower($this->formatter->moneyToWords($displayTotal, $currency)));

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
body{font-family:' . esc_attr($theme['font_family']) . ',Arial,sans-serif;margin:0;padding:0;color:#333;font-size:10pt;}
.sheet{position:relative;padding:14px;}
.watermark{position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:56%;max-width:140mm;opacity:0.2;z-index:0;pointer-events:none;}
.content{position:relative;z-index:1;}
.content-main{padding-bottom:135px;}
.header-table,.grid-table,.commodity-table,.foot-table{width:100%;border-collapse:collapse;}
.header-table td{vertical-align:bottom;border:none;padding:0;}
.header-wrap{margin-bottom:4px;}
.header-left{text-align:left;vertical-align:bottom;}
.header-center{text-align:center;vertical-align:bottom;}
.header-right{text-align:right;vertical-align:bottom;}
.logo{width:220px;height:auto;}
.title{font-size:21pt;font-weight:700;letter-spacing:0.6px;color:#2d4360;margin:0;}
.meta{font-size:10pt;text-align:right;line-height:1.35;}
.accent-line{border-top:2px solid #f4a460;margin:8px 0 10px;}
.sec-head{display:block;width:100%;box-sizing:border-box;background:#d2a272;color:#1f3550;font-weight:700;padding:8px 10px;border:1px solid #ddd;font-size:10pt;}
.sec-body{display:block;width:100%;box-sizing:border-box;border:1px solid #ddd;border-top:none;padding:10px 12px;font-size:10pt;}
.grid-table{margin-bottom:10px;}
.grid-table td{border:none;padding:0;vertical-align:top;}
.section-full{width:100%;margin-bottom:10px;}
.commodity-table{margin:12px 0;}
.commodity-table th{background:#d2a272;color:#1f3550;font-weight:700;padding:8px 10px;border:1px solid #bbb;text-align:center;}
.commodity-table td{padding:7px 10px;border:1px solid #ddd;}
.commodity-table .commodity-row-odd td{background:#f8f8f8;}
.commodity-table .commodity-row-even td{background:#ffffff;}
.receipt-total-row td{background:#d2a272;color:#fff;font-weight:700;}
.receipt-total-row .amount-right{font-size:14pt;}
.receipt-total-words-row td{background:#d2a272;color:#fff;font-weight:700;text-align:right;}
.amount-right{text-align:right;}
.center{text-align:center;}
.total-line{margin-top:8px;text-align:right;font-size:21pt;font-weight:700;color:#111;}
.muted{color:#555;}
.payment-block{margin-top:12px;}
.payment-body{padding:0;font-size:10pt;}
.payment-grid{width:100%;border-collapse:collapse;}
.payment-grid td{border:none;vertical-align:top;}
.wallet-qr{width:145px;height:145px;border:1px solid #ddd;}
.contact-line{border-top:2px solid #ff0000;margin:14px 0 6px;}
.contact-table{width:100%;border-collapse:collapse;font-size:9pt;}
.contact-table td{border:none;padding:4px 2px;vertical-align:top;}
.contact-bar{margin-top:10px;background:#2d4360;color:#fff;border-radius:6px;padding:10px;}
.contact-bar .line-1{font-size:10pt;text-align:center;}
.contact-bar .line-2{font-size:10pt;text-align:center;margin-top:4px;}
.bottom-note{margin-top:8px;text-align:center;font-style:italic;font-size:11pt;}
.receipt-footer{
position:fixed;
left:0;
right:0;
bottom:0;
z-index:2;
padding:0 14px 4mm;
box-sizing:border-box;
}
</style>
</head>
<body>
<div class="sheet">
' . ($watermarkUrl !== '' ? '<img src="' . esc_attr($watermarkUrl) . '" class="watermark" alt="" />' : '') . '
<div class="content">
  <div class="content-main">
  <div class="header-wrap">
    <table class="header-table">
      <tr>
        <td class="header-left" style="width:33.33%;">
          ' . ($logoUrl !== '' ? '<img src="' . esc_attr($logoUrl) . '" class="logo" alt="Company Logo" />' : '') . '
        </td>
        <td class="header-center" style="width:33.33%;">
          <div class="title">' . esc_html($receiptTitle) . '</div>
        </td>
        <td class="header-right" style="width:33.33%;">
          <div class="meta" style="font-size:13pt;"><strong>Date:</strong> ' . esc_html($date) . '</div>
          <div class="meta" style="font-size:13pt;"><strong>Receipt No:</strong> <span style="color:#df3f36;"><strong>' . esc_html($receiptNumber) . '</strong></span></div>
        </td>
      </tr>
    </table>
  </div>
  <div class="accent-line"></div>

  <div class="section-full">
    <div class="sec-head">Received From</div>
    <div class="sec-body">
      <strong>' . esc_html($clientName) . '</strong><br>
      ' . esc_html($clientEmail) . '
      ' . ($clientAddress !== '' ? '<br>' . nl2br(esc_html($clientAddress)) : '') . '
    </div>
  </div>

  <table class="commodity-table">
    <tr>
      <th style="width:44%;">Description</th>
      <th style="width:16%;">Unit cost ($)</th>
      <th style="width:16%;">Quantity</th>
      <th style="width:24%;">Amount (USD)</th>
    </tr>
    ' . $rowsHtml . '
    <tr class="receipt-total-row">
      <td colspan="3" class="center">TOTAL (' . esc_html($currency) . ')</td>
      <td class="amount-right">$' . $this->formatter->formatSmart($displayTotal, 2) . '</td>
    </tr>
    <tr class="receipt-total-words-row">
      <td colspan="4"><strong>' . esc_html($displayTotalWords) . ' Only.</strong></td>
    </tr>
  </table>

  ' . ($notes !== '' ? '<div class="sec-head">Special notes and Instructions</div><div class="sec-body muted">' . nl2br(esc_html($notes)) . '</div>' : '') . '

  <div class="payment-block">
    <div class="payment-body">
      <table class="payment-grid">
        <tr>
          <td style="width:100%;">
            <strong>Payment Method:</strong> ' . esc_html($paymentMethod) . '<br>
            ' . ($paymentReference !== '' ? '<strong>Reference:</strong> ' . esc_html($paymentReference) . '<br>' : '') . '
            ' . ($paymentAddress !== '' ? '<strong>Wallet:</strong> ' . esc_html($paymentAddress) . '<br>' : '') . '
            ' . ($paymentNetwork !== '' ? '<strong>Network:</strong> ' . esc_html($paymentNetwork) . '<br>' : '') . '
            <br>
            <strong>For and on behalf of</strong><br><br>
            ......................................<br>
            ' . esc_html($companyName) . '
          </td>
        </tr>
      </table>
    </div>
  </div>
  </div>
  <div class="receipt-footer">
  <div class="contact-bar">
    <div class="line-1">☎ ' . esc_html($companyPhone) . ' | ✉ ' . esc_html($companyEmail) . ' | ⌂ ' . esc_html($companyWebsite) . '</div>
    <div class="line-2">⌂ ' . nl2br(esc_html($companyAddress)) . '</div>
  </div>
  <div class="bottom-note">' . esc_html($wetStampNote) . '</div>
  </div>
</div>
</div>
</body>
</html>';
    }
}
