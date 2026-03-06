<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;

class CircleAddonPayloadBuilder
{
    public function build(Circle $circle, CircleBillingTerm $term, string $addonCode, float $amount): array
    {
        return [
            'name' => trim(($circle->name ?? 'Circle') . ' - ' . $term->label()),
            'description' => trim(($circle->description ?? $circle->purpose ?? 'Circle subscription') . ' (' . $term->label() . ')'),
            'addon_code' => $addonCode,
            'product_id' => (string) env('ZOHO_CIRCLE_ADDON_PRODUCT_ID', ''),
            'price' => round($amount, 2),
            'currency_code' => 'INR',
        ];
    }

    public function syncHash(Circle $circle, CircleBillingTerm $term, array $payload, bool $active): string
    {
        return hash('sha256', implode('|', [
            (string) ($payload['product_id'] ?? ''),
            (string) $circle->id,
            $term->value,
            (string) ($payload['price'] ?? ''),
            (string) ($payload['name'] ?? ''),
            (string) ($payload['description'] ?? ''),
            $active ? '1' : '0',
        ]));
    }
}
