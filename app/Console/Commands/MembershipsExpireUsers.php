<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Membership\MembershipLifecycleNotificationService;
use Illuminate\Console\Command;

class MembershipsExpireUsers extends Command
{
    protected $signature = 'memberships:expire-users';

    protected $description = 'Normalize expired users to Free Peer membership status.';

    public function handle(): int
    {
        $freePeerStatus = User::freePeerMembershipStatus();

        $expiredUsers = User::query()
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<', now())
            ->where('membership_status', '!=', $freePeerStatus)
            ->get();

        $updated = 0;
        $notifications = app(MembershipLifecycleNotificationService::class);

        foreach ($expiredUsers as $user) {
            $user->forceFill([
                'membership_status' => $freePeerStatus,
            ])->save();

            $notifications->sendExpired($user->refresh());
            $updated++;
        }

        $this->info("Expired users normalized: {$updated}");

        return self::SUCCESS;
    }
}
