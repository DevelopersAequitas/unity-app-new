<?php

namespace App\Console\Commands;

use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\Membership\MembershipUpgradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipsExpire extends Command
{
    protected $signature = 'memberships:expire';

    protected $description = 'Expire memberships, promote queued plans, and downgrade users when needed.';

    public function handle(): int
    {
        $now = now();

        $expiredMemberships = UserMembership::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        if ($expiredMemberships->isNotEmpty()) {
            $membershipIds = $expiredMemberships->pluck('id')->all();
            UserMembership::query()
                ->whereIn('id', $membershipIds)
                ->update(['status' => 'expired']);
        }

        // Activate queued memberships that have now reached their start date
        $queuedToActivate = UserMembership::query()
            ->where('status', 'queued')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get();

        foreach ($queuedToActivate as $queued) {
            $queued->update(['status' => 'active']);
        }

        $affectedUserIds = $expiredMemberships->pluck('user_id')
            ->merge($queuedToActivate->pluck('user_id'))
            ->unique()
            ->values()
            ->all();

        if ($affectedUserIds === []) {
            $this->info('No memberships to expire or promote.');

            return self::SUCCESS;
        }

        foreach ($affectedUserIds as $userId) {
            DB::transaction(function () use ($userId, $now): void {
                $hasActiveMembership = UserMembership::query()
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(function ($query) use ($now) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $now);
                    })
                    ->first();

                if ($hasActiveMembership) {
                    $furthestEnd = UserMembership::query()
                        ->where('user_id', $userId)
                        ->whereIn('status', ['active', 'queued'])
                        ->where('ends_at', '>=', $now)
                        ->max('ends_at');

                    User::query()->where('id', $userId)->update([
                        'membership_status' => MembershipUpgradeService::ONLY_GREEN_PEER_STATUS,
                        'membership_ends_at' => $furthestEnd ?? $hasActiveMembership->ends_at,
                        'membership_expiry' => $furthestEnd ?? $hasActiveMembership->ends_at,
                    ]);

                    return;
                }

                $furthestExpiredEnd = UserMembership::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('ends_at')
                    ->max('ends_at');

                User::query()->where('id', $userId)->update([
                    'membership_status' => 'free_peer',
                    'membership_ends_at' => $furthestExpiredEnd,
                    'membership_expiry' => $furthestExpiredEnd,
                ]);

                try {
                    $memberUser = User::query()->find($userId);
                    $circleId = (string) ($memberUser?->active_circle_id ?? $memberUser?->circle_id ?? '');
                    if ($circleId !== '') {
                        $peerName = trim((string) ($memberUser?->display_name ?? $memberUser?->name ?? 'Peer'));
                        app(LeaderNotificationDispatcherService::class)->dispatchEvent(
                            'peer_membership_expired',
                            'Peer Membership Expired',
                            "{$peerName}'s membership has expired and reverted to free peer.",
                            [
                                'circle_id' => $circleId,
                                'actor_id' => (string) $userId,
                                'entity_type' => 'peer',
                                'entity_id' => (string) $userId,
                                'reference_id' => (string) $userId,
                            ]
                        );
                    }
                } catch (\Throwable $le) {
                    Log::warning('Failed dispatching leader peer_membership_expired', ['error' => $le->getMessage()]);
                }
            });
        }

        Log::info('Expired memberships and promotions processed', [
            'expired_count' => $expiredMemberships->count(),
            'promoted_count' => $queuedToActivate->count(),
            'affected_users' => count($affectedUserIds),
        ]);

        $this->info('Expired memberships and promotions processed.');

        return self::SUCCESS;
    }
}
