<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\MembershipPlan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CirclePriceResolver
{
    public const SOURCE_CIRCLE_PLAN = 'circle_plan';

    public const SOURCE_CIRCLE_OVERRIDE = 'circle_override';

    public const SOURCE_MEMBERSHIP_PLAN = 'membership_plan';

    /**
     * Resolve the price for a Circle and/or CircleJoinRequest.
     *
     * Priority:
     * 1. Exact selected Circle pricing/plan:
     *    - Join request assigned plan (`membership_plan_id` in notes)
     *    - Specific Circle Plan matching the circle (slug/name)
     * 2. Circle-specific price override (`circle_price_amount` > 0)
     * 3. Membership Plan with slug 'circle_peer'
     *
     * @return array{
     *     amount: float,
     *     currency: string,
     *     source: string,
     *     amount_in_paise: int,
     *     plan: ?MembershipPlan
     * }
     */
    public function resolve(?Circle $circle, ?CircleJoinRequest $joinRequest = null): array
    {
        // 1. Exact selected Circle pricing/plan
        // 1a. Explicit plan assigned to join request
        if ($joinRequest) {
            $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
            $planId = $notes['membership_plan_id'] ?? ($notes['circle_plan_id'] ?? null);
            if ($planId) {
                $assignedPlan = MembershipPlan::query()->find($planId);
                if ($assignedPlan && (float) $assignedPlan->price > 0 && $assignedPlan->is_active) {
                    $planPrice = (float) $assignedPlan->price;
                    $currency = strtoupper((string) config('razorpay.currency', 'INR'));

                    return [
                        'amount' => $planPrice,
                        'currency' => $currency,
                        'source' => $assignedPlan->slug === 'circle_peer' ? self::SOURCE_MEMBERSHIP_PLAN : self::SOURCE_CIRCLE_PLAN,
                        'amount_in_paise' => (int) round($planPrice * 100),
                        'plan' => $assignedPlan,
                    ];
                }
            }
        }

        // 1b. Specific active Circle Plan configured for the selected Circle
        if ($circle) {
            $slugCandidates = array_values(array_unique(array_filter([
                ! empty($circle->slug) ? 'circle_'.$circle->slug : null,
                ! empty($circle->slug) ? 'circle_'.str_replace('-', '_', $circle->slug) : null,
                ! empty($circle->slug) ? 'circle_'.str_replace('_', '-', $circle->slug) : null,
                $circle->slug ?: null,
                ! empty($circle->slug) ? str_replace('-', '_', $circle->slug) : null,
                ! empty($circle->name) ? 'circle_'.Str::slug((string) $circle->name, '_') : null,
                ! empty($circle->name) ? 'circle_'.Str::slug((string) $circle->name, '-') : null,
                ! empty($circle->name) ? Str::slug((string) $circle->name, '_') : null,
                ! empty($circle->name) ? Str::slug((string) $circle->name, '-') : null,
            ])));

            $query = MembershipPlan::query()
                ->circleOnly()
                ->where('is_active', true)
                ->where('slug', '!=', 'circle_peer')
                ->where(function ($q) use ($slugCandidates, $circle) {
                    if (! empty($slugCandidates)) {
                        $q->whereIn('slug', $slugCandidates);
                    }
                    if (! empty($circle->name)) {
                        $q->orWhere('name', $circle->name)
                            ->orWhere('name', 'ILIKE', '%'.$circle->name.'%');
                    }
                });

            if (! empty($slugCandidates)) {
                $escaped = array_map(fn ($s) => "'".addslashes($s)."'", $slugCandidates);
                $query->orderByRaw('CASE WHEN slug IN ('.implode(',', $escaped).') THEN 0 ELSE 1 END');
            }

            $circlePlan = $query->first();

            if ($circlePlan && (float) $circlePlan->price > 0) {
                $planPrice = (float) $circlePlan->price;
                $currency = strtoupper((string) config('razorpay.currency', 'INR'));

                return [
                    'amount' => $planPrice,
                    'currency' => $currency,
                    'source' => self::SOURCE_CIRCLE_PLAN,
                    'amount_in_paise' => (int) round($planPrice * 100),
                    'plan' => $circlePlan,
                ];
            }
        }

        // 2. Circle-specific price override
        $circlePrice = $circle && $circle->circle_price_amount !== null
            ? (float) $circle->circle_price_amount
            : null;

        if ($circlePrice !== null && $circlePrice > 0) {
            $currency = strtoupper((string) ($circle->circle_price_currency ?: config('razorpay.currency', 'INR')));

            return [
                'amount' => $circlePrice,
                'currency' => $currency,
                'source' => self::SOURCE_CIRCLE_OVERRIDE,
                'amount_in_paise' => (int) round($circlePrice * 100),
                'plan' => null,
            ];
        }

        // 3. Membership Plan fallback (slug = 'circle_peer')
        $plan = MembershipPlan::query()
            ->where('slug', 'circle_peer')
            ->first();

        if ($plan && (float) $plan->price > 0) {
            $planPrice = (float) $plan->price;
            $currency = strtoupper((string) config('razorpay.currency', 'INR'));

            return [
                'amount' => $planPrice,
                'currency' => $currency,
                'source' => self::SOURCE_MEMBERSHIP_PLAN,
                'amount_in_paise' => (int) round($planPrice * 100),
                'plan' => $plan,
            ];
        }

        throw ValidationException::withMessages([
            'circle_price' => 'Unable to resolve valid pricing for this Circle. Neither Circle-specific price nor Circle Peer plan price is configured.',
        ]);
    }
}
