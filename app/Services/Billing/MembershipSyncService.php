<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\Membership\MembershipUpgradeService;
use App\Support\Zoho\ZohoBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MembershipSyncService
{
    public function __construct(private readonly MembershipUpgradeService $membershipUpgradeService) {}

    public function syncUserMembershipFromZoho(User $user, array $zohoData): User
    {
        $subscription = $zohoData['subscription'] ?? [];
        $invoice = $zohoData['invoice'] ?? [];

        $startAt = $subscription['current_term_starts_at']
            ?? $subscription['start_date']
            ?? $subscription['created_time']
            ?? now()->toDateTimeString();

        $endAt = $subscription['current_term_ends_at']
            ?? $subscription['expires_at']
            ?? $subscription['next_billing_at']
            ?? $this->calculateEndsAt($startAt, $subscription);

        $syncedUser = $this->membershipUpgradeService->markAsOnlyUnityPeerAfterPayment($user, [
            'payment_id' => $zohoData['payment_id'] ?? null,
            'zoho_customer_id' => $zohoData['zoho_customer_id'] ?? $subscription['customer_id'] ?? $zohoData['customer_id'] ?? $user->zoho_customer_id ?? null,
            'zoho_subscription_id' => $subscription['subscription_id'] ?? null,
            'zoho_plan_code' => data_get($subscription, 'plan.plan_code') ?? $subscription['plan_code'] ?? null,
            'zoho_invoice_id' => $invoice['invoice_id'] ?? $subscription['invoice_id'] ?? null,
            'plan_name' => data_get($subscription, 'plan.name') ?? $subscription['name'] ?? null,
            'amount' => data_get($subscription, 'plan.price') ?? data_get($invoice, 'total') ?? null,
            'duration_months' => $this->calculateDurationMonths($subscription),
            'membership_starts_at' => $startAt,
            'membership_ends_at' => $endAt,
            'force_dates' => true,
            'last_payment_at' => now(),
        ]);

        Log::info('Membership synced from Zoho', [
            'user_id' => $syncedUser->id,
            'subscription_id' => $syncedUser->zoho_subscription_id ?? null,
            'plan_code' => $syncedUser->zoho_plan_code ?? null,
            'membership_status' => $syncedUser->membership_status ?? null,
        ]);

        return $syncedUser;
    }

    /**
     * Fetch all subscriptions for this customer from Zoho and sync each into user_memberships.
     */
    public function syncCustomerSubscriptionsFromZoho(User $user, ?ZohoBillingService $zohoBillingService = null): void
    {
        $customerId = $user->zoho_customer_id;
        if (! $customerId || ! Schema::hasTable('user_memberships')) {
            return;
        }

        try {
            $billingService = $zohoBillingService ?? app(ZohoBillingService::class);
            $response = $billingService->listSubscriptionsByCustomer((string) $customerId);
            $subscriptions = $response['subscriptions'] ?? [];

            if (empty($subscriptions) || ! is_array($subscriptions)) {
                return;
            }

            // Sort ascending by start/created date so periods line up chronologically
            usort($subscriptions, function (array $a, array $b): int {
                $aTime = $a['current_term_starts_at'] ?? $a['start_date'] ?? $a['created_time'] ?? '';
                $bTime = $b['current_term_starts_at'] ?? $b['start_date'] ?? $b['created_time'] ?? '';

                return strcmp((string) $aTime, (string) $bTime);
            });

            $now = now();

            foreach ($subscriptions as $sub) {
                $subId = $sub['subscription_id'] ?? null;
                $planCode = data_get($sub, 'plan.plan_code') ?? $sub['plan_code'] ?? null;
                $planName = data_get($sub, 'plan.name') ?? $sub['name'] ?? null;
                $rawStatus = strtolower((string) ($sub['status'] ?? ''));

                $startStr = $sub['current_term_starts_at'] ?? $sub['start_date'] ?? $sub['created_time'] ?? null;
                $endStr = $sub['current_term_ends_at'] ?? $sub['expires_at'] ?? $sub['next_billing_at'] ?? null;

                if (! $startStr) {
                    continue;
                }

                $startsAt = Carbon::parse($startStr);
                $endsAt = $endStr ? Carbon::parse($endStr) : Carbon::parse($this->calculateEndsAt($startStr, $sub));

                // Find or link payment
                $payment = null;
                if ($subId && Schema::hasTable('payments')) {
                    $payment = Payment::query()->where('zoho_subscription_id', $subId)->first();
                }

                // Determine membership status
                if ($endsAt->isPast() || in_array($rawStatus, ['expired', 'cancelled', 'stopped'], true)) {
                    $status = 'expired';
                } elseif ($startsAt->isFuture() || $rawStatus === 'future') {
                    $status = 'queued';
                } else {
                    $status = 'active';
                }

                $this->membershipUpgradeService->syncUserMembershipRow(
                    $user,
                    $payment,
                    $startsAt,
                    $endsAt,
                    [
                        'zoho_subscription_id' => $subId,
                        'zoho_plan_code' => $planCode,
                        'plan_name' => $planName,
                    ],
                    $status
                );
            }
        } catch (Throwable $t) {
            Log::warning('Failed syncing Zoho customer subscriptions', [
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'error' => $t->getMessage(),
            ]);
        }
    }

    /**
     * Ensure user_memberships rows exist by backfilling from payments, user dates, or Zoho.
     */
    public function ensureUserMembershipsSynced(User $user, ?ZohoBillingService $zohoBillingService = null): void
    {
        if (! Schema::hasTable('user_memberships')) {
            return;
        }

        $now = now();

        // 1. If user has Zoho customer ID, sync their subscriptions
        if ($user->zoho_customer_id) {
            $this->syncCustomerSubscriptionsFromZoho($user, $zohoBillingService);
        }

        // 2. Check for paid payments without user_memberships rows
        if (Schema::hasTable('payments')) {
            $paidPayments = Payment::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['paid', 'success', 'completed', 'Paid'])
                ->get();

            foreach ($paidPayments as $payment) {
                $alreadySynced = UserMembership::query()->where('payment_id', $payment->id)->exists();
                if ($alreadySynced) {
                    continue;
                }

                $startsAt = $payment->paid_at ? Carbon::parse($payment->paid_at) : Carbon::parse($payment->created_at);
                $durationMonths = (int) ($payment->plan?->duration_months ?? 1);
                $endsAt = $startsAt->copy()->addMonths(max(1, $durationMonths))->endOfDay();

                $status = $endsAt->isPast() ? 'expired' : ($startsAt->isFuture() ? 'queued' : 'active');

                $this->membershipUpgradeService->syncUserMembershipRow(
                    $user,
                    $payment,
                    $startsAt,
                    $endsAt,
                    [
                        'membership_plan_id' => $payment->membership_plan_id,
                        'zoho_plan_code' => $payment->zoho_plan_code,
                    ],
                    $status
                );
            }
        }

        // 3. If user has membership_starts_at and membership_ends_at on users table, ensure covered in user_memberships
        if ($user->membership_ends_at) {
            $userEndsAt = Carbon::parse((string) $user->membership_ends_at);
            $userStartsAt = $user->membership_starts_at ? Carbon::parse((string) $user->membership_starts_at) : $now->copy();

            $existingCount = UserMembership::query()->where('user_id', $user->id)->count();

            if ($existingCount === 0) {
                $status = $userEndsAt->isPast() ? 'expired' : 'active';
                $latestPayment = Schema::hasTable('payments')
                    ? Payment::query()->where('user_id', $user->id)->latest('created_at')->first()
                    : null;

                $this->membershipUpgradeService->syncUserMembershipRow(
                    $user,
                    $latestPayment,
                    $userStartsAt,
                    $userEndsAt,
                    [
                        'zoho_plan_code' => $user->zoho_plan_code,
                    ],
                    $status
                );
            }
        }

        // 4. Ensure past active memberships are marked expired
        UserMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['status' => 'expired']);
    }

    public function calculateDurationMonths(array $subscription): int
    {
        $interval = (int) ($subscription['interval'] ?? 1);
        $unit = strtolower((string) ($subscription['interval_unit'] ?? ''));
        $planName = strtolower((string) ($subscription['name'] ?? data_get($subscription, 'plan.name') ?? ''));
        $planCode = (string) (data_get($subscription, 'plan.plan_code') ?? $subscription['plan_code'] ?? '');

        if ($planCode === '014' || str_contains($planName, '2-year') || str_contains($planName, '2 year') || str_contains($planName, '2 years')) {
            return 24;
        }

        if ($planCode === '013' || $planCode === '015' || str_contains($planName, '1-year') || str_contains($planName, '1 year') || str_contains($planName, 'annual')) {
            return 12;
        }

        if ($planCode === '012' || str_contains($planName, '1-month') || str_contains($planName, '1 month')) {
            return 1;
        }

        if ($unit === 'years' || $unit === 'year' || str_contains($planName, 'annual')) {
            return max(1, $interval) * 12;
        }

        return max(1, $interval);
    }

    private function calculateEndsAt(string $startAt, array $subscription): string
    {
        try {
            $start = Carbon::parse($startAt);
        } catch (Throwable) {
            $start = now();
        }

        $interval = (int) ($subscription['interval'] ?? 1);
        $unit = strtolower((string) ($subscription['interval_unit'] ?? ''));
        $planName = strtolower((string) ($subscription['name'] ?? data_get($subscription, 'plan.name') ?? ''));
        $planCode = (string) (data_get($subscription, 'plan.plan_code') ?? $subscription['plan_code'] ?? '');

        if ($planCode === '014' || str_contains($planName, '2-year') || str_contains($planName, '2 year') || str_contains($planName, '2 years')) {
            return $start->copy()->addMonths(24)->toDateTimeString();
        }

        if ($planCode === '013' || $planCode === '015' || str_contains($planName, '1-year') || str_contains($planName, '1 year') || str_contains($planName, 'annual')) {
            return $start->copy()->addMonths(12)->toDateTimeString();
        }

        if ($planCode === '012' || str_contains($planName, '1-month') || str_contains($planName, '1 month')) {
            return $start->copy()->addMonths(1)->toDateTimeString();
        }

        if ($unit === 'years' || $unit === 'year' || str_contains($planName, 'annual')) {
            return $start->copy()->addYears(max(1, $interval))->toDateTimeString();
        }

        return $start->copy()->addMonths(max(1, $interval))->toDateTimeString();
    }
}
