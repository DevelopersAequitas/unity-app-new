<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\Billing\MembershipSyncService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingCheckoutController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipSyncService $membershipSyncService,
    ) {
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->zohoBillingService->createHostedPageForSubscription($user, $validated['plan_code']);

            $hostedPageId = (string) data_get($result, 'hostedpage_id', '');
            $checkoutUrl = (string) data_get($result, 'checkout_url', '');

            try {
                $this->recordPendingZohoPayment($user, $validated['plan_code'], $hostedPageId);
            } catch (Throwable $throwable) {
                Log::warning('ZOHO_CHECKOUT_PENDING_RECORD_FAILED', [
                    'user_id' => $user->id,
                    'hostedpage_id' => $hostedPageId,
                    'message' => $throwable->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Hosted checkout URL created successfully.',
                'data' => [
                    'hostedpage_id' => $hostedPageId,
                    'checkout_url' => $checkoutUrl,
                ],
            ]);
        } catch (ValidationException $validationException) {
            return response()->json([
                'success' => false,
                'message' => collect($validationException->errors())->flatten()->first() ?? 'Validation failed',
                'data' => [
                    'errors' => $validationException->errors(),
                ],
            ], 422);
        } catch (Throwable $throwable) {
            Log::error('Zoho checkout creation failed', [
                'user_id' => $user->id,
                'message' => 'Failed to generate checkout URL.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate checkout URL.',
                'data' => [],
            ], 500);
        }
    }


    public function syncHostedPage(Request $request, string $hostedpageId)
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $hostedPageResponse = $this->zohoBillingService->getHostedPage($hostedpageId);
            $updated = $this->zohoBillingService->syncMembershipFromHostedPage($user, $hostedPageResponse);
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Hosted page membership sync completed.',
                'data' => [
                    'handled' => $updated,
                    'zoho_customer_id' => $user->zoho_customer_id,
                    'zoho_subscription_id' => $user->zoho_subscription_id,
                    'zoho_plan_code' => $user->zoho_plan_code,
                    'zoho_last_invoice_id' => $user->zoho_last_invoice_id,
                    'membership_starts_at' => $user->membership_starts_at,
                    'membership_ends_at' => $user->membership_ends_at,
                    'last_payment_at' => $user->last_payment_at,
                ],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Zoho hosted page sync failed', [
                'user_id' => $user->id,
                'hostedpage_id' => $hostedpageId,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hosted page sync failed.',
                'data' => [],
            ], 500);
        }
    }

    public function status(Request $request, string $hostedpage_id)
    {
        try {
            /** @var User|null $authUser */
            $authUser = $request->user();

            if (! $authUser || ! Schema::hasTable('circle_join_payments')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'data' => [],
                ], 404);
            }

            $joinPayment = DB::table('circle_join_payments')
                ->where('zoho_hostedpage_id', $hostedpage_id)
                ->where('user_id', $authUser->id)
                ->orderByDesc('created_at')
                ->first();

            if (! $joinPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'data' => [],
                ], 404);
            }

            $zohoResponse = $this->zohoBillingService->getHostedPage($hostedpage_id);
            $hostedPage = $zohoResponse['hostedpage'] ?? [];

            $hostedPageStatus = (string) (
                data_get($hostedPage, 'status')
                ?? data_get($hostedPage, 'hostedpage_status')
                ?? data_get($zohoResponse, 'status')
                ?? ''
            );

            $subscriptionId = data_get($hostedPage, 'subscription.subscription_id')
                ?? data_get($hostedPage, 'subscriptions.0.subscription_id')
                ?? data_get($hostedPage, 'subscription_id');

            return response()->json([
                'success' => true,
                'message' => 'Checkout status fetched',
                'data' => [
                    'hostedpage_id' => $hostedpage_id,
                    'has_subscription' => ! empty($subscriptionId),
                    'hostedpage_status' => $hostedPageStatus,
                    'join_payment_status' => $joinPayment->status,
                ],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Zoho checkout status sync failed', [
                'hostedpage_id' => $hostedpage_id,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function recordPendingZohoPayment(User $user, string $planCode, string $hostedpageId): void
    {
        $paymentQuery = Payment::query()
            ->whereNotNull('zoho_hostedpage_id')
            ->where('zoho_hostedpage_id', $hostedpageId);

        if (Schema::hasColumn('payments', 'provider')) {
            $paymentQuery->where(function ($query) {
                $query->where('provider', 'zoho')
                    ->orWhereNull('provider');
            });
        }

        $payment = $paymentQuery->first();

        if (! $payment) {
            $payment = new Payment();
            $payment->id = (string) Str::uuid();
        }

        $payload = [
            'user_id' => $user->id,
            'zoho_plan_code' => $planCode,
            'zoho_hostedpage_id' => $hostedpageId,
            'status' => 'pending',
        ];

        if (Schema::hasColumn('payments', 'provider')) {
            $payload['provider'] = 'zoho';
        }

        $payment->forceFill($payload);

        $payment->save();
    }

    private function syncUserMembershipRow(User $user, Payment $payment, mixed $startsAt, mixed $endsAt): void
    {
        if (! Schema::hasTable('user_memberships')) {
            return;
        }

        $existing = UserMembership::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($existing) {
            $existing->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'payment_id' => $payment->id,
            ])->save();

            return;
        }

        try {
            UserMembership::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'membership_plan_id' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'payment_id' => $payment->id,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Unable to create user_memberships row during Zoho sync', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}

/*
| Postman Smoke Steps
| 1) GET /api/v1/circles/{circle}/subscription-prices
| 2) POST /api/v1/circles/{circle}/join-with-subscription {"duration_months":3,"currency":"INR"}
| 3) Open checkout_url and complete payment
| 4) Confirm webhook hits and circle_join_payments status becomes success
| 5) Confirm users.membership_status updated to Circle Peer
*/
