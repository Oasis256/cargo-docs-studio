<?php

namespace CargoDocsStudio\Domain\Render\Composer\Shared;

class NumberFormatter
{
    public function formatFieldValue(mixed $value, string $type): string
    {
        if ($type === 'textarea') {
            return nl2br(esc_html((string) $value));
        }
        if ($type === 'number' || $type === 'currency') {
            return esc_html($this->formatSmart((float) $value, 2));
        }

        return esc_html((string) $value);
    }

    public function formatSmart(float|int $value, int $decimals = 2): string
    {
        $decimals = max(0, min(6, $decimals));
        $formatted = number_format((float) $value, $decimals, '.', ',');
        if ($decimals === 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
