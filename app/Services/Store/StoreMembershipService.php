<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\MembershipLedger;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Receipt;
use App\Models\Store\StoreConfig;
use App\Models\Store\StoreMembershipPlan;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreMembershipService
{
    protected StoreWalletService $walletService;

    public function __construct(StoreWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function getMembershipStatus(User $user): array
    {
        $expiry = $user->membership_expiry ? Carbon::parse($user->membership_expiry) : null;
        $isActive = $expiry && $expiry->isFuture();
        $daysRemaining = $isActive ? now()->diffInDays($expiry, false) : 0;

        return [
            'status' => $isActive ? 'ACTIVE' : ($expiry ? 'EXPIRED' : 'NONE'),
            'membership_status' => (string) ($user->membership_status ?? 'free_peer'),
            'start_date' => $user->created_at ? $user->created_at->toDateString() : null,
            'end_date' => $expiry ? $expiry->toDateString() : null,
            'days_remaining' => max(0, (int) $daysRemaining),
        ];
    }

    public function getActivePlans(): Collection
    {
        return StoreMembershipPlan::active()->orderBy('sort_order', 'asc')->get();
    }

    public function quoteRenewal(User $user, string $planId): array
    {
        $plan = StoreMembershipPlan::active()->find($planId);
        if (! $plan) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        $currentExpiry = $user->membership_expiry ? Carbon::parse($user->membership_expiry) : null;
        $isActive = $currentExpiry && $currentExpiry->isFuture();

        $startDate = $isActive ? $currentExpiry->copy()->addDay() : now();
        $baseDate = $isActive ? $currentExpiry : now();
        $newEndDate = $baseDate->copy()->addMonths($plan->duration_months);

        $maxHorizonMonths = (int) StoreConfig::getValue('membership_max_horizon_months', 60);
        if (now()->diffInMonths($newEndDate) > $maxHorizonMonths) {
            throw new Exception(StoreErrorCodes::MEMBERSHIP_LIMIT_EXCEEDED, 422);
        }

        $walletInfo = $this->walletService->getWalletInfo($user);
        $shortfall = max(0, $plan->price_coins - $walletInfo['balance']);

        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'duration_months' => $plan->duration_months,
            'price_coins' => $plan->price_coins,
            'current_end_date' => $currentExpiry ? $currentExpiry->toDateString() : null,
            'new_start_date' => $startDate->toDateString(),
            'new_end_date' => $newEndDate->toDateString(),
            'months_added' => $plan->duration_months,
            'wallet_balance' => $walletInfo['balance'],
            'shortfall' => $shortfall,
            'can_renew' => $shortfall === 0,
        ];
    }

    public function renewMembership(User $user, string $planId, ?string $idempotencyKey = null): array
    {
        $quote = $this->quoteRenewal($user, $planId);
        $plan = StoreMembershipPlan::findOrFail($planId);

        return DB::transaction(function () use ($user, $plan, $quote, $idempotencyKey) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->coins_balance < $plan->price_coins) {
                throw new Exception(StoreErrorCodes::INSUFFICIENT_COINS, 400);
            }

            $renewalId = (string) Str::uuid();

            // Execute Debit
            $debitResult = $this->walletService->executeSpendDebit(
                $lockedUser,
                $plan->price_coins,
                'MEMBERSHIP_RENEWAL',
                $renewalId,
                $idempotencyKey,
                "Membership Renewal: {$plan->name}"
            );

            // Update user membership
            $newEndDate = Carbon::parse($quote['new_end_date']);
            $lockedUser->update([
                'membership_status' => 'active',
                'membership_expiry' => $newEndDate->toDateString(),
            ]);

            // Create Membership Ledger entry
            $ledger = MembershipLedger::create([
                'id' => $renewalId,
                'user_id' => $lockedUser->id,
                'plan_id' => $plan->id,
                'coins_paid' => $plan->price_coins,
                'start_date' => $quote['new_start_date'],
                'end_date' => $quote['new_end_date'],
                'status' => 'ACTIVE',
                'activated_at' => now(),
                'created_at' => now(),
            ]);

            // Create Receipt
            $receipt = Receipt::create([
                'receipt_no' => 'REC-MEM-' . strtoupper(Str::random(8)),
                'order_id' => null,
                'user_id' => $lockedUser->id,
                'coins_paid' => $plan->price_coins,
                'receipt_data' => [
                    'plan_name' => $plan->name,
                    'months_added' => $plan->duration_months,
                    'new_end_date' => $newEndDate->toDateString(),
                    'coins_paid' => $plan->price_coins,
                ],
                'issued_at' => now(),
                'created_at' => now(),
            ]);

            // Create Notification
            NotificationEvent::create([
                'event_key' => 'membership.renewed',
                'user_id' => $lockedUser->id,
                'reference_type' => 'MEMBERSHIP',
                'reference_id' => $renewalId,
                'payload' => [
                    'plan_name' => $plan->name,
                    'new_end_date' => $newEndDate->toDateString(),
                ],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);

            return [
                'ledger' => $ledger,
                'receipt' => $receipt,
                'membership_status' => $this->getMembershipStatus($lockedUser->fresh()),
            ];
        });
    }
}
