<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\CircleMemberSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZohoCircleSubscriptionWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->header('X-Webhook-Token') ?? $request->header('x-webhook-token');
        $configuredSecret = (string) config('zoho_billing.webhook_secret', '');

        if ($configuredSecret === '' || ! is_string($token) || ! hash_equals($configuredSecret, $token)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized webhook request.'], 401);
        }

        $payload = $request->all();
        $hostedPageId = (string) (data_get($payload, 'hostedpage.hostedpage_id')
            ?? data_get($payload, 'data.hostedpage.hostedpage_id')
            ?? data_get($payload, 'hostedpage_id')
            ?? '');

        if ($hostedPageId === '') {
            return response()->json(['success' => true, 'handled' => false]);
        }

        $subscription = CircleMemberSubscription::query()->where('zoho_hostedpage_id', $hostedPageId)->first();

        if (! $subscription) {
            return response()->json(['success' => true, 'handled' => false]);
        }

        $status = strtolower((string) (data_get($payload, 'subscription.status')
            ?? data_get($payload, 'hostedpage.status')
            ?? data_get($payload, 'status')
            ?? ''));

        DB::transaction(function () use ($subscription, $payload, $status): void {
            $isPaid = in_array($status, ['active', 'live', 'paid', 'payment_success', 'success', 'completed'], true);
            $isCancelled = in_array($status, ['cancelled', 'canceled'], true);

            if ($isPaid) {
                $startsAt = now();
                $subscription->forceFill([
                    'status' => 'active',
                    'joined_at' => now(),
                    'starts_at' => $startsAt,
                    'expires_at' => $startsAt->copy()->addMonths((int) $subscription->duration_months),
                    'zoho_subscription_id' => data_get($payload, 'subscription.subscription_id') ?? data_get($payload, 'subscription_id'),
                    'zoho_payment_id' => data_get($payload, 'payment.payment_id') ?? data_get($payload, 'payment_id'),
                    'payload' => $payload,
                ])->save();

                CircleMember::query()->updateOrCreate(
                    [
                        'circle_id' => $subscription->circle_id,
                        'user_id' => $subscription->user_id,
                    ],
                    [
                        'role' => 'member',
                        'status' => 'approved',
                        'joined_at' => now(),
                    ]
                );

                return;
            }

            $subscription->forceFill([
                'status' => $isCancelled ? 'cancelled' : 'failed',
                'payload' => $payload,
            ])->save();
        });

        Log::info('Circle subscription webhook handled', [
            'hostedpage_id' => $hostedPageId,
            'subscription_request_id' => $subscription->id,
            'status' => $status,
        ]);

        return response()->json(['success' => true, 'handled' => true]);
    }
}
