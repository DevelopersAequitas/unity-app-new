<?php

namespace App\Console\Commands;

use App\Models\CircleMember;
use App\Models\CircleMemberSubscription;
use Illuminate\Console\Command;

class ExpireCircleSubscriptions extends Command
{
    protected $signature = 'circles:subscriptions-expire';

    protected $description = 'Expire circle member subscriptions whose end date has passed.';

    public function handle(): int
    {
        $subscriptions = CircleMemberSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);

            CircleMember::query()
                ->where('circle_id', $subscription->circle_id)
                ->where('user_id', $subscription->user_id)
                ->update(['status' => 'inactive']);
        }

        $this->info('Expired ' . $subscriptions->count() . ' circle subscription(s).');

        return self::SUCCESS;
    }
}
