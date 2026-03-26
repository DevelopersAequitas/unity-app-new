<?php

namespace App\Services\Circles;

use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaidCircleMembershipFinalizer
{
    public function finalize(
        User $user,
        CircleSubscription $subscription,
        ?Carbon $paidAt = null,
        ?Carbon $startedAt = null,
        ?Carbon $expiresAt = null
    ): CircleMember {
        $paidAt ??= $subscription->paid_at ?? now();
        $startedAt ??= $subscription->started_at ?? $paidAt;
        $expiresAt ??= $subscription->expires_at;

        $member = CircleMember::withTrashed()
            ->where('user_id', $user->id)
            ->where('circle_id', $subscription->circle_id)
            ->first();

        $updates = [
            'status' => $this->joinedStatus(),
            'role' => $member?->role ?: 'member',
            'joined_at' => $member?->joined_at ?: $startedAt,
            'left_at' => null,
        ];

        if (Schema::hasColumn('circle_members', 'joined_via')) {
            $updates['joined_via'] = 'payment';
        }
        if (Schema::hasColumn('circle_members', 'joined_via_payment')) {
            $updates['joined_via_payment'] = true;
        }
        if (Schema::hasColumn('circle_members', 'payment_status')) {
            $updates['payment_status'] = 'paid';
        }
        if (Schema::hasColumn('circle_members', 'paid_at')) {
            $updates['paid_at'] = $paidAt;
        }
        if (Schema::hasColumn('circle_members', 'paid_starts_at')) {
            $updates['paid_starts_at'] = $startedAt;
        }
        if (Schema::hasColumn('circle_members', 'paid_ends_at')) {
            $updates['paid_ends_at'] = $expiresAt;
        }
        if (Schema::hasColumn('circle_members', 'billing_term')) {
            $updates['billing_term'] = 'yearly';
        }
        if (Schema::hasColumn('circle_members', 'zoho_subscription_id')) {
            $updates['zoho_subscription_id'] = $subscription->zoho_subscription_id;
        }
        if (Schema::hasColumn('circle_members', 'zoho_addon_code')) {
            $updates['zoho_addon_code'] = $subscription->zoho_addon_code;
        }
        if (Schema::hasColumn('circle_members', 'meta')) {
            $updates['meta'] = array_filter([
                'circle_subscription_id' => $subscription->id,
                'zoho_payment_id' => $subscription->zoho_payment_id,
                'zoho_hosted_page_id' => $subscription->zoho_hosted_page_id,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $action = 'created';
        if ($member) {
            if ($member->trashed()) {
                $member->restore();
            }
            $member->forceFill($updates)->save();
            $action = 'updated';
        } else {
            $member = CircleMember::query()->create(array_merge($updates, [
                'user_id' => $user->id,
                'circle_id' => $subscription->circle_id,
            ]));
        }

        Log::info('circle membership finalized from payment', [
            'action' => $action,
            'user_id' => $user->id,
            'circle_id' => $subscription->circle_id,
            'circle_subscription_id' => $subscription->id,
        ]);

        return $member;
    }

    private function joinedStatus(): string
    {
        return (string) config('circle.member_joined_status', 'approved');
    }
}

