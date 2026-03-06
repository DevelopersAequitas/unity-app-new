<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\ZohoCircleAddon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoCircleAddonService
{
    private const INTERVALS = [
        'monthly' => ['field' => 'price_monthly', 'months' => 1, 'suffix' => 'm', 'label' => 'Monthly'],
        'quarterly' => ['field' => 'price_quarterly', 'months' => 3, 'suffix' => 'q', 'label' => 'Quarterly'],
        'half_yearly' => ['field' => 'price_half_yearly', 'months' => 6, 'suffix' => 'h', 'label' => 'Half Yearly'],
        'yearly' => ['field' => 'price_yearly', 'months' => 12, 'suffix' => 'y', 'label' => 'Yearly'],
    ];

    public function __construct(private readonly ZohoTokenService $tokenService)
    {
    }

    public function syncCircleAddons(Circle $circle): void
    {
        Log::info('circle addon sync started', ['circle_id' => $circle->id]);

        foreach (self::INTERVALS as $intervalType => $meta) {
            $price = $circle->{$meta['field']} ?? null;

            if ($price === null || $price === '') {
                Log::info('circle addon skipped because no price', [
                    'circle_id' => $circle->id,
                    'interval_type' => $intervalType,
                ]);

                continue;
            }

            try {
                $this->syncInterval($circle, $intervalType, $meta, (float) $price);
            } catch (\Throwable $exception) {
                Log::error('zoho api error', [
                    'context' => 'circle_addon_sync',
                    'circle_id' => $circle->id,
                    'interval_type' => $intervalType,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function syncInterval(Circle $circle, string $intervalType, array $meta, float $price): void
    {
        $addonCode = $this->buildAddonCode((string) $circle->slug, (string) $circle->name, $meta['suffix']);

        $localAddon = ZohoCircleAddon::query()
            ->firstOrNew([
                'circle_id' => $circle->id,
                'interval_type' => $intervalType,
            ]);

        if ($localAddon->exists) {
            try {
                $response = $this->updateAddon($addonCode, $price, (int) $meta['months']);
                $this->persistAddon($localAddon, $circle, $intervalType, $addonCode, $price, $response);

                Log::info('circle addon updated', [
                    'circle_id' => $circle->id,
                    'interval_type' => $intervalType,
                    'addon_code' => $addonCode,
                    'price' => $price,
                ]);

                return;
            } catch (RequestException $exception) {
                $status = $exception->response?->status();
                $body = $exception->response?->json() ?? [];
                $bodyText = is_array($body) ? strtolower((string) json_encode($body)) : strtolower((string) $body);

                if ($status === 404 || str_contains($bodyText, 'does not exist') || str_contains($bodyText, 'invalid addon code')) {
                    Log::warning('zoho addon missing remotely, recreating', [
                        'circle_id' => $circle->id,
                        'interval_type' => $intervalType,
                        'addon_code' => $addonCode,
                    ]);
                } else {
                    throw $exception;
                }
            }
        }

        $response = $this->createAddon($circle, $addonCode, (string) $meta['label'], $price, (int) $meta['months']);
        $this->persistAddon($localAddon, $circle, $intervalType, $addonCode, $price, $response);

        Log::info('circle addon created', [
            'circle_id' => $circle->id,
            'interval_type' => $intervalType,
            'addon_code' => $addonCode,
            'price' => $price,
        ]);
    }

    private function createAddon(Circle $circle, string $addonCode, string $intervalLabel, float $price, int $months): array
    {
        return $this->request('POST', '/addons', [
            'name' => trim($circle->name . ' - ' . $intervalLabel),
            'code' => $addonCode,
            'product_id' => env('ZOHO_CIRCLE_ADDON_PRODUCT_ID'),
            'type' => 'recurring',
            'pricing_model' => 'per_unit',
            'price' => $price,
            'interval_unit' => 'months',
            'interval' => $months,
        ]);
    }

    private function updateAddon(string $addonCode, float $price, int $months): array
    {
        return $this->request('PUT', '/addons/' . $addonCode, [
            'price' => $price,
            'type' => 'recurring',
            'pricing_model' => 'per_unit',
            'interval_unit' => 'months',
            'interval' => $months,
            'product_id' => env('ZOHO_CIRCLE_ADDON_PRODUCT_ID'),
        ]);
    }

    private function persistAddon(ZohoCircleAddon $addon, Circle $circle, string $intervalType, string $addonCode, float $price, array $response): void
    {
        $addon->fill([
            'circle_id' => $circle->id,
            'interval_type' => $intervalType,
            'price' => $price,
            'zoho_addon_id' => (string) (data_get($response, 'addon.addon_id') ?: data_get($response, 'addon.id') ?: $addon->zoho_addon_id),
            'zoho_addon_code' => (string) (data_get($response, 'addon.addon_code') ?: data_get($response, 'addon.code') ?: $addonCode),
            'product_id' => (string) (data_get($response, 'addon.product_id') ?: env('ZOHO_CIRCLE_ADDON_PRODUCT_ID')),
        ]);

        $addon->save();
    }

    private function request(string $method, string $path, array $payload): array
    {
        $baseUrl = rtrim((string) env('ZOHO_BILLING_BASE_URL'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->withHeaders([
                    'Authorization' => 'Zoho-oauthtoken ' . $this->tokenService->getAccessToken(),
                    'X-com-zoho-subscriptions-organizationid' => (string) env('ZOHO_BILLING_ORG_ID'),
                ])
                ->send(strtoupper($method), $url, ['json' => $payload])
                ->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            Log::error('zoho api error', [
                'context' => 'circle_addon_request',
                'method' => strtoupper($method),
                'url' => $url,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json() ?? $exception->response?->body(),
                'payload' => $payload,
            ]);

            throw $exception;
        }
    }

    private function buildAddonCode(string $slug, string $name, string $suffix): string
    {
        $source = $slug !== '' ? $slug : $name;
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', strtolower($source)) ?? 'circle';
        $normalized = trim((string) preg_replace('/_+/', '_', $normalized), '_');

        if ($normalized === '') {
            $normalized = 'circle';
        }

        return 'circle_' . $normalized . '_' . $suffix;
    }
}
