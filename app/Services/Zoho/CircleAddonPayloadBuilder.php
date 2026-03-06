<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;

class CircleAddonPayloadBuilder
{
    public function buildBase(Circle $circle, CircleBillingTerm $term, string $addonCode, float $amount): array
    {
        return [
            'name' => trim(($circle->name ?: 'Circle') . ' - ' . $term->label()),
            'description' => trim(($circle->description ?: $circle->purpose ?: 'Circle paid subscription') . ' (' . $term->label() . ')'),
            'addon_code' => $addonCode,
            'product_id' => (string) env('ZOHO_CIRCLE_ADDON_PRODUCT_ID', ''),
            'currency_code' => 'INR',
            'type' => 'recurring',
            'pricing_scheme' => 'unit',
            'unit_name' => (string) config('zoho_billing.circle_addon_unit_name', 'Member'),
            'interval_unit' => $this->defaultIntervalUnit($term),
            'price_brackets' => [[
                'start_quantity' => 1,
                'end_quantity' => 0,
                'price' => round($amount, 2),
            ]],
        ];
    }

    public function buildPayloadStrategies(
        Circle $circle,
        CircleBillingTerm $term,
        string $addonCode,
        float $amount,
        string $intervalUnit,
        ?array $templateAddon = null,
    ): array {
        $base = array_merge(
            $this->buildBase($circle, $term, $addonCode, $amount),
            ['interval_unit' => $intervalUnit]
        );

        $strategies = [
            'unit_brackets_mapped_interval' => $base,
        ];

        if ($templateAddon) {
            $strategies['template_aligned_unit'] = $this->buildFromTemplate($base, $templateAddon, $amount, $intervalUnit);
        }

        return $strategies;
    }

    public function syncHash(Circle $circle, CircleBillingTerm $term, float $amount, string $name, string $description, bool $active): string
    {
        return hash('sha256', implode('|', [
            (string) env('ZOHO_CIRCLE_ADDON_PRODUCT_ID', ''),
            (string) $circle->id,
            $term->value,
            (string) round($amount, 2),
            $name,
            $description,
            $active ? '1' : '0',
        ]));
    }

    private function buildFromTemplate(array $base, array $templateAddon, float $amount, string $intervalUnit): array
    {
        $payload = $base;

        foreach (['type', 'pricing_scheme', 'unit', 'product_type'] as $key) {
            if (array_key_exists($key, $templateAddon) && $templateAddon[$key] !== null && $templateAddon[$key] !== '') {
                $payload[$key] = $templateAddon[$key];
            }
        }

        if (! empty($templateAddon['unit_name'])) {
            $payload['unit_name'] = (string) $templateAddon['unit_name'];
        }

        $payload['interval_unit'] = $intervalUnit;

        $brackets = $templateAddon['price_brackets'] ?? null;
        if (is_array($brackets) && $brackets !== []) {
            $first = is_array($brackets[0] ?? null) ? $brackets[0] : [];
            $priceKey = array_key_exists('recurring_price', $first) ? 'recurring_price' : 'price';

            $payload['price_brackets'] = [[
                'start_quantity' => (int) ($first['start_quantity'] ?? 1),
                'end_quantity' => (int) ($first['end_quantity'] ?? 0),
                $priceKey => round($amount, 2),
            ]];
        }

        unset($payload['interval']);

        return $payload;
    }

    private function defaultIntervalUnit(CircleBillingTerm $term): string
    {
        return match ($term) {
            CircleBillingTerm::MONTHLY => 'monthly',
            CircleBillingTerm::QUARTERLY => 'quarterly',
            CircleBillingTerm::HALF_YEARLY => 'half_yearly',
            CircleBillingTerm::YEARLY => 'yearly',
        };
    }
}
