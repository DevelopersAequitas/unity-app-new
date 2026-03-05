<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\CircleMemberSubscription;
use App\Models\User;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ZohoCircleSubscriptionWebhookController extends Controller
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        $hostedPageId = (string) (data_get($payload, 'hostedpage.hostedpage_id')
            ?? data_get($payload, 'data.hostedpage_id')
            ?? $request->input('hostedpage_id')
            ?? '');

        if ($hostedPageId === '') {
            Log::warning('Circle subscription webhook ignored: hostedpage_id missing', [
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json(['success' => true]);
        }

        if (! Schema::hasTable('circle_join_payments')) {
            Log::warning('Circle subscription webhook ignored: circle_join_payments table missing', [
                'hostedpage_id' => $hostedPageId,
            ]);

            return response()->json(['success' => true]);
        }

        $joinPayment = DB::table('circle_join_payments')
            ->where('zoho_hostedpage_id', $hostedPageId)
            ->orderByDesc('created_at')
            ->first();

        if (! $joinPayment) {
            Log::warning('Circle subscription webhook ignored: payment row not found', [
                'hostedpage_id' => $hostedPageId,
            ]);

            return response()->json(['success' => true]);
        }

        try {
            $hostedPageResponse = $this->zohoBillingService->getHostedPage($hostedPageId);
        } catch (\Throwable $throwable) {
            Log::error('Circle subscription webhook failed to fetch hosted page', [
                'hostedpage_id' => $hostedPageId,
                'payment_id' => $joinPayment->id,
                'error' => $throwable->getMessage(),
            ]);

            return response()->json(['success' => true]);
        }

        $hostedPage = (array) data_get($hostedPageResponse, 'hostedpage', []);
        $subscription = (array) (data_get($hostedPage, 'subscription') ?? data_get($hostedPage, 'subscriptions.0') ?? []);
        $paymentBlock = (array) (data_get($hostedPage, 'payment') ?? data_get($hostedPage, 'payments.0') ?? []);

        $hostedPageStatus = strtolower((string) (data_get($hostedPage, 'status')
            ?? data_get($hostedPageResponse, 'status')
            ?? ''));

        $subscriptionId = data_get($subscription, 'subscription_id')
            ?? data_get($hostedPage, 'subscription_id');
        $invoiceId = data_get($hostedPage, 'invoice.invoice_id')
            ?? data_get($hostedPage, 'invoice_id')
            ?? data_get($subscription, 'invoice_id');
        $zohoPaymentId = data_get($paymentBlock, 'payment_id')
            ?? data_get($hostedPage, 'payment.payment_id')
            ?? data_get($hostedPage, 'payment_id');

        $isPaid = in_array($hostedPageStatus, ['completed', 'paid', 'success', 'payment_success', 'active'], true)
            || ! empty($subscriptionId);

        DB::transaction(function () use ($joinPayment, $hostedPageStatus, $subscriptionId, $invoiceId, $zohoPaymentId, $hostedPageResponse, $isPaid): void {
            $update = [
                'status' => $isPaid ? 'success' : ((string) ($joinPayment->status ?? 'pending') ?: 'pending'),
                'payload' => $hostedPageResponse,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('circle_join_payments', 'zoho_hostedpage_status')) {
                $update['zoho_hostedpage_status'] = $hostedPageStatus;
            }
            if (Schema::hasColumn('circle_join_payments', 'zoho_subscription_id')) {
                $update['zoho_subscription_id'] = $subscriptionId;
            }
            if (Schema::hasColumn('circle_join_payments', 'zoho_invoice_id')) {
                $update['zoho_invoice_id'] = $invoiceId;
            }
            if (Schema::hasColumn('circle_join_payments', 'zoho_payment_id')) {
                $update['zoho_payment_id'] = $zohoPaymentId;
            }

            DB::table('circle_join_payments')->where('id', $joinPayment->id)->update($update);

            if (! $isPaid) {
                return;
            }

            User::query()->where('id', $joinPayment->user_id)->update([
                'membership_status' => 'circle_peer',
                'membership_expiry' => now()->addMonths((int) ($joinPayment->duration_months ?? 1)),
                'updated_at' => now(),
            ]);

            CircleMember::query()->updateOrCreate(
                [
                    'circle_id' => $joinPayment->circle_id,
                    'user_id' => $joinPayment->user_id,
                ],
                [
                    'role' => 'member',
                    'status' => 'approved',
                    'joined_at' => now(),
                ]
            );

            $subscriptionPayload = [
                'duration_months' => (int) ($joinPayment->duration_months ?? 1),
                'price' => $joinPayment->price,
                'currency' => (string) ($joinPayment->currency ?? 'INR'),
                'status' => 'active',
                'joined_at' => now(),
                'starts_at' => now(),
                'expires_at' => now()->addMonths((int) ($joinPayment->duration_months ?? 1)),
                'zoho_hostedpage_id' => $joinPayment->zoho_hostedpage_id,
                'zoho_subscription_id' => $subscriptionId,
                'zoho_payment_id' => $zohoPaymentId,
                'payload' => $hostedPageResponse,
            ];

            $existing = CircleMemberSubscription::query()
                ->where('circle_id', $joinPayment->circle_id)
                ->where('user_id', $joinPayment->user_id)
                ->latest('created_at')
                ->first();

            if ($existing) {
                $existing->forceFill($subscriptionPayload)->save();
            } else {
                CircleMemberSubscription::query()->create(array_merge($subscriptionPayload, [
                    'circle_id' => $joinPayment->circle_id,
                    'user_id' => $joinPayment->user_id,
                ]));
            }
        });

        Log::info('Circle subscription webhook handled', [
            'hostedpage_id' => $hostedPageId,
            'payment_id' => $joinPayment->id,
            'status' => $hostedPageStatus,
            'paid' => $isPaid,
        ]);

        return response()->json(['success' => true]);
    }
}
