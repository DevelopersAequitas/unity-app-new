<?php

namespace App\Console\Commands;

use App\Services\LifeImpact\LifeImpactBackfillService;
use Illuminate\Console\Command;

class LifeImpactBackfillCommand extends Command
{
    protected $signature = 'life-impact:backfill';

    protected $description = 'Backfill life impact histories for legacy business deals, referrals, and testimonials.';

    public function handle(LifeImpactBackfillService $backfillService): int
    {
        $this->info('Starting life impact backfill...');

        $stats = $backfillService->run();

        $this->newLine();
        $this->info('Life impact backfill completed.');
        $this->line('Business deal impacts inserted: ' . (int) ($stats['business_deals_inserted'] ?? 0));
        $this->line('Referral impacts inserted: ' . (int) ($stats['referrals_inserted'] ?? 0));
        $this->line('Testimonial impacts inserted: ' . (int) ($stats['testimonials_inserted'] ?? 0));
        $this->line('Skipped as duplicate: ' . (int) ($stats['duplicates_skipped'] ?? 0));
        $this->line('Users total count recalculated: ' . (int) ($stats['users_recalculated'] ?? 0));

        return self::SUCCESS;
    }
}
