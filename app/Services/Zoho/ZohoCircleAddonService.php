<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Str;

class ZohoCircleAddonService
{
    private const DURATION_LABELS = [
        1 => 'Monthly',
        3 => 'Quarterly',
        6 => 'Half-Yearly',
        12 => 'Yearly',
    ];

    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function ensureAddonsForCircle(Circle $circle): void
    {
        $prices = $circle->subscriptionPrices()
            ->whereIn('duration_months', array_keys(self::DURATION_LABELS))
            ->get()
            ->keyBy('duration_months');

        foreach (self::DURATION_LABELS as $duration => $label) {
            /** @var CircleSubscriptionPrice|null $price */
            $price = $prices->get($duration);

            if (! $price || (float) $price->price <= 0) {
                continue;
            }

            $addonCode = $this->buildAddonCode($circle, $duration);
            $addonName = sprintf('%s - %s', $circle->name, $label);

            $payload = [
                'name' => $addonName,
                'code' => $addonCode,
                'price' => (float) $price->price,
                'type' => 'recurring',
            ];

            if ($price->zoho_addon_id) {
                $response = $this->zohoBillingService->updateAddon($price->zoho_addon_id, $payload);
                $addon = data_get($response, 'addon', []);
            } else {
                $response = $this->zohoBillingService->createAddon($payload);
                $addon = data_get($response, 'addon', []);
            }

            $price->forceFill([
                'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? $price->zoho_addon_id),
                'zoho_addon_code' => (string) (data_get($addon, 'code') ?? $addonCode),
                'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                'payload' => $response,
            ])->save();
        }
    }

    public static function durationLabel(int $durationMonths): string
    {
        return self::DURATION_LABELS[$durationMonths] ?? ($durationMonths . ' Months');
    }

    private function buildAddonCode(Circle $circle, int $duration): string
    {
        $shortId = Str::lower(substr(str_replace('-', '', (string) $circle->id), 0, 8));

        return sprintf('circle_%s_%sm', $shortId ?: 'circle', $duration);
    }
}
