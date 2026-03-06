<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use App\Models\CircleZohoAddon;
use App\Models\User;
use App\Models\UserCircleSubscription;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CircleCheckoutService
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly CircleAddonSyncService $addonSyncService,
    ) {
    }

    public function createCheckout(User $user, Circle $circle, CircleBillingTerm $term): array
    {
        $plans = collect($this->addonSyncService->resolveAvailablePlans($circle))->keyBy('billing_term');
        $selected = $plans->get($term->value);

        if (! $selected || ! ($selected['available'] ?? false)) {
            throw new RuntimeException('Selected billing term is not available for this circle.');
        }

        $addonCode = (string) $selected['addon_code'];
        $basePlanCode = (string) env('ZOHO_CIRCLE_BASE_PLAN_CODE', '');

        if ($basePlanCode === '') {
            throw new RuntimeException('Base plan code is not configured.');
        }

        $customerId = $this->zohoBillingService->ensureCustomerForUser($user);

        $response = app(\App\Support\Zoho\ZohoBillingClient::class)->request('POST', '/hostedpages/newsubscription', [
            'customer_id' => $customerId,
            'plan' => ['plan_code' => $basePlanCode],
            'addons' => [[
                'addon_code' => $addonCode,
                'quantity' => 1,
            ]],
        ]);

        $hostedPageId = (string) data_get($response, 'hostedpage.hostedpage_id', '');
        $url = (string) data_get($response, 'hostedpage.url', '');

        if ($hostedPageId === '' || $url === '') {
            throw new RuntimeException('Failed to generate circle checkout hosted page.');
        }

        $localAddon = CircleZohoAddon::query()->where('circle_id', $circle->id)->where('billing_term', $term->value)->first();

        $this->storePendingSubscription($user, $circle, $term, $addonCode, $hostedPageId, $localAddon?->zoho_addon_id, (float) $selected['amount']);

        return [
            'hostedpage_id' => $hostedPageId,
            'checkout_url' => $url,
            'summary' => [
                'circle_id' => $circle->id,
                'billing_term' => $term->value,
                'amount' => (float) $selected['amount'],
                'addon_code' => $addonCode,
                'base_plan_code' => $basePlanCode,
            ],
        ];
    }

    private function storePendingSubscription(User $user, Circle $circle, CircleBillingTerm $term, string $addonCode, string $hostedPageId, ?string $zohoAddonId, float $amount): void
    {
        if (! Schema::hasTable('user_circle_subscriptions')) {
            return;
        }

        $record = UserCircleSubscription::query()->firstOrNew([
            'user_id' => $user->id,
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
            'zoho_hostedpage_id' => $hostedPageId,
        ]);

        $columns = Schema::getColumnListing($record->getTable());

        $record->forceFill(Arr::only([
            'id' => $record->id ?: (string) Str::uuid(),
            'user_id' => $user->id,
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
            'amount' => $amount,
            'status' => 'pending',
            'zoho_addon_code' => $addonCode,
            'zoho_addon_id' => $zohoAddonId,
            'zoho_hostedpage_id' => $hostedPageId,
        ], $columns))->save();
    }
}
