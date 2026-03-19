<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\ImageResolver;

class SpaRenderer
{
    private ImageResolver $images;

    public function __construct(?ImageResolver $images = null)
    {
        $this->images = $images ?: new ImageResolver();
    }

    public function render(array $payload): string
    {
        $logoCandidate = (string) ($payload['spa_logo_url'] ?? $payload['company_logo_url'] ?? $payload['logo_url'] ?? '');
        $logoUrl = $this->images->resolveImageSource($logoCandidate);
        $watermarkEnabled = !empty($payload['watermark_enabled']);
        $watermarkUrl = $watermarkEnabled
            ? $this->images->resolveImageSource((string) ($payload['watermark_url'] ?? ''))
            : '';
        $sellerInitials = sanitize_text_field((string) ($payload['seller_initials'] ?? "Seller's Initials"));
        $buyerInitials = sanitize_text_field((string) ($payload['buyer_initials'] ?? "Buyer's Initials"));

        $tableBlocks = $this->normalizeTableBlocks($payload['spa_tables'] ?? []);
        $textBlocks = $this->normalizeTextBlocks($payload['spa_text_walls'] ?? []);
        $imageBlocks = $this->normalizeImageBlocks($payload['spa_images'] ?? []);

        $pages = [];
        foreach ($tableBlocks as $block) {
            $pages[] = $this->renderTablePage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }
        foreach ($textBlocks as $block) {
            $pages[] = $this->renderTextPage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }
        foreach ($imageBlocks as $block) {
            $pages[] = $this->renderImagePage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }

        if (empty($pages)) {
            $pages[] = $this->renderTextPage([
                'title' => 'SALE AND PURCHASE AGREEMENT',
                'body' => 'No SPA content blocks were provided.',
            ], $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }

        return '<!doctype html><html><head><meta charset="utf-8" /><style>' .
            $this->styles() .
            '</style></head><body>' . implode('', $pages) . '</body></html>';
    }

    private function normalizeTableBlocks($raw): array
    {
        $blocks = $this->decodeIfJson($raw);
        if (!is_array($blocks)) {
            return [];
        }
        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $title = sanitize_text_field((string) ($block['title'] ?? ''));
            $rowsRaw = $block['rows'] ?? [];
            if (!is_array($rowsRaw)) {
                continue;
            }
            $rows = [];
            foreach ($rowsRaw as $row) {
                if (is_array($row) && array_keys($row) === range(0, count($row) - 1)) {
                    $left = sanitize_text_field((string) ($row[0] ?? ''));
                    $right = sanitize_text_field((string) ($row[1] ?? ''));
                    $rows[] = [$left, $right];
                    continue;
                }
                if (is_array($row)) {
                    $left = sanitize_text_field((string) ($row['label'] ?? $row['left'] ?? ''));
                    $right = sanitize_text_field((string) ($row['value'] ?? $row['right'] ?? ''));
                    $rows[] = [$left, $right];
                }
            }
            if (!empty($rows)) {
                $normalized[] = [
                    'title' => $title,
                    'rows' => $rows,
                ];
            }
        }
        return $normalized;
    }

