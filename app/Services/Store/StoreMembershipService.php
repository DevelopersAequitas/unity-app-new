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

    public function quoteRenewal(User $user, ?string $planId = null, array $options = []): array
    {
        $plan = null;
        $product = null;

        if (! empty($options['product_id'])) {
            $product = \App\Models\Store\Product::find($options['product_id']);
        }

        if ($planId) {
            $plan = StoreMembershipPlan::active()->find($planId) ?? StoreMembershipPlan::find($planId);
        }

        if (! $plan && $product) {
            $plan = StoreMembershipPlan::where('duration_months', $product->subscription_duration_months ?: 1)->first()
                ?? StoreMembershipPlan::first();
        }

        if (! $plan && ! $product) {
            $plan = StoreMembershipPlan::first();
        }

        if (! $plan && ! $product) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        // Determine duration months (Priority: options > product > plan > fallback)
        $durationMonths = (int) (
            $options['duration_months']
            ?? ($product->subscription_duration_months ?? ($plan->duration_months ?? 1))
        );
        if ($durationMonths <= 0) {
            $durationMonths = 1;
        }

        // Determine price in coins (Priority: options coins/price_coins > product coin_price > plan price_coins > fallback)
        $priceCoins = (int) (
            $options['coins']
            ?? ($options['price_coins']
            ?? ($product ? ($product->coin_price ?: $product->price_coins)
            : ($plan ? ($plan->price_coins ?: $plan->coins) : 95)))
        );

        if ($priceCoins <= 0) {
            $priceCoins = 95;
        }

        $currentExpiry = $user->membership_expiry ? Carbon::parse($user->membership_expiry) : null;
        $isActive = $currentExpiry && $currentExpiry->isFuture();

        $startDate = $isActive ? $currentExpiry->copy()->addDay() : now();
        $baseDate = $isActive ? $currentExpiry : now();
        $newEndDate = $baseDate->copy()->addMonths($durationMonths);

        $maxHorizonMonths = (int) StoreConfig::getValue('membership_max_horizon_months', 60);
        if (now()->diffInMonths($newEndDate) > $maxHorizonMonths) {
            throw new Exception(StoreErrorCodes::MEMBERSHIP_LIMIT_EXCEEDED, 422);
        }

        $walletInfo = $this->walletService->getWalletInfo($user);
        $shortfall = max(0, $priceCoins - $walletInfo['balance']);

        return [
            'plan_id' => $plan ? $plan->id : ($product ? $product->id : null),
            'product_id' => $product ? $product->id : null,
            'plan_name' => $product ? $product->name : ($plan ? $plan->name : "{$durationMonths} Month Membership"),
            'duration_months' => $durationMonths,
            'price_coins' => $priceCoins,
            'current_end_date' => $currentExpiry ? $currentExpiry->toDateString() : null,
            'new_start_date' => $startDate->toDateString(),
            'new_end_date' => $newEndDate->toDateString(),
            'months_added' => $durationMonths,
            'wallet_balance' => $walletInfo['balance'],
            'shortfall' => $shortfall,
            'can_renew' => $shortfall === 0,
        ];
    }

    public function renewMembership(User $user, ?string $planId = null, ?string $idempotencyKey = null, array $options = []): array
    {
        $quote = $this->quoteRenewal($user, $planId, $options);
        $priceCoins = (int) $quote['price_coins'];
        $plan = $planId ? (StoreMembershipPlan::find($planId) ?? StoreMembershipPlan::first()) : StoreMembershipPlan::first();
        $planName = $quote['plan_name'];

        return DB::transaction(function () use ($user, $plan, $quote, $priceCoins, $planName, $idempotencyKey) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->coins_balance < $priceCoins) {
                throw new Exception(StoreErrorCodes::INSUFFICIENT_COINS, 400);
            }

            $renewalId = (string) Str::uuid();

            // Execute Debit
            $debitResult = $this->walletService->executeSpendDebit(
                $lockedUser,
                $priceCoins,
                'MEMBERSHIP_RENEWAL',
                $renewalId,
                $idempotencyKey,
                "Membership Renewal: {$planName}"
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
                'plan_id' => $plan ? $plan->id : $quote['plan_id'],
                'coins_paid' => $priceCoins,
                'start_date' => $quote['new_start_date'],
                'end_date' => $quote['new_end_date'],
                'status' => 'ACTIVE',
                'activated_at' => now(),
                'created_at' => now(),
            ]);

            // Create Receipt safely
            $receipt = null;
            try {
                $receipt = Receipt::create([
                    'receipt_no' => 'REC-MEM-'.strtoupper(Str::random(8)),
                    'user_id' => $lockedUser->id,
                    'coins_paid' => $priceCoins,
                    'receipt_data' => [
                        'plan_name' => $planName,
                        'months_added' => $quote['duration_months'],
                        'new_end_date' => $newEndDate->toDateString(),
                        'coins_paid' => $priceCoins,
                    ],
                    'issued_at' => now(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Ignore if order_id is strictly required by legacy schema
            }

            // Create Notification
            NotificationEvent::create([
                'event_key' => 'membership.renewed',
                'user_id' => $lockedUser->id,
                'reference_type' => 'MEMBERSHIP',
                'reference_id' => $renewalId,
                'payload' => [
                    'plan_name' => $planName,
                    'new_end_date' => $newEndDate->toDateString(),
                ],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);

            return [
                'renewal_id' => $renewalId,
                'membership_status' => 'active',
                'expiry_date' => $newEndDate->toDateString(),
                'coins_debited' => $priceCoins,
                'remaining_balance' => (int) ($lockedUser->fresh()->coins_balance ?? 0),
                'ledger_id' => $ledger->id,
                'receipt' => $receipt,
            ];
        });
    }
}
