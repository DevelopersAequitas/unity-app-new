<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WhatsAppBulkCampaign;
use App\Models\WhatsAppBulkCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppBulkCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public string $campaignId,
        public int $batchSize = 50
    ) {}

    public function handle(): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        $campaign = WhatsAppBulkCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::error("[ProcessWhatsAppBulkCampaignJob] Campaign {$this->campaignId} not found.");

            return;
        }

        // If cancelled or already finalized, abort
        if (in_array($campaign->status, ['cancelled', 'completed', 'completed_with_errors'], true)) {
            Log::info("[ProcessWhatsAppBulkCampaignJob] Campaign {$this->campaignId} is in status {$campaign->status}. Stopping.");

            return;
        }

        // Mark as processing if queued or scheduled
        if (in_array($campaign->status, ['queued', 'scheduled'], true)) {
            $campaign->update([
                'status' => 'processing',
                'started_at' => $campaign->started_at ?? now(),
            ]);
        }

        // Fetch next chunk of pending recipients
        $pendingRecipients = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('csv_row_number', 'asc')
            ->limit($this->batchSize)
            ->get();

        if ($pendingRecipients->isEmpty()) {
            // Check if there are any still in 'processing' status
            $processingCount = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'processing')
                ->count();

            if ($processingCount > 0) {
                // Wait briefly and recheck
                self::dispatch($campaign->id, $this->batchSize)->delay(now()->addSeconds(5));

                return;
            }

            // No pending and no processing left -> campaign complete
            $hasFailures = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->exists();

            $finalStatus = $hasFailures ? 'completed_with_errors' : 'completed';
            $campaign->update([
                'status' => $finalStatus,
                'completed_at' => now(),
            ]);

            Log::info("[ProcessWhatsAppBulkCampaignJob] Campaign {$campaign->id} completed with status: {$finalStatus}.");

            return;
        }

        // Dispatch individual recipient jobs with a slight staggered delay to respect Meta API rate limits
        $delayMs = 0;
        foreach ($pendingRecipients as $recipient) {
            // Atomically mark as queued so next batch query doesn't re-select them
            $recipient->update(['status' => 'queued']);

            $delaySeconds = (int) floor($delayMs / 1000);
            SendWhatsAppBulkRecipientJob::dispatch($recipient->id)
                ->delay(now()->addSeconds($delaySeconds));

            // 50ms stagger between recipient dispatches
            $delayMs += 50;
        }

        // Re-dispatch self to continue processing remaining batches
        self::dispatch($campaign->id, $this->batchSize)->delay(now()->addSeconds(max(2, (int) ceil($delayMs / 1000))));
    }
}
