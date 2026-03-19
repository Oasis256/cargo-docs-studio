<?php

namespace CargoDocsStudio\Domain\Render\Composer;

use CargoDocsStudio\Domain\Render\Composer\Shared\FinancialCalculator;

class RenderContextFactory
{
    private FinancialCalculator $calculator;

    public function __construct(?FinancialCalculator $calculator = null)
    {
        $this->calculator = $calculator ?: new FinancialCalculator();
    }

    public function build(array $payload, array $templateConfig): array
    {
        $docTypeKey = sanitize_key((string) ($templateConfig['doc_type_key'] ?? ($payload['doc_type_key'] ?? 'invoice')));

        return [
            'doc_type_key' => $docTypeKey,
            'theme' => $this->normalizeTheme(is_array($templateConfig['theme'] ?? null) ? $templateConfig['theme'] : []),
            'layout' => $this->normalizeLayout(is_array($templateConfig['layout'] ?? null) ? $templateConfig['layout'] : [], $docTypeKey),
            'schema' => $this->normalizeSchema(is_array($templateConfig['schema'] ?? null) ? $templateConfig['schema'] : []),
            'computed' => $this->calculator->computeFinancials($payload),
        ];
    }

    public function normalizeTheme(array $theme): array
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

    public function normalizeLayout(array $layout, string $docTypeKey = 'invoice'): array
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

    public function normalizeSchema(array $schema): array
    {
        return [
            'fields' => is_array($schema['fields'] ?? null) ? $schema['fields'] : [],
            'groups' => is_array($schema['groups'] ?? null) ? $schema['groups'] : [],
        ];
    }

    public function defaultSectionsForDocType(string $docTypeKey): array
    {
        return match ($docTypeKey) {
            'receipt' => ['header', 'summary', 'line_items', 'footer'],
            'skr' => ['header', 'summary', 'tracking_qr', 'footer'],
            'spa' => ['header', 'summary', 'footer'],
            default => ['header', 'summary', 'line_items', 'tracking_qr', 'payment_qr', 'footer'],
        };
    }

    public function defaultTitleForDocType(string $docTypeKey): string
    {
        return match ($docTypeKey) {
            'receipt' => 'Payment Receipt',
            'skr' => 'Safe Keeping Receipt',
            'spa' => 'Sale and Purchase Agreement',
            default => 'Cargo Invoice',
        };
    }
}
