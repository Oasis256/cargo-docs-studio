<?php

namespace CargoDocsStudio\Domain\Render\Composer\Shared;

class NumberFormatter
{
    private const SMALL = [
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
    ];

    private const TENS = [
        2 => 'twenty',
        3 => 'thirty',
        4 => 'forty',
        5 => 'fifty',
        6 => 'sixty',
        7 => 'seventy',
        8 => 'eighty',
        9 => 'ninety',
    ];

    private const SCALES = [
        1000000000000 => 'trillion',
        1000000000 => 'billion',
        1000000 => 'million',
        1000 => 'thousand',
    ];

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

    public function moneyToWords(float|int $amount, string $currency = 'USD'): string
    {
        $value = (float) $amount;
        $negative = $value < 0;
        $value = abs($value);

        $whole = (int) floor($value);
        $fraction = (int) round(($value - $whole) * 100);
        if ($fraction === 100) {
            $whole++;
            $fraction = 0;
        }

        $major = $this->currencyMajorUnit($currency);
        $minor = $this->currencyMinorUnit($currency);

        $wholeWords = $this->capitalizeWordTokens($this->numberToWords($whole));
        $words = ($negative ? 'minus ' : '') . $wholeWords . ' ' . ($whole === 1 ? rtrim($major, 's') : $major);
        if ($fraction > 0) {
            $fractionWords = $this->capitalizeWordTokens($this->numberToWords($fraction));
            $words .= ' and ' . $fractionWords . ' ' . ($fraction === 1 ? rtrim($minor, 's') : $minor);
        }

        return $words;
    }

    public function numberToWords(int $number): string
    {
        if ($number < 0) {
            return 'minus ' . $this->numberToWords(abs($number));
        }
        if ($number < 20) {
            return self::SMALL[$number];
        }
        if ($number < 100) {
            $ten = intdiv($number, 10);
            $remainder = $number % 10;
            $base = self::TENS[$ten] ?? '';
            return $remainder > 0 ? $base . '-' . self::SMALL[$remainder] : $base;
        }
        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;
            $base = self::SMALL[$hundreds] . ' hundred';
            return $remainder > 0 ? $base . ' ' . $this->numberToWords($remainder) : $base;
        }

        foreach (self::SCALES as $scale => $label) {
            if ($number >= $scale) {
                $major = intdiv($number, $scale);
                $remainder = $number % $scale;
                $base = $this->numberToWords($major) . ' ' . $label;
                return $remainder > 0 ? $base . ' ' . $this->numberToWords($remainder) : $base;
            }
        }

        return (string) $number;
    }

    private function currencyMajorUnit(string $currency): string
    {
        return match (strtoupper(trim($currency))) {
            'EUR' => 'euros',
            'GBP' => 'pounds',
            'UGX' => 'shillings',
            default => 'dollars',
        };
    }

    private function currencyMinorUnit(string $currency): string
    {
        return match (strtoupper(trim($currency))) {
            'EUR' => 'cents',
            'GBP' => 'pence',
            'UGX' => 'cents',
            default => 'cents',
        };
    }

    private function capitalizeWordTokens(string $text): string
    {
        return (string) preg_replace_callback(
            '/\b[a-z]/',
            static fn (array $matches): string => strtoupper($matches[0]),
            $text
        );
    }
}
