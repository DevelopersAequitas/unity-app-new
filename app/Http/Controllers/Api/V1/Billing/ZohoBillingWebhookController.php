<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\User;
use App\Services\Billing\MembershipSyncService;
use App\Support\Zoho\ZohoBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ZohoBillingWebhookController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipSyncService $membershipSyncService,
    ) {
    }

    public function handle(Request $request)
    {
        if (! $this->isValidWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();
        $subscriptionId = data_get($payload, 'subscription.subscription_id')
            ?? data_get($payload, 'data.subscription.subscription_id')
            ?? data_get($payload, 'subscription_id');
        $invoiceId = data_get($payload, 'invoice.invoice_id')
            ?? data_get($payload, 'data.invoice.invoice_id')
            ?? data_get($payload, 'invoice_id');
        $customerId = data_get($payload, 'customer.customer_id')
            ?? data_get($payload, 'data.customer.customer_id')
            ?? data_get($payload, 'customer_id');
        $email = data_get($payload, 'customer.email')
            ?? data_get($payload, 'data.customer.email')
            ?? data_get($payload, 'email');

        $user = User::query()
            ->when($subscriptionId, fn ($q) => $q->orWhere('zoho_subscription_id', $subscriptionId))
            ->when($customerId, fn ($q) => $q->orWhere('zoho_customer_id', $customerId))
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->first();

        if (! $user) {
            Log::warning('Zoho webhook user not found', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'email_masked' => $this->maskEmail($email),
            ]);

            return response()->json(['success' => true, 'message' => 'No matching user']);
        }

        try {
            $subscription = [];
            $invoice = [];

            if ($subscriptionId) {
                $subscriptionResp = $this->zohoBillingService->getSubscription($subscriptionId);
                $subscription = $subscriptionResp['subscription'] ?? $subscriptionResp;
            }

            if ($invoiceId) {
                $invoiceResp = $this->zohoBillingService->getInvoice($invoiceId);
                $invoice = $invoiceResp['invoice'] ?? $invoiceResp;
            } elseif ($subscriptionId) {
                $invoiceList = $this->zohoBillingService->listInvoicesBySubscription($subscriptionId);
                $invoice = ($invoiceList['invoices'][0] ?? []);
            }

            $this->membershipSyncService->syncUserMembershipFromZoho($user, [
                'subscription' => $subscription,
                'invoice' => $invoice,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $throwable) {
            Log::error('Zoho webhook sync failed', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'error' => $throwable->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Webhook sync failed'], 500);
        }
    }

    public function handleCircleSubscription(Request $request)
    {
        if (! $this->isValidWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();

        Log::info('circle webhook received', [
            'payload' => $payload,
        ]);

        try {
            $hostedPageId = (string) (
                data_get($payload, 'hostedpage.hostedpage_id')
                ?? data_get($payload, 'data.hostedpage.hostedpage_id')
                ?? data_get($payload, 'hostedpage_id')
                ?? ''
            );

            $customerId = (string) (
                data_get($payload, 'customer.customer_id')
                ?? data_get($payload, 'data.customer.customer_id')
                ?? data_get($payload, 'customer_id')
                ?? ''
            );

            $addonCode = (string) (
                data_get($payload, 'addon.addon_code')
                ?? data_get($payload, 'data.addon.addon_code')
                ?? data_get($payload, 'addons.0.addon_code')
                ?? data_get($payload, 'subscription.addons.0.addon_code')
                ?? ''
            );

            $subscription = CircleSubscription::query()
                ->when($hostedPageId !== '', fn ($q) => $q->where('zoho_hosted_page_id', $hostedPageId))
                ->latest('created_at')
                ->first();

            if (! $subscription && $customerId !== '') {
                $subscription = CircleSubscription::query()
                    ->where('zoho_customer_id', $customerId)
                    ->where('status', 'pending')
                    ->when($addonCode !== '', fn ($q) => $q->where('zoho_addon_code', $addonCode))
                    ->latest('created_at')
                    ->first();
            }

            if (! $subscription) {
                Log::warning('circle webhook subscription match failed', [
                    'hostedpage_id' => $hostedPageId,
                    'customer_id' => $customerId,
                    'addon_code' => $addonCode,
                ]);

                return response()->json(['success' => true, 'message' => 'No matching circle subscription']);
            }

            Log::info('circle webhook matched subscription', [
                'circle_subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'circle_id' => $subscription->circle_id,
            ]);

            if ($subscription->status === 'active') {
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }

            $startedAtRaw = data_get($payload, 'subscription.current_term_starts_at')
                ?? data_get($payload, 'subscription.start_date')
                ?? data_get($payload, 'payment.paid_time')
                ?? now()->toDateTimeString();

            $startedAt = Carbon::parse($startedAtRaw);
            $durationMonths = (int) ($subscription->circle?->circle_duration_months ?: 12);

            $subscription->forceFill([
                'status' => 'active',
                'zoho_subscription_id' => data_get($payload, 'subscription.subscription_id') ?? $subscription->zoho_subscription_id,
                'zoho_payment_id' => data_get($payload, 'payment.payment_id') ?? data_get($payload, 'payment_id') ?? $subscription->zoho_payment_id,
                'zoho_hosted_page_id' => $hostedPageId !== '' ? $hostedPageId : $subscription->zoho_hosted_page_id,
                'started_at' => $startedAt,
                'paid_at' => now(),
                'expires_at' => $startedAt->copy()->addMonths(max(1, $durationMonths)),
                'raw_webhook_payload' => $payload,
            ])->save();

            $user = $subscription->user;

            $user->forceFill([
                'membership_status' => 'Circle Peer',
                'active_circle_id' => $subscription->circle_id,
                'active_circle_subscription_id' => $subscription->id,
                'circle_joined_at' => $subscription->started_at,
                'circle_expires_at' => $subscription->expires_at,
                'active_circle_addon_code' => $subscription->zoho_addon_code,
                'active_circle_addon_name' => $subscription->zoho_addon_name,
            ])->save();

            Log::info('user membership upgraded', [
                'user_id' => $user->id,
                'membership_status' => $user->membership_status,
            ]);

            $member = CircleMember::withTrashed()
                ->where('circle_id', $subscription->circle_id)
                ->where('user_id', $subscription->user_id)
                ->first();

            if ($member) {
                if ($member->trashed()) {
                    $member->restore();
                }

                $member->forceFill([
                    'status' => 'approved',
                    'role' => $member->role ?: 'member',
                    'joined_at' => $member->joined_at ?: $subscription->started_at,
                    'left_at' => null,
                ])->save();
            } else {
                CircleMember::query()->create([
                    'circle_id' => $subscription->circle_id,
                    'user_id' => $subscription->user_id,
                    'role' => 'member',
                    'status' => 'approved',
                    'joined_at' => $subscription->started_at,
                ]);
            }

            Log::info('circle member attached', [
                'circle_id' => $subscription->circle_id,
                'user_id' => $subscription->user_id,
            ]);

            Log::info('circle webhook activated subscription', [
                'circle_subscription_id' => $subscription->id,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $throwable) {
            Log::error('circle webhook failed', [
                'message' => $throwable->getMessage(),
            ]);

            return response()->json(['success' => true, 'message' => 'Webhook received']);
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $expected = (string) env('ZOHO_WEBHOOK_TOKEN', '');

        if ($expected === '') {
            return false;
        }

        $incoming = (string) ($request->header('X-Zoho-Webhook-Signature')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? $request->input('token')
            ?? '');

        return hash_equals($expected, $incoming);
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1) . '***@' . $domain;
    }
}
