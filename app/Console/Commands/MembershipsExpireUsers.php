<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MembershipsExpireUsers extends Command
{
    protected $signature = 'memberships:expire-users';

    protected $description = 'Normalize expired users to Free Peer membership status.';

    public function handle(): int
    {
        $freePeerStatus = User::freePeerMembershipStatus();

        $expiryColumn = Schema::hasColumn('users', 'membership_ends_at')
            ? 'membership_ends_at'
            : (Schema::hasColumn('users', 'membership_expiry') ? 'membership_expiry' : null);

        if (! $expiryColumn) {
            $this->info('No membership expiry column found on users table.');

            return self::SUCCESS;
        }

        $updated = User::query()
            ->whereNotNull($expiryColumn)
            ->where($expiryColumn, '<', now())
            ->where('membership_status', '!=', $freePeerStatus)
            ->update([
                'membership_status' => $freePeerStatus,
            ]);

        $this->info("Expired users normalized: {$updated}");

        return self::SUCCESS;
    }
}