    private function normalizeTextBlocks($raw): array
    {
        $blocks = $this->decodeIfJson($raw);
        if (!is_array($blocks)) {
            return [];
        }
        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $title = sanitize_text_field((string) ($block['title'] ?? ''));
            $body = trim((string) ($block['body'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            $normalized[] = [
                'title' => $title,
                'body' => $body,
            ];
        }
        return $normalized;
    }

    private function normalizeImageBlocks($raw): array
    {
        $blocks = $this->decodeIfJson($raw);
        if (!is_array($blocks)) {
            return [];
        }
        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $title = sanitize_text_field((string) ($block['title'] ?? ''));
            $url = $this->images->resolveImageSource((string) ($block['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $normalized[] = [
                'title' => $title,
                'url' => $url,
            ];
        }
        return $normalized;
    }

    private function decodeIfJson($raw)
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $raw;
    }

    private function renderTablePage(array $block, string $logoUrl, string $watermarkUrl, string $sellerInitials, string $buyerInitials): string
    {
        $rowsHtml = '';
        foreach ($block['rows'] as $row) {
            $rowsHtml .= '<tr><td>' . esc_html((string) $row[0]) . '</td><td>' . esc_html((string) $row[1]) . '</td></tr>';
        }

        return $this->pageStart($logoUrl, $watermarkUrl) .
            ($block['title'] !== '' ? '<h2>' . esc_html((string) $block['title']) . '</h2>' : '') .
            '<table class="spa-table"><tbody>' . $rowsHtml . '</tbody></table>' .
            $this->pageFooter($sellerInitials, $buyerInitials) .
            $this->pageEnd();
    }

    private function renderTextPage(array $block, string $logoUrl, string $watermarkUrl, string $sellerInitials, string $buyerInitials): string
    {
        $body = wp_kses_post(nl2br(esc_html((string) $block['body'])));
        return $this->pageStart($logoUrl, $watermarkUrl) .
            ($block['title'] !== '' ? '<h2>' . esc_html((string) $block['title']) . '</h2>' : '') .
            '<div class="spa-text-wall">' . $body . '</div>' .
            $this->pageFooter($sellerInitials, $buyerInitials) .
            $this->pageEnd();
    }

    private function renderImagePage(array $block, string $logoUrl, string $watermarkUrl, string $sellerInitials, string $buyerInitials): string
    {
        return $this->pageStart($logoUrl, $watermarkUrl) .
            ($block['title'] !== '' ? '<h2>' . esc_html((string) $block['title']) . '</h2>' : '') .
            '<div class="spa-image-wrap"><img src="' . esc_attr((string) $block['url']) . '" alt="" /></div>' .
            $this->pageFooter($sellerInitials, $buyerInitials) .
            $this->pageEnd();
    }

    private function pageStart(string $logoUrl, string $watermarkUrl): string
    {
        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoHtml = '<img class="spa-logo" src="' . esc_attr($logoUrl) . '" alt="" />';
        }
        $watermarkHtml = $watermarkUrl !== '' ? '<img class="spa-watermark" src="' . esc_attr($watermarkUrl) . '" alt="" />' : '';

        return '<section class="spa-page"><div class="spa-page-inner">' .
            $watermarkHtml .
            '<header class="spa-header">' . $logoHtml . '</header>';
    }

    private function pageFooter(string $sellerInitials, string $buyerInitials): string
    {
        return '<footer class="spa-footer"><span>' . esc_html($sellerInitials) . '</span><span>' . esc_html($buyerInitials) . '</span></footer>';
    }

    private function pageEnd(): string
    {
        return '</div></section>';
    }

    private function styles(): string
    {
        return '
@page { margin: 8mm; }
html, body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
.spa-page { position: relative; page-break-after: always; background: #fff; }
.spa-page:last-child { page-break-after: auto; }
.spa-page-inner {
  position: relative;
  box-sizing: border-box;
  padding: 12mm 10mm 10mm 24mm;
  background: linear-gradient(to right, #f28b3c 0, #f28b3c 7mm, #ffffff 7mm, #ffffff 7.8mm, #123a75 7.8mm, #123a75 8.4mm, #ffffff 8.4mm, #ffffff 100%);
}
.spa-header { margin: 0 0 8mm 0; position: relative; z-index: 2; }
.spa-logo { width: 48mm; height: auto; display: block; }
.spa-watermark {
  position: absolute;
  left: 50%;
  top: 52%;
  transform: translate(-50%, -50%);
  width: 120mm;
  opacity: 0.12;
  z-index: 0;
}
h2 {
  margin: 0 0 6mm 0;
  font-size: 11pt;
  font-weight: 700;
  text-transform: uppercase;
  position: relative;
  z-index: 2;
}
.spa-text-wall { line-height: 1.45; font-size: 10.7pt; white-space: normal; position: relative; z-index: 2; }
.spa-table {
  width: 100%;
  border-collapse: collapse;
  position: relative;
  z-index: 2;
}
.spa-table td {
  border: 1px solid #9f9f9f;
  padding: 3.5mm 4mm;
  vertical-align: top;
  font-size: 10.8pt;
}
.spa-table td:first-child { width: 30%; }
.spa-image-wrap { position: relative; z-index: 2; }
.spa-image-wrap img {
  width: 100%;
  max-height: 200mm;
  object-fit: contain;
  display: block;
}
.spa-footer {
  margin-top: 10mm;
  display: flex;
  justify-content: space-between;
  font-size: 10pt;
  z-index: 2;
}
';
    }
}
