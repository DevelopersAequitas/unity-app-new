<?php

namespace App\Services\Zoho;

use App\Models\CircleMember;
use App\Models\CircleZohoAddon;
use App\Models\User;
use App\Models\UserCircleSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CircleWebhookService
{
    public function isValid(Request $request): bool
    {
        $expected = (string) env('ZOHO_WEBHOOK_TOKEN', '');
        $incoming = (string) ($request->header('X-Zoho-Webhook-Signature')
            ?? $request->header('X-Webhook-Token')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? '');

        return $expected !== '' && hash_equals($expected, $incoming);
    }

    public function handle(array $payload): void
    {
        $eventType = strtolower((string) (data_get($payload, 'event_type') ?? data_get($payload, 'event') ?? 'unknown'));
        $eventId = (string) (data_get($payload, 'event_id') ?? data_get($payload, 'id') ?? data_get($payload, 'webhook_id') ?? '');

        if ($eventId !== '' && ! Cache::add('zoho_circle_event:' . $eventId, true, now()->addDay())) {
            Log::info('Circle webhook skipped duplicate event', ['event_id' => $eventId]);
            return;
        }

        $subscriptionId = (string) (data_get($payload, 'subscription.subscription_id') ?? data_get($payload, 'data.subscription.subscription_id') ?? '');
        $customerId = (string) (data_get($payload, 'customer.customer_id') ?? data_get($payload, 'data.customer.customer_id') ?? '');
        $addonCode = (string) (data_get($payload, 'addon.addon_code')
            ?? data_get($payload, 'data.addon.addon_code')
            ?? data_get($payload, 'subscription.addons.0.addon_code')
            ?? data_get($payload, 'data.subscription.addons.0.addon_code')
            ?? '');

        $addon = CircleZohoAddon::query()->where('addon_code', $addonCode)->first();

        if (! $addon) {
            Log::warning('Circle webhook addon not found', ['event_type' => $eventType, 'addon_code' => $addonCode]);
            return;
        }

        $user = User::query()
            ->when($customerId !== '', fn ($q) => $q->where('zoho_customer_id', $customerId))
            ->when($customerId === '', fn ($q) => $q->where('email', data_get($payload, 'customer.email')))
            ->first();

        if (! $user) {
            Log::warning('Circle webhook user not found', ['event_type' => $eventType, 'customer_id' => $customerId]);
            return;
        }

        $startsAt = data_get($payload, 'subscription.current_term_starts_at') ?? data_get($payload, 'data.subscription.current_term_starts_at') ?? now();
        $endsAt = data_get($payload, 'subscription.current_term_ends_at') ?? data_get($payload, 'data.subscription.current_term_ends_at') ?? now()->copy()->addMonths(1);

        DB::transaction(function () use ($eventType, $eventId, $subscriptionId, $addonCode, $addon, $user, $startsAt, $endsAt, $payload): void {
            $status = $this->mapStatus($eventType, (string) (data_get($payload, 'subscription.status') ?? 'active'));

            $subscription = UserCircleSubscription::query()->firstOrNew([
                'user_id' => $user->id,
                'circle_id' => $addon->circle_id,
                'zoho_subscription_id' => $subscriptionId,
            ]);

            $this->fillSafe($subscription, [
                'id' => $subscription->id ?: (string) Str::uuid(),
                'user_id' => $user->id,
                'circle_id' => $addon->circle_id,
                'billing_term' => $addon->billing_term,
                'status' => $status,
                'zoho_subscription_id' => $subscriptionId,
                'zoho_addon_code' => $addonCode,
                'paid_starts_at' => $startsAt,
                'paid_ends_at' => $endsAt,
                'last_event_id' => $eventId,
                'last_event_type' => $eventType,
                'last_event_at' => now(),
                'metadata' => ['payload' => $payload],
            ]);
            $subscription->save();

            $member = CircleMember::query()->firstOrNew([
                'circle_id' => $addon->circle_id,
                'user_id' => $user->id,
            ]);

            $memberPayload = [
                'id' => $member->id ?: (string) Str::uuid(),
                'status' => $status === 'active' ? 'approved' : ($member->status ?: 'approved'),
                'role' => $member->role ?: 'member',
                'joined_at' => $member->joined_at ?: now(),
                'joined_via_payment' => $status === 'active',
                'billing_term' => $addon->billing_term,
                'paid_starts_at' => $startsAt,
                'paid_ends_at' => $endsAt,
                'payment_status' => $status,
                'zoho_subscription_id' => $subscriptionId,
                'zoho_addon_code' => $addonCode,
            ];
            $this->fillSafe($member, $memberPayload);
            $member->save();

            if ($status === 'active') {
                $user->forceFill(['membership_status' => 'circle_peer'])->save();
                return;
            }

            $hasOtherActive = UserCircleSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $hasOtherActive && $user->membership_status === 'circle_peer') {
                $user->forceFill(['membership_status' => 'only_unity_peer'])->save();
            }
        });
    }

    private function mapStatus(string $eventType, string $fallback): string
    {
        if (str_contains($eventType, 'cancel') || str_contains($eventType, 'expire') || str_contains($eventType, 'fail')) {
            return 'inactive';
        }

        if (str_contains($eventType, 'success') || str_contains($eventType, 'active') || str_contains($eventType, 'renew')) {
            return 'active';
        }

        return strtolower($fallback) ?: 'active';
    }

    private function fillSafe($model, array $payload): void
    {
        $columns = Schema::hasTable($model->getTable()) ? Schema::getColumnListing($model->getTable()) : [];
        $model->forceFill(Arr::only($payload, $columns));
    }
}
