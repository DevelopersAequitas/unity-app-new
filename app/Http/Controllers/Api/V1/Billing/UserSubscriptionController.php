<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class UserSubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        if ($user instanceof User) {
            $this->autoPromoteQueuedMemberships($user, $now);
        }

        $items = collect();
        $membershipCards = collect();

        // 1. Fetch base membership subscriptions/payments
        if (Schema::hasTable('user_memberships')) {
            $memberships = UserMembership::query()
                ->with(['plan', 'payment'])
                ->where('user_id', $user->id)
                ->orderBy('starts_at', 'asc')
                ->get();

            foreach ($memberships as $membership) {
                $startsAt = $membership->starts_at;
                $endsAt = $membership->ends_at;

                $duration = $this->formatDuration($startsAt, $endsAt, $membership->plan?->duration_months);
                $planName = $membership->plan?->name ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan');

                $card = [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'membership_plan_id' => $membership->membership_plan_id,
                    'plan_name' => $planName,
                    'duration' => $duration,
                    'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                    'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
                    'status' => $membership->status,
                    'days_left' => ($endsAt && $endsAt->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($endsAt, false))) : 0,
                    'payment_id' => $membership->payment_id,
                    'membership_plan' => $membership->plan ? [
                        'id' => $membership->plan->id,
                        'name' => $membership->plan->name,
                    ] : null,
                    'payment' => $membership->payment ? [
                        'id' => $membership->payment->id,
                        'status' => $membership->payment->status,
                    ] : null,
                    'circle_payment' => 'no',
                    'is_circle_payment' => false,
                ];

                $membershipCards->push($card);
                $items->push($card);
            }
        } else {
            // Fallback to payments table if user_memberships doesn't exist
            $payments = Payment::query()
                ->with('plan')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($payments as $payment) {
                $startsAt = $payment->paid_at ?? $payment->created_at;
                $endsAt = $payment->paid_at ? $payment->paid_at->copy()->addYear() : null;

                $duration = $this->formatDuration($startsAt, $endsAt, $payment->plan?->duration_months);
                $planName = $payment->plan?->name ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan');
                $status = in_array($payment->status, ['paid', 'success'], true) ? 'active' : 'inactive';

                $card = [
                    'id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'membership_plan_id' => $payment->membership_plan_id,
                    'plan_name' => $planName,
                    'duration' => $duration,
                    'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                    'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
                    'status' => $status,
                    'days_left' => ($endsAt && $endsAt->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($endsAt, false))) : 0,
                    'payment_id' => $payment->razorpay_payment_id ?? $payment->id,
                    'membership_plan' => $payment->plan ? [
                        'id' => $payment->plan->id,
                        'name' => $payment->plan->name,
                    ] : null,
                    'payment' => [
                        'id' => $payment->id,
                        'status' => $payment->status,
                    ],
                    'circle_payment' => 'no',
                    'is_circle_payment' => false,
                ];

                $membershipCards->push($card);
                $items->push($card);
            }
        }

        // 2. Fetch circle subscriptions
        $circleSubscriptions = CircleSubscription::query()
            ->with('circle')
            ->where('user_id', $user->id)
            ->get();

        foreach ($circleSubscriptions as $sub) {
            $startsAt = $sub->paid_at ?? $sub->started_at ?? $sub->created_at;
            $endsAt = $sub->expires_at;

            $circleName = $sub->circle?->name;
            $addonName = $sub->zoho_addon_name;
            $description = 'Circle Subscription';
            if ($circleName && $addonName) {
                $description = "{$circleName} ({$addonName})";
            } elseif ($circleName) {
                $description = "Subscription for {$circleName}";
            } elseif ($addonName) {
                $description = "Circle Subscription - {$addonName}";
            }

            $items->push([
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'membership_plan_id' => $sub->zoho_addon_id,
                'plan_name' => $description,
                'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
                'status' => $sub->status,
                'payment_id' => $sub->zoho_payment_id ?? $sub->zoho_invoice_id ?? $sub->id,
                'membership_plan' => [
                    'id' => $sub->zoho_addon_id ?? $sub->circle_id,
                    'name' => $description,
                ],
                'payment' => [
                    'id' => $sub->zoho_payment_id ?? $sub->zoho_invoice_id ?? $sub->id,
                    'status' => in_array(strtolower((string) $sub->status), ['paid', 'success', 'completed', 'active'], true) ? 'paid' : $sub->status,
                ],
                'circle_payment' => 'yes',
                'is_circle_payment' => true,
            ]);
        }

        // 3. Categorize membership plans
        $currentPlan = $membershipCards
            ->first(fn (array $c): bool => $c['status'] === 'active' && (! $c['ends_at'] || Carbon::parse($c['ends_at'])->gte($now)));

        $upcomingPlans = $membershipCards
            ->filter(fn (array $c): bool => $c['status'] === 'queued' || ($c['starts_at'] && Carbon::parse($c['starts_at'])->gt($now)))
            ->values()
            ->all();

        $expiredPlans = $membershipCards
            ->filter(fn (array $c): bool => $c['status'] === 'expired' || ($c['ends_at'] && Carbon::parse($c['ends_at'])->lt($now)))
            ->sortByDesc('ends_at')
            ->values()
            ->all();

        // 4. Calculate total validity metrics
        $userEndsAt = $user->membership_ends_at ? Carbon::parse((string) $user->membership_ends_at) : null;
        $furthestMembershipEnd = $membershipCards
            ->filter(fn (array $c): bool => in_array($c['status'], ['active', 'queued'], true) && $c['ends_at'] && Carbon::parse($c['ends_at'])->isFuture())
            ->map(fn (array $c): Carbon => Carbon::parse($c['ends_at']))
            ->max();

        $finalEnd = ($userEndsAt && $furthestMembershipEnd)
            ? ($userEndsAt->gt($furthestMembershipEnd) ? $userEndsAt : $furthestMembershipEnd)
            : ($furthestMembershipEnd ?? $userEndsAt);

        $totalValidUntil = ($finalEnd && $finalEnd->isFuture()) ? $finalEnd->toIso8601String() : null;
        $totalDaysRemaining = ($finalEnd && $finalEnd->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($finalEnd, false))) : 0;

        // 5. Complete flat list sorted by starts_at descending for backward compatibility
        $sortedItems = $items->sortByDesc('starts_at')->values()->all();

        return $this->success([
            'total_valid_until' => $totalValidUntil,
            'days_remaining' => $totalDaysRemaining,
            'current_plan' => $currentPlan,
            'upcoming_plans' => $upcomingPlans,
            'expired_plans' => $expiredPlans,
            'subscriptions' => $sortedItems,
        ], 'User subscriptions fetched successfully.');
    }

    private function autoPromoteQueuedMemberships(User $user, Carbon $now): void
    {
        if (! Schema::hasTable('user_memberships')) {
            return;
        }

        try {
            // Expire past memberships
            UserMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now)
                ->update(['status' => 'expired']);

            // Check if user has an active membership currently running
            $hasActive = UserMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->exists();

            if (! $hasActive) {
                $nextQueued = UserMembership::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'queued')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)
                    ->orderBy('starts_at')
                    ->first();

                if ($nextQueued) {
                    $nextQueued->update(['status' => 'active']);
                }
            }
        } catch (Throwable) {
            // Non-blocking auto-promotion
        }
    }

    private function formatDuration(?Carbon $startsAt, ?Carbon $endsAt, ?int $durationMonths = null): ?string
    {
        if ($durationMonths && $durationMonths > 0) {
            if ($durationMonths === 1) {
                return '1 Month';
            }
            if ($durationMonths === 12) {
                return '1 Year';
            }
            if ($durationMonths === 24) {
                return '2 Years';
            }
            if ($durationMonths % 12 === 0) {
                return ($durationMonths / 12).' Years';
            }

            return $durationMonths.' Months';
        }

        if (! $startsAt || ! $endsAt) {
            return null;
        }

        $months = (int) round($startsAt->diffInMonths($endsAt));
        if ($months === 1) {
            return '1 Month';
        }
        if ($months === 12) {
            return '1 Year';
        }
        if ($months === 24) {
            return '2 Years';
        }
        if ($months > 12 && $months % 12 === 0) {
            return ($months / 12).' Years';
        }
        if ($months > 0) {
            return $months.' Months';
        }

        $days = (int) ceil($startsAt->diffInDays($endsAt));

        return $days.' Days';
    }
}
