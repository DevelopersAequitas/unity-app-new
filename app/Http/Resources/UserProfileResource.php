<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Models\UserMembership;
use App\Services\Billing\MembershipSyncService;
use App\Services\Membership\MembershipUpgradeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class UserProfileResource extends MemberDetailResource
{
    /**
     * Transform the resource into an array with duplicate fields removed.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        // Ensure introducer ID is preserved in introduced_by_user before unsetting introduced_by
        if (empty($data['introduced_by_user']) && ! empty($data['introduced_by'])) {
            $data['introduced_by_user'] = [
                'id' => (string) $data['introduced_by'],
                'name' => null,
                'profile_photo_url' => null,
            ];
        }

        // Ensure business_website has fallback before unsetting website & other_website
        if (empty($data['business_website'])) {
            $data['business_website'] = $this->business_website ?? $data['website'] ?? $this->other_website ?? null;
        }

        // Remove all duplicate and redundant fields
        unset(
            // Count aliases (duplicates of badges_count, p2p_meetings_count, business_deals_count)
            $data['my_badges_count'],
            $data['p2p_count'],
            $data['deals_count'],

            // Profile video duplicate (profile_video_id & profile_video_url are canonical)
            $data['profile_video'],

            // Introducer UUID (already present inside introduced_by_user.id)
            $data['introduced_by'],

            // Active circle flat fields (already present in active_circle and circle_memberships)
            $data['active_circle_id'],
            $data['active_circle_addon_code'],
            $data['active_circle_addon_name'],
            $data['circle_joined_at'],
            $data['circle_expires_at'],
            $data['active_circle_subscription_id'],

            // Category duplicates (already present in business_category & main_business_category)
            $data['business_sub_category'],
            $data['main_business_category_id'],
            $data['business_category_id'],

            // Website duplicates (already present in business_website)
            $data['website'],
            $data['other_website'],

            // Social links object (duplicates flat fields linkedin_profile, instagram_handle, etc.)
            $data['social_links'],

            // Categories field (category path is now properly inside circle_memberships)
            $data['categories']
        );

        // Attach multi-period membership breakdown (active, upcoming queued, and expired)
        $membershipBreakdown = $this->resolveMembershipBreakdown();

        $data['active_membership'] = $membershipBreakdown['active_membership'];
        $data['upcoming_memberships'] = $membershipBreakdown['upcoming_memberships'];
        $data['expired_memberships'] = $membershipBreakdown['expired_memberships'];
        $data['total_membership_valid_until'] = $membershipBreakdown['total_valid_until'];
        $data['total_membership_days_remaining'] = $membershipBreakdown['days_remaining'];

        if ($membershipBreakdown['total_valid_until']) {
            $data['membership_ends_at'] = $membershipBreakdown['total_valid_until'];
        }

        return $data;
    }

    /**
     * Resolve active, upcoming queued, and expired membership periods.
     *
     * @return array<string, mixed>
     */
    private function resolveMembershipBreakdown(): array
    {
        $now = now();
        $user = $this->resource;

        if ($user instanceof User) {
            $this->autoPromoteQueuedMemberships($user, $now);
            try {
                app(MembershipSyncService::class)->ensureUserMembershipsSynced($user);
            } catch (Throwable) {
                // Non-blocking sync
            }
        }

        if (! Schema::hasTable('user_memberships')) {
            $endsAt = $user->membership_ends_at ? Carbon::parse((string) $user->membership_ends_at) : null;
            $startsAt = $user->membership_starts_at ? Carbon::parse((string) $user->membership_starts_at) : null;
            $isActive = $endsAt && $endsAt->isFuture();

            $activePlan = null;
            if ($isActive) {
                $duration = $this->formatMembershipDuration($startsAt, $endsAt);
                $activePlan = [
                    'id' => null,
                    'plan_name' => $user->zoho_plan_code ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan'),
                    'duration' => $duration,
                    'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                    'ends_at' => $endsAt->toIso8601String(),
                    'status' => 'active',
                    'days_left' => max(0, (int) ceil($now->floatDiffInDays($endsAt, false))),
                    'payment_id' => null,
                ];
            }

            return [
                'total_valid_until' => ($endsAt && $endsAt->isFuture()) ? $endsAt->toIso8601String() : null,
                'days_remaining' => ($endsAt && $endsAt->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($endsAt, false))) : 0,
                'active_membership' => $activePlan,
                'upcoming_memberships' => [],
                'expired_memberships' => [],
            ];
        }

        $memberships = UserMembership::query()
            ->with(['plan'])
            ->where('user_id', $user->id)
            ->orderBy('starts_at', 'asc')
            ->get();

        $active = null;
        $upcoming = [];
        $expired = [];

        foreach ($memberships as $membership) {
            $startsAt = $membership->starts_at;
            $endsAt = $membership->ends_at;
            $duration = $this->formatMembershipDuration($startsAt, $endsAt, $membership->plan?->duration_months);
            $planName = $membership->plan?->name ?? $user->zoho_plan_code ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan');

            $card = [
                'id' => (string) $membership->id,
                'plan_name' => $planName,
                'duration' => $duration,
                'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
                'status' => (string) $membership->status,
                'days_left' => ($endsAt && $endsAt->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($endsAt, false))) : 0,
                'payment_id' => $membership->payment_id ? (string) $membership->payment_id : null,
            ];

            if ($membership->status === 'active' && (! $endsAt || $endsAt->gte($now))) {
                if ($active === null) {
                    $card['status'] = 'active';
                    $active = $card;
                } else {
                    $card['status'] = 'queued';
                    $upcoming[] = $card;
                }
            } elseif ($membership->status === 'queued' || ($startsAt && $startsAt->gt($now))) {
                $card['status'] = 'queued';
                $upcoming[] = $card;
            } elseif ($membership->status === 'expired' || ($endsAt && $endsAt->lt($now))) {
                $card['status'] = 'expired';
                $card['days_left'] = 0;
                $expired[] = $card;
            }
        }

        // Fallback for active plan if user_memberships rows are empty but user has active dates
        if ($active === null && $user->membership_ends_at && Carbon::parse((string) $user->membership_ends_at)->isFuture()) {
            $startsAt = $user->membership_starts_at ? Carbon::parse((string) $user->membership_starts_at) : null;
            $endsAt = Carbon::parse((string) $user->membership_ends_at);
            $duration = $this->formatMembershipDuration($startsAt, $endsAt);

            $createdMembership = app(MembershipUpgradeService::class)->syncUserMembershipRow(
                $user,
                null,
                $startsAt ?? $now,
                $endsAt,
                ['zoho_plan_code' => $user->zoho_plan_code],
                'active'
            );

            $active = [
                'id' => $createdMembership?->id ? (string) $createdMembership->id : (string) Str::uuid(),
                'plan_name' => $user->zoho_plan_code ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan'),
                'duration' => $duration,
                'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                'ends_at' => $endsAt->toIso8601String(),
                'status' => 'active',
                'days_left' => max(0, (int) ceil($now->floatDiffInDays($endsAt, false))),
                'payment_id' => $createdMembership?->payment_id ? (string) $createdMembership->payment_id : null,
            ];
        }

        // Fallback for expired plan if user has past membership dates but user_memberships has no expired rows
        if (empty($expired) && $user->membership_ends_at && Carbon::parse((string) $user->membership_ends_at)->isPast()) {
            $startsAt = $user->membership_starts_at ? Carbon::parse((string) $user->membership_starts_at) : null;
            $endsAt = Carbon::parse((string) $user->membership_ends_at);
            $duration = $this->formatMembershipDuration($startsAt, $endsAt);

            $createdMembership = app(MembershipUpgradeService::class)->syncUserMembershipRow(
                $user,
                null,
                $startsAt ?? $endsAt->copy()->subMonth(),
                $endsAt,
                ['zoho_plan_code' => $user->zoho_plan_code],
                'expired'
            );

            $expired[] = [
                'id' => $createdMembership?->id ? (string) $createdMembership->id : (string) Str::uuid(),
                'plan_name' => $user->zoho_plan_code ?? ($duration ? "Pro Plan ({$duration})" : 'Pro Plan'),
                'duration' => $duration,
                'starts_at' => $startsAt ? $startsAt->toIso8601String() : null,
                'ends_at' => $endsAt->toIso8601String(),
                'status' => 'expired',
                'days_left' => 0,
                'payment_id' => $createdMembership?->payment_id ? (string) $createdMembership->payment_id : null,
            ];
        }

        usort($upcoming, fn (array $a, array $b): int => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? '')));
        usort($expired, fn (array $a, array $b): int => strcmp((string) ($b['ends_at'] ?? ''), (string) ($a['ends_at'] ?? '')));

        $userEndsAt = $user->membership_ends_at ? Carbon::parse((string) $user->membership_ends_at) : null;
        $furthestEnd = $userEndsAt;

        if (! empty($upcoming)) {
            $lastUpcoming = end($upcoming);
            if (! empty($lastUpcoming['ends_at'])) {
                $upcomingEnd = Carbon::parse($lastUpcoming['ends_at']);
                if (! $furthestEnd || $upcomingEnd->gt($furthestEnd)) {
                    $furthestEnd = $upcomingEnd;
                }
            }
        }

        if (! $furthestEnd && $active && ! empty($active['ends_at'])) {
            $furthestEnd = Carbon::parse($active['ends_at']);
        }

        $totalValidUntil = ($furthestEnd && $furthestEnd->isFuture()) ? $furthestEnd->toIso8601String() : null;
        $daysRemaining = ($furthestEnd && $furthestEnd->isFuture()) ? max(0, (int) ceil($now->floatDiffInDays($furthestEnd, false))) : 0;

        return [
            'total_valid_until' => $totalValidUntil,
            'days_remaining' => $daysRemaining,
            'active_membership' => $active,
            'upcoming_memberships' => $upcoming,
            'expired_memberships' => $expired,
        ];
    }

    private function autoPromoteQueuedMemberships(User $user, Carbon $now): void
    {
        if (! Schema::hasTable('user_memberships')) {
            return;
        }

        try {
            UserMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now)
                ->update(['status' => 'expired']);

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

    private function formatMembershipDuration(?Carbon $startsAt, ?Carbon $endsAt, ?int $durationMonths = null): ?string
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
