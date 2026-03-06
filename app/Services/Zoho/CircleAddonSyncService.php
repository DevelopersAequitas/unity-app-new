<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use App\Models\CircleZohoAddon;
use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CircleAddonSyncService
{
    private bool $loggedRemoteAddonShape = false;

    /** @var array<int, array>|null */
    private ?array $addonsCache = null;

    public function __construct(
        private readonly CircleAddonCodeGenerator $codeGenerator,
        private readonly CircleAddonPayloadBuilder $payloadBuilder,
        private readonly ZohoBillingClient $client,
    ) {
    }

    public function syncCircle(Circle $circle): array
    {
        Log::info('[CircleAddonSync] start', [
            'circle_id' => $circle->id,
            'circle_name' => $circle->name,
            'payment_enabled' => $this->isPaymentEnabled($circle),
        ]);

        if (! $this->canSync()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 4, 'errors' => 0];
        }

        if (! $this->isPaymentEnabled($circle)) {
            $this->markCircleAddonsInactive($circle);
            return ['created' => 0, 'updated' => 0, 'skipped' => 4, 'errors' => 0];
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $templateAddon = $this->resolveTemplateAddon();
        $intervalUnits = $this->resolveIntervalUnitMap();

        foreach (CircleBillingTerm::cases() as $term) {
            $res = $this->syncSingleTerm($circle, $term, $templateAddon, $intervalUnits[$term->value] ?? $this->defaultIntervalUnit($term));
            $counts['created'] += $res['created'];
            $counts['updated'] += $res['updated'];
            $counts['skipped'] += $res['skipped'];
            $counts['errors'] += $res['errors'];
        }

        Log::info('[CircleAddonSync] completed', array_merge(['circle_id' => $circle->id], $counts));

        return $counts;
    }

    public function syncCircleTerm(Circle $circle, CircleBillingTerm $term): array
    {
        if (! $this->canSync()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0];
        }

        if (! $this->isPaymentEnabled($circle)) {
            $this->markTermInactive($circle, $term);

            return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0];
        }

        $intervalUnits = $this->resolveIntervalUnitMap();

        return $this->syncSingleTerm($circle, $term, $this->resolveTemplateAddon(), $intervalUnits[$term->value] ?? $this->defaultIntervalUnit($term));
    }

    public function resolveAvailablePlans(Circle $circle): array
    {
        return collect(CircleBillingTerm::cases())
            ->map(function (CircleBillingTerm $term) use ($circle): array {
                $amount = $this->resolveAmount($circle, $term);

                return [
                    'billing_term' => $term->value,
                    'label' => $term->label(),
                    'months' => $term->months(),
                    'amount' => $amount,
                    'available' => $this->isPaymentEnabled($circle) && $amount > 0,
                    'addon_code' => $this->codeGenerator->generate($circle, $term),
                ];
            })
            ->values()
            ->all();
    }

    public function isPaymentEnabled(Circle $circle): bool
    {
        foreach (['circle_payment_enabled', 'payment_enabled', 'is_payment_enabled', 'is_paid', 'paid_enabled'] as $column) {
            if (Schema::hasColumn('circles', $column)) {
                return (bool) data_get($circle, $column, false);
            }
        }

        return false;
    }

    public function resolveAmount(Circle $circle, CircleBillingTerm $term): float
    {
        $candidates = match ($term) {
            CircleBillingTerm::MONTHLY => ['monthly_price', 'monthly_amount', 'price_monthly', 'amount_monthly'],
            CircleBillingTerm::QUARTERLY => ['quarterly_price', 'quarterly_amount', 'price_quarterly', 'amount_quarterly'],
            CircleBillingTerm::HALF_YEARLY => ['half_yearly_price', 'half_yearly_amount', 'price_half_yearly', 'six_month_amount'],
            CircleBillingTerm::YEARLY => ['yearly_price', 'yearly_amount', 'price_yearly', 'annual_amount'],
        };

        foreach ($candidates as $column) {
            if (Schema::hasColumn('circles', $column)) {
                return round((float) data_get($circle, $column, 0), 2);
            }
        }

        return 0;
    }

    private function syncSingleTerm(Circle $circle, CircleBillingTerm $term, ?array $templateAddon, string $intervalUnit): array
    {
        $amount = $this->resolveAmount($circle, $term);
        $code = $this->codeGenerator->generate($circle, $term);

        Log::info('[CircleAddonSync] syncing term', [
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
            'amount' => $amount,
            'addon_code' => $code,
            'interval_unit' => $intervalUnit,
        ]);

        if ($amount <= 0) {
            $this->markTermInactive($circle, $term);

            return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0];
        }

        $base = $this->payloadBuilder->buildBase($circle, $term, $code, $amount);
        $syncHash = $this->payloadBuilder->syncHash(
            $circle,
            $term,
            $amount,
            (string) ($base['name'] ?? ''),
            (string) ($base['description'] ?? ''),
            true,
        );

        $local = CircleZohoAddon::query()->firstOrNew([
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
        ]);

        $existingCode = (string) ($local->zoho_addon_code ?? $local->addon_code ?? '');
        if ($local->exists && (string) ($local->sync_hash ?? '') === $syncHash && $existingCode === $code && (bool) ($local->is_active ?? true)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 0];
        }

        try {
            $remoteAddon = $this->resolveRemoteAddon($local, $code);
            $strategies = $this->payloadBuilder->buildPayloadStrategies($circle, $term, $code, $amount, $intervalUnit, $templateAddon);
            $action = 'created';

            if ($remoteAddon && (string) ($remoteAddon['addon_id'] ?? '') !== '') {
                $action = 'updated';
                $remoteAddon = $this->attemptRemoteUpsert('update', (string) $remoteAddon['addon_id'], $strategies, $circle, $term, $code);
            } else {
                $remoteAddon = $this->attemptRemoteUpsert('create', null, $strategies, $circle, $term, $code);
            }

            $this->saveLocalAddon($local, $circle, $term, $amount, $base, $syncHash, $code, $remoteAddon, true);

            return [
                'created' => $action === 'created' ? 1 : 0,
                'updated' => $action === 'updated' ? 1 : 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        } catch (\Throwable $throwable) {
            Log::error('[CircleAddonSync] failed term sync', [
                'circle_id' => $circle->id,
                'circle_name' => $circle->name,
                'billing_term' => $term->value,
                'amount' => $amount,
                'addon_code' => $code,
                'error' => $throwable->getMessage(),
            ]);

            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1];
        }
    }

    private function canSync(): bool
    {
        if (! Schema::hasTable('circle_zoho_addons')) {
            Log::warning('[CircleAddonSync] skipped: circle_zoho_addons table missing');
            return false;
        }

        return true;
    }

    private function resolveRemoteAddon(CircleZohoAddon $local, string $code): ?array
    {
        $localAddonId = (string) ($local->zoho_addon_id ?? '');

        if ($localAddonId !== '') {
            try {
                $response = $this->client->request('GET', '/addons/' . $localAddonId);
                $addon = $response['addon'] ?? null;
                if (is_array($addon)) {
                    return $addon;
                }
            } catch (\Throwable $throwable) {
                Log::warning('[CircleAddonSync] local zoho_addon_id missing remotely', [
                    'zoho_addon_id' => $localAddonId,
                    'addon_code' => $code,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $this->findRemoteAddonByCode($code);
    }

    private function attemptRemoteUpsert(string $mode, ?string $remoteId, array $strategies, Circle $circle, CircleBillingTerm $term, string $code): array
    {
        $errors = [];

        foreach ($strategies as $strategy => $payload) {
            $this->logPayload('[CircleAddonSync] payload strategy attempt', $circle, $term, $code, $strategy, $payload, $mode, $remoteId);

            try {
                $response = $mode === 'update' && $remoteId
                    ? $this->client->request('PUT', '/addons/' . $remoteId, $payload)
                    : $this->client->request('POST', '/addons', $payload);

                $addon = $response['addon'] ?? null;

                if (is_array($addon) && (string) ($addon['addon_id'] ?? '') !== '') {
                    Log::info('[CircleAddonSync] payload strategy success', [
                        'circle_id' => $circle->id,
                        'billing_term' => $term->value,
                        'addon_code' => $code,
                        'strategy' => $strategy,
                        'zoho_addon_id' => (string) ($addon['addon_id'] ?? ''),
                        'mode' => $mode,
                    ]);

                    return $addon;
                }

                $errors[] = $strategy . ': empty addon response';
            } catch (\Throwable $throwable) {
                $errors[] = $strategy . ': ' . $throwable->getMessage();

                Log::warning('[CircleAddonSync] payload strategy failed', [
                    'circle_id' => $circle->id,
                    'billing_term' => $term->value,
                    'addon_code' => $code,
                    'strategy' => $strategy,
                    'mode' => $mode,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('All addon payload strategies failed (' . $mode . '): ' . implode(' | ', $errors));
    }

    private function fetchAddons(): array
    {
        if ($this->addonsCache !== null) {
            return $this->addonsCache;
        }

        $response = $this->client->request('GET', '/addons', ['page' => 1, 'per_page' => 200], true);
        $addons = is_array($response['addons'] ?? null) ? $response['addons'] : [];
        $this->addonsCache = $addons;

        if (! $this->loggedRemoteAddonShape && $addons !== []) {
            Log::info('[CircleAddonSync] remote addon shape sample', $this->sanitizeAddonShape($addons[0]));
            $this->loggedRemoteAddonShape = true;
        }

        return $addons;
    }

    private function resolveTemplateAddon(): ?array
    {
        $addons = $this->fetchAddons();
        $productId = (string) env('ZOHO_CIRCLE_ADDON_PRODUCT_ID', '');

        foreach (['02', '03', '04', '15'] as $legacyCode) {
            foreach ($addons as $addon) {
                if ((string) ($addon['addon_code'] ?? '') === $legacyCode) {
                    return $addon;
                }
            }
        }

        if ($productId !== '') {
            foreach ($addons as $addon) {
                if ((string) ($addon['product_id'] ?? '') === $productId) {
                    return $addon;
                }
            }
        }

        return $addons[0] ?? null;
    }

    private function findRemoteAddonByCode(string $code): ?array
    {
        foreach ($this->fetchAddons() as $addon) {
            if ((string) ($addon['addon_code'] ?? '') === $code) {
                return $addon;
            }
        }

        return null;
    }


    private function resolveIntervalUnitMap(): array
    {
        $map = [
            'monthly' => (string) config('zoho_billing.circle_addon_interval_units.monthly', env('ZOHO_CIRCLE_INTERVAL_MONTHLY', 'monthly')),
            'quarterly' => (string) config('zoho_billing.circle_addon_interval_units.quarterly', env('ZOHO_CIRCLE_INTERVAL_QUARTERLY', 'quarterly')),
            'half_yearly' => (string) config('zoho_billing.circle_addon_interval_units.half_yearly', env('ZOHO_CIRCLE_INTERVAL_HALF_YEARLY', 'half_yearly')),
            'yearly' => (string) config('zoho_billing.circle_addon_interval_units.yearly', env('ZOHO_CIRCLE_INTERVAL_YEARLY', 'yearly')),
        ];

        $addons = $this->fetchAddons();

        $legacyCodeToTerm = [
            '02' => 'monthly',
            '03' => 'quarterly',
            '04' => 'half_yearly',
            '15' => 'yearly',
        ];

        foreach ($addons as $addon) {
            $code = (string) ($addon['addon_code'] ?? '');
            $intervalUnit = trim((string) ($addon['interval_unit'] ?? ''));

            if ($intervalUnit === '') {
                continue;
            }

            if (isset($legacyCodeToTerm[$code])) {
                $map[$legacyCodeToTerm[$code]] = $intervalUnit;
            }
        }

        Log::info('[CircleAddonSync] resolved interval unit map', $map);

        return $map;
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

    private function saveLocalAddon(
        CircleZohoAddon $model,
        Circle $circle,
        CircleBillingTerm $term,
        float $amount,
        array $basePayload,
        string $syncHash,
        string $code,
        array $remoteAddon,
        bool $isActive,
    ): void {
        $columns = Schema::getColumnListing($model->getTable());

        $fieldMap = [
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
            'amount' => $amount,
            'price' => $amount,
            'currency' => 'INR',
            'currency_code' => 'INR',
            'zoho_product_id' => (string) env('ZOHO_CIRCLE_ADDON_PRODUCT_ID', ''),
            'zoho_addon_id' => (string) ($remoteAddon['addon_id'] ?? $model->zoho_addon_id ?? ''),
            'zoho_addon_code' => $code,
            'addon_code' => $code,
            'zoho_addon_name' => (string) ($remoteAddon['name'] ?? ($basePayload['name'] ?? '')),
            'name' => (string) ($remoteAddon['name'] ?? ($basePayload['name'] ?? '')),
            'description' => (string) ($basePayload['description'] ?? ''),
            'is_active' => $isActive,
            'sync_hash' => $syncHash,
            'last_synced_at' => now(),
            'raw_payload' => [
                'request_base' => $basePayload,
                'response' => $remoteAddon,
            ],
            'metadata' => [
                'request_base' => $basePayload,
                'response' => $remoteAddon,
            ],
        ];

        $model->forceFill(Arr::only($fieldMap, $columns))->save();
    }

    private function markCircleAddonsInactive(Circle $circle): void
    {
        if (! Schema::hasColumn('circle_zoho_addons', 'is_active')) {
            return;
        }

        CircleZohoAddon::query()->where('circle_id', $circle->id)->update(['is_active' => false]);
    }

    private function markTermInactive(Circle $circle, CircleBillingTerm $term): void
    {
        if (! Schema::hasColumn('circle_zoho_addons', 'is_active')) {
            return;
        }

        CircleZohoAddon::query()
            ->where('circle_id', $circle->id)
            ->where('billing_term', $term->value)
            ->update(['is_active' => false]);
    }

    private function sanitizeAddonShape(array $addon): array
    {
        return [
            'addon_id' => $addon['addon_id'] ?? null,
            'addon_code' => $addon['addon_code'] ?? null,
            'product_id' => $addon['product_id'] ?? null,
            'type' => $addon['type'] ?? null,
            'pricing_scheme' => $addon['pricing_scheme'] ?? null,
            'price' => $addon['price'] ?? null,
            'recurring_price' => $addon['recurring_price'] ?? null,
            'interval' => $addon['interval'] ?? null,
            'interval_unit' => $addon['interval_unit'] ?? null,
            'currency_code' => $addon['currency_code'] ?? null,
            'price_brackets_keys' => is_array($addon['price_brackets'] ?? null) && isset($addon['price_brackets'][0]) && is_array($addon['price_brackets'][0])
                ? array_keys($addon['price_brackets'][0])
                : [],
        ];
    }

    private function logPayload(string $message, Circle $circle, CircleBillingTerm $term, string $code, string $strategy, array $payload, string $mode, ?string $remoteId = null): void
    {
        Log::info($message, [
            'circle_id' => $circle->id,
            'circle_name' => $circle->name,
            'billing_term' => $term->value,
            'addon_code' => $code,
            'mode' => $mode,
            'remote_addon_id' => $remoteId,
            'strategy' => $strategy,
            'payload' => Arr::only($payload, [
                'name',
                'description',
                'addon_code',
                'product_id',
                'type',
                'pricing_scheme',
                'currency_code',
                'price',
                'recurring_price',
                'interval',
                'interval_unit',
                'price_brackets',
            ]),
        ]);
    }
}
