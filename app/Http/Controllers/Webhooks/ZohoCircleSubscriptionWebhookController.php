<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ZohoCircleSubscriptionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->isValidWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized webhook token.'], 401);
        }

        $payload = $request->all();

        Log::info('zoho circle webhook payload', ['payload' => $payload]);

        if (! $this->isPaymentSuccessEvent($payload)) {
            return response()->json(['success' => true, 'message' => 'Webhook ignored']);
        }

        $user = $this->resolveUser($payload);
        if (! $user) {
            Log::warning('zoho api error', ['context' => 'circle_webhook_user_not_found', 'payload' => $payload]);

            return response()->json(['success' => true, 'message' => 'User not found']);
        }

        $circleId = $this->extractCircleId($payload);
        $subscriptionStart = $this->extractDate($payload, ['subscription_start', 'subscription.start_date', 'subscription.current_term_starts_at']);
        $subscriptionExpiry = $this->extractDate($payload, ['subscription_expiry', 'subscription.next_billing_at', 'subscription.current_term_ends_at']);

        $updates = ['membership_status' => 'circle_peer'];

        if (Schema::hasColumn('users', 'subscription_start') && $subscriptionStart) {
            $updates['subscription_start'] = $subscriptionStart;
        }

        if (Schema::hasColumn('users', 'subscription_expiry') && $subscriptionExpiry) {
            $updates['subscription_expiry'] = $subscriptionExpiry;
        }

        if (Schema::hasColumn('users', 'membership_starts_at') && $subscriptionStart) {
            $updates['membership_starts_at'] = $subscriptionStart;
        }

        if (Schema::hasColumn('users', 'membership_ends_at') && $subscriptionExpiry) {
            $updates['membership_ends_at'] = $subscriptionExpiry;
        }

        $user->forceFill($updates)->save();

        if ($circleId !== null) {
            CircleMember::query()->updateOrCreate(
                ['circle_id' => $circleId, 'user_id' => $user->id],
                [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => $subscriptionStart ?? now(),
                    'left_at' => null,
                ]
            );
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    private function isValidWebhook(Request $request): bool
    {
        $expected = (string) env('ZOHO_WEBHOOK_TOKEN', '');

        if ($expected === '') {
            return false;
        }

        $incoming = (string) ($request->header('X-Zoho-Webhook-Token')
            ?? $request->header('X-Zoho-Webhook-Signature')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? $request->input('token')
            ?? '');

        return hash_equals($expected, trim($incoming));
    }

    private function isPaymentSuccessEvent(array $payload): bool
    {
        $event = strtolower((string) (data_get($payload, 'event_type') ?? data_get($payload, 'event') ?? data_get($payload, 'type')));
        $status = strtolower((string) (data_get($payload, 'payment_status') ?? data_get($payload, 'payment.status') ?? data_get($payload, 'subscription.status')));

        if (str_contains($event, 'payment') || str_contains($event, 'subscription')) {
            return in_array($status, ['paid', 'success', 'completed', 'active', 'live'], true)
                || str_contains($event, 'success')
                || str_contains($event, 'activated');
        }

        return false;
    }

    private function resolveUser(array $payload): ?User
    {
        $subscriptionId = (string) (data_get($payload, 'subscription.subscription_id') ?? data_get($payload, 'subscription_id'));
        $customerId = (string) (data_get($payload, 'customer.customer_id') ?? data_get($payload, 'customer_id'));
        $email = (string) (data_get($payload, 'customer.email') ?? data_get($payload, 'email'));

        return User::query()
            ->when($subscriptionId !== '', fn ($query) => $query->orWhere('zoho_subscription_id', $subscriptionId))
            ->when($customerId !== '', fn ($query) => $query->orWhere('zoho_customer_id', $customerId))
            ->when($email !== '', fn ($query) => $query->orWhere('email', $email))
            ->first();
    }

    private function extractCircleId(array $payload): ?string
    {
        $circleId = (string) (data_get($payload, 'circle_id')
            ?? data_get($payload, 'subscription.custom_fields.circle_id')
            ?? data_get($payload, 'data.subscription.custom_fields.circle_id')
            ?? '');

        return $circleId !== '' ? $circleId : null;
    }

    private function extractDate(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (! $value) {
                continue;
            }

            try {
                return Carbon::parse((string) $value)->toDateTimeString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
