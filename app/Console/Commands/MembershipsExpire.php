<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserMembership;
use App\Services\Membership\MembershipLifecycleNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipsExpire extends Command
{
    protected $signature = 'memberships:expire';
    protected $description = 'Expire memberships and downgrade users when needed.';

    public function handle(): int
    {
        $now = now();

        $expiredMemberships = UserMembership::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        if ($expiredMemberships->isEmpty()) {
            $this->info('No memberships to expire.');

            return self::SUCCESS;
        }

        $membershipIds = $expiredMemberships->pluck('id')->all();
        $userIds = $expiredMemberships->pluck('user_id')->unique()->values()->all();

        UserMembership::query()
            ->whereIn('id', $membershipIds)
            ->update(['status' => 'expired']);

        $notifications = app(MembershipLifecycleNotificationService::class);

        foreach ($userIds as $userId) {
            $expiredUser = DB::transaction(function () use ($userId, $now): ?User {
                $hasActiveMembership = UserMembership::query()
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(function ($query) use ($now) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $now);
                    })
                    ->exists();

                if ($hasActiveMembership) {
                    return null;
                }

                $user = User::query()->find($userId);

                if (! $user) {
                    return null;
                }

                $user->forceFill([
                    'membership_status' => 'free_peer',
                    'membership_ends_at' => null,
                    'membership_expiry' => null,
                ])->save();

                return $user;
            });

            if ($expiredUser) {
                $notifications->sendExpired($expiredUser);
            }
        }

        Log::info('Expired memberships processed', [
            'count' => count($membershipIds),
        ]);

        $this->info('Expired memberships processed.');

        return self::SUCCESS;
    }
}
