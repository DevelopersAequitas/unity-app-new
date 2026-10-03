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

    public const SOURCE_ZOHO_ADDON = 'zoho_circle_addon';

    /**
     * Resolve the price for a Circle and/or CircleJoinRequest.
     *
     * @return array{
     *     amount: float,
     *     currency: string,
     *     source: string,
     *     amount_in_paise: int,
     *     plan: ?MembershipPlan,
     *     gateway: string
     * }
     */
    public function resolve(?Circle $circle, ?CircleJoinRequest $joinRequest = null): array
    {
        // 1. If Circle is explicitly configured for Zoho (or legacy Zoho circle)
        if ($circle && $circle->isZoho()) {
            $circlePrice = $circle->circle_price_amount !== null ? (float) $circle->circle_price_amount : 5000.00;
            $currency = strtoupper((string) ($circle->circle_price_currency ?: 'INR'));
            $gstPercent = (float) ($circle->circle_gst_percent ?: 18.00);
            $totalAmount = round($circlePrice + round($circlePrice * ($gstPercent / 100), 2), 2);

            return [
                'amount' => $circlePrice,
                'currency' => $currency,
                'source' => self::SOURCE_ZOHO_ADDON,
                'amount_in_paise' => (int) round($totalAmount * 100),
                'plan' => null,
                'gateway' => 'zoho',
            ];
        }

        // 2. Razorpay Circle: Check explicit payment_plan_id configured on Circle
        if ($circle && ! empty($circle->payment_plan_id)) {
            $planIdStr = trim((string) $circle->payment_plan_id);
            $assignedPlan = Str::isUuid($planIdStr)
                ? MembershipPlan::query()->find($planIdStr)
                : MembershipPlan::query()->where('slug', $planIdStr)->first();

            if ($assignedPlan && (float) $assignedPlan->price >= 0 && $assignedPlan->is_active) {
                $planPrice = (float) $assignedPlan->price;
                $currency = strtoupper((string) config('razorpay.currency', 'INR'));
                $gstPercent = (float) ($assignedPlan->gst_percent ?: 18.00);
                $totalAmount = round($planPrice + round($planPrice * ($gstPercent / 100), 2), 2);

                return [
                    'amount' => $planPrice,
                    'currency' => $currency,
                    'source' => self::SOURCE_CIRCLE_PLAN,
                    'amount_in_paise' => (int) round($totalAmount * 100),
                    'plan' => $assignedPlan,
                    'gateway' => 'razorpay',
                ];
            }
        }

        // 3. Explicit plan assigned to join request
        if ($joinRequest) {
            $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
            $planId = $notes['membership_plan_id'] ?? ($notes['circle_plan_id'] ?? null);
            if ($planId) {
                $planIdStr = trim((string) $planId);
                $assignedPlan = Str::isUuid($planIdStr)
                    ? MembershipPlan::query()->find($planIdStr)
                    : MembershipPlan::query()->where('slug', $planIdStr)->first();
                if ($assignedPlan && (float) $assignedPlan->price >= 0 && $assignedPlan->is_active) {
                    $planPrice = (float) $assignedPlan->price;
                    $currency = strtoupper((string) config('razorpay.currency', 'INR'));
                    $gstPercent = (float) ($assignedPlan->gst_percent ?: 18.00);
                    $totalAmount = round($planPrice + round($planPrice * ($gstPercent / 100), 2), 2);

                    return [
                        'amount' => $planPrice,
                        'currency' => $currency,
                        'source' => $assignedPlan->slug === 'circle_peer' ? self::SOURCE_MEMBERSHIP_PLAN : self::SOURCE_CIRCLE_PLAN,
                        'amount_in_paise' => (int) round($totalAmount * 100),
                        'plan' => $assignedPlan,
                        'gateway' => 'razorpay',
                    ];
                }
            }
        }

        // 4. Specific active Circle Plan matching the selected Circle (slug or name)
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

            if ($circlePlan && (float) $circlePlan->price >= 0) {
                $planPrice = (float) $circlePlan->price;
                $currency = strtoupper((string) config('razorpay.currency', 'INR'));
                $gstPercent = (float) ($circlePlan->gst_percent ?: 18.00);
                $totalAmount = round($planPrice + round($planPrice * ($gstPercent / 100), 2), 2);

                return [
                    'amount' => $planPrice,
                    'currency' => $currency,
                    'source' => self::SOURCE_CIRCLE_PLAN,
                    'amount_in_paise' => (int) round($totalAmount * 100),
                    'plan' => $circlePlan,
                    'gateway' => 'razorpay',
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
                'gateway' => 'razorpay',
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
                'gateway' => 'razorpay',
            ];
        }

        throw ValidationException::withMessages([
            'circle_price' => 'Unable to resolve valid pricing for this Circle. Neither Circle-specific price nor Circle Peer plan price is configured.',
        ]);
    }
}
