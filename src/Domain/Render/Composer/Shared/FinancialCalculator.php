<?php

namespace CargoDocsStudio\Domain\Render\Composer\Shared;

class FinancialCalculator
{
    public function resolveLineItems(array $payload): array
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

    public function computeFinancials(array $payload): array
    {
        $items = $this->resolveLineItems($payload);
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['total'] ?? 0);
        }

        if ($subtotal <= 0 && isset($payload['taxable_value'])) {
            $subtotal = (float) $payload['taxable_value'];
        }

        $quantity = (float) ($payload['quantity'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }
        $taxableValue = (float) ($payload['taxable_value'] ?? 0);
        $declaredSubtotal = $taxableValue * $quantity;

        $taxRate = $this->normalizeRatePercent($payload['tax_rate'] ?? 0.0, 0.0);
        $insuranceRate = $this->normalizeRatePercent($payload['insurance_rate'] ?? 0.0, 0.0);
        $smeltingCost = (float) ($payload['smelting_cost'] ?? 0);
        $certOrigin = (float) ($payload['cert_origin'] ?? 0);
        $certOwnership = (float) ($payload['cert_ownership'] ?? 0);
        $exportPermit = (float) ($payload['export_permit'] ?? 0);
        $freightCost = (float) ($payload['freight_cost'] ?? 0);
        $agentFees = (float) ($payload['agent_fees'] ?? 0);

        $taxAmount = isset($payload['tax_amount']) ? (float) $payload['tax_amount'] : ($declaredSubtotal * ($taxRate / 100));
        $insuranceAmount = isset($payload['insurance_amount']) ? (float) $payload['insurance_amount'] : ($declaredSubtotal * ($insuranceRate / 100));
        $smeltingTotal = $smeltingCost * $quantity;
        $freightTotal = $freightCost;
        $agentTotal = $agentFees;

        // Business rule: invoice grand total excludes declared taxable value itself.
        $derivedGrandTotal = $taxAmount + $insuranceAmount + $smeltingTotal + $certOrigin + $certOwnership + $exportPermit + $freightTotal + $agentTotal;
        $grandTotal = isset($payload['grand_total']) ? (float) $payload['grand_total'] : $derivedGrandTotal;

        return [
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'insurance_amount' => $insuranceAmount,
            'grand_total' => $grandTotal,
        ];
    }

    public function normalizeRatePercent(mixed $raw, float $defaultPercent): float
    {
        if ($raw === null || $raw === '') {
            return $defaultPercent;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return $defaultPercent;
        }

        $hasPercent = str_contains($text, '%');
        $clean = preg_replace('/[^0-9.\-]/', '', $text);
        if ($clean === null || $clean === '' || !is_numeric($clean)) {
            return $defaultPercent;
        }

        $value = (float) $clean;
        if (!$hasPercent && abs($value) > 0 && abs($value) <= 1) {
            $value *= 100;
        }

        return $value;
    }
}
