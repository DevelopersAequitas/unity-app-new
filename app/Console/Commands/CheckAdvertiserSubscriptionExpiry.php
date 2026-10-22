<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserNotificationService;
use App\Services\Advertiser\AdvertiserPublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAdvertiserSubscriptionExpiry extends Command
{
    protected $signature = 'PGU:advertiser-subscription-expiry-check';

    protected $description = 'Check and process expired advertiser subscriptions and dispatch expiry reminders';

    public function handle(
        AdvertiserPublicationService $publicationService,
        AdvertiserNotificationService $notificationService
    ): int {
        $this->info('Starting Advertiser Subscription Expiry Check...');

        // 1. Process expired subscriptions
        $expiredSubs = AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $expiredCount = 0;
        foreach ($expiredSubs as $sub) {
            $publicationService->expireSubscription($sub);
            $expiredCount++;
            Log::info("[AdvertiserExpiry] Subscription {$sub->id} marked as expired.");
        }
        $this->info("Processed {$expiredCount} expired subscriptions.");

        // 2. Dispatch 3-day reminders
        $reminderWindowStart = now()->addDays(3)->startOfDay();
        $reminderWindowEnd = now()->addDays(3)->endOfDay();

        $remindSubs = AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$reminderWindowStart, $reminderWindowEnd])
            ->get();

        $reminderCount = 0;
        foreach ($remindSubs as $sub) {
            $notificationService->sendExpiryReminder($sub, 3);
            $reminderCount++;
        }
        $this->info("Dispatched {$reminderCount} expiry reminders.");

        return self::SUCCESS;
    }
}
