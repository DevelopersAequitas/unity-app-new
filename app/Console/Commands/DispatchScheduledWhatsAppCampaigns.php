<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppBulkCampaignJob;
use App\Models\WhatsAppBulkCampaign;
use App\Models\WhatsAppBulkCampaignLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DispatchScheduledWhatsAppCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:dispatch-scheduled-campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch WhatsApp bulk campaigns that have reached their scheduled time.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueCampaigns = WhatsAppBulkCampaign::where('status', 'scheduled')
            ->where('schedule_type', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($dueCampaigns->isEmpty()) {
            $this->info('No scheduled campaigns due for dispatch.');

            return self::SUCCESS;
        }

        $this->info("Found {$dueCampaigns->count()} campaign(s) due for dispatch.");

        foreach ($dueCampaigns as $campaign) {
            $this->info("Dispatching scheduled campaign: {$campaign->name} ({$campaign->id})");

            $campaign->update([
                'status' => 'queued',
            ]);

            WhatsAppBulkCampaignLog::create([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'event_type' => 'SCHEDULED_DISPATCH',
                'message' => "Campaign reached scheduled time ({$campaign->scheduled_at}) and dispatched to queue.",
                'created_at' => now(),
            ]);

            ProcessWhatsAppBulkCampaignJob::dispatch($campaign->id);
        }

        return self::SUCCESS;
    }
}
