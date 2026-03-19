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
        $logoUrl = $this->sanitizeRenderableImage($this->images->resolveImageSource($logoCandidate));
        $watermarkEnabled = !empty($payload['watermark_enabled']);
        $watermarkUrl = '';
        if ($watermarkEnabled) {
            $watermarkUrl = $this->sanitizeRenderableImage(
                $this->images->resolveImageSource((string) ($payload['watermark_url'] ?? ''))
            );
        }
        $sellerInitials = sanitize_text_field((string) ($payload['seller_initials'] ?? "Seller's Initials"));
        $buyerInitials = sanitize_text_field((string) ($payload['buyer_initials'] ?? "Buyer's Initials"));

        $tableBlocks = $this->normalizeTableBlocks($payload['spa_tables'] ?? []);
        $textBlocks = $this->normalizeTextBlocks($payload['spa_text_walls'] ?? []);
        $imageBlocks = $this->normalizeImageBlocks($payload['spa_images'] ?? []);

        $pages = $this->buildPages($payload, $tableBlocks, $textBlocks, $imageBlocks, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);

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

        return '<section class="spa-page">' .
            '<div class="spa-rail-orange"></div><div class="spa-rail-blue"></div>' .
            '<div class="spa-page-inner">' .
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
@page { margin: 0; }
html, body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
.spa-page { position: relative; min-height: 297mm; page-break-inside: avoid; background: #fff; }
.spa-page + .spa-page { page-break-before: always; }
.spa-rail-orange {
  position: absolute;
  top: 0;
  left: 8mm;
  width: 7mm;
  height: 100%;
  background: #f28b3c;
  z-index: 0;
}
.spa-rail-blue {
  position: absolute;
  top: 0;
  left: 16.2mm;
  width: 0.8mm;
  height: 26mm;
  background: #123a75;
  z-index: 0;
}
.spa-page-inner {
  position: relative;
  box-sizing: border-box;
  min-height: 297mm;
  padding: 12mm 12mm 12mm 24mm;
  display: flex;
  flex-direction: column;
  z-index: 1;
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
  max-height: 210mm;
  object-fit: contain;
  display: block;
}
.spa-footer {
  margin-top: auto;
  padding-top: 8mm;
  display: flex;
  justify-content: space-between;
  font-size: 10pt;
  position: relative;
  z-index: 2;
}
';
    }

    private function buildPages(
        array $payload,
        array $tableBlocks,
        array $textBlocks,
        array $imageBlocks,
        string $logoUrl,
        string $watermarkUrl,
        string $sellerInitials,
        string $buyerInitials
    ): array {
        $pages = [];
        $orderRaw = $payload['spa_block_order'] ?? [];
        $order = $this->decodeIfJson($orderRaw);

        if (is_array($order) && !empty($order)) {
            foreach ($order as $entry) {
                $token = sanitize_text_field((string) $entry);
                if ($token === '') {
                    continue;
                }
                $parts = explode(':', strtolower($token), 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $type = $parts[0];
                $index = (int) $parts[1];
                if ($index < 0) {
                    continue;
                }
                if ($type === 'table' && isset($tableBlocks[$index])) {
                    $pages[] = $this->renderTablePage($tableBlocks[$index], $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
                    continue;
                }
                if ($type === 'text' && isset($textBlocks[$index])) {
                    $pages[] = $this->renderTextPage($textBlocks[$index], $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
                    continue;
                }
                if ($type === 'image' && isset($imageBlocks[$index])) {
                    $pages[] = $this->renderImagePage($imageBlocks[$index], $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
                }
            }
            if (!empty($pages)) {
                return $pages;
            }
        }

        foreach ($tableBlocks as $block) {
            $pages[] = $this->renderTablePage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }
        foreach ($textBlocks as $block) {
            $pages[] = $this->renderTextPage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }
        foreach ($imageBlocks as $block) {
            $pages[] = $this->renderImagePage($block, $logoUrl, $watermarkUrl, $sellerInitials, $buyerInitials);
        }

        return $pages;
    }

    private function sanitizeRenderableImage(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        if (str_starts_with($src, 'data:image/')) {
            return $src;
        }
        if (file_exists($src)) {
            return $src;
        }
        return '';
    }
}
