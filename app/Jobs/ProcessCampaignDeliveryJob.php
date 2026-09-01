<?php

namespace App\Jobs;

use App\Models\AdminCampaign;
use App\Models\CampaignDelivery;
use App\Models\CampaignLog;
use App\Services\AdminCampaigns\CampaignRecipientResolverService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessCampaignDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Allow ample time for large audiences

    public function __construct(protected string $deliveryId) {}

    public function handle(CampaignRecipientResolverService $resolver): void
    {
        $delivery = CampaignDelivery::with('campaign')->find($this->deliveryId);
        if (! $delivery || ! $delivery->campaign) {
            Log::error('ProcessCampaignDeliveryJob failed: Delivery or campaign not found', ['delivery_id' => $this->deliveryId]);

            return;
        }

        $campaign = $delivery->campaign;

        // Idempotency check & Resumable setup:
        // Do not skip the entire run based on batch_id alone (a filled batch_id doesn't mean all jobs succeeded).
        // Instead, retrieve all existing log records to track recipient-level progress.
        $existingLogs = CampaignLog::where('delivery_id', $this->deliveryId)
            ->select('id', 'user_id', 'sent_at')
            ->get()
            ->keyBy('user_id');

        Log::info('ProcessCampaignDeliveryJob started', [
            'delivery_id' => $this->deliveryId,
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'existing_logs_count' => $existingLogs->count(),
        ]);

        // Set status to processing
        $delivery->update([
            'status' => 'processing',
            'started_at' => $delivery->started_at ?: now(),
        ]);

        try {
            // 1. Resolve recipients
            $query = $resolver->query(
                $campaign->audience_type,
                $campaign->filters,
                $campaign->includesEmail()
            );

            $recipientCount = $query->count();
            $delivery->update(['total_recipients' => $recipientCount]);

            if ($recipientCount === 0) {
                // Complete delivery with zero recipients
                $delivery->update([
                    'status' => 'sent',
                    'completed_at' => now(),
                ]);

                // For immediate/once campaigns, sync status to parent campaign
                $this->syncCampaignStatus($campaign);

                return;
            }

            // 2. Prepare and chunk recipient logs creation to prevent memory overflow
            $jobs = [];
            $deliveryId = $this->deliveryId;

            // Retrieve user IDs and emails in chunks
            $query->chunk(250, function ($users) use ($deliveryId, $existingLogs, &$jobs) {
                $logsData = [];
                $now = now();

                foreach ($users as $user) {
                    $existingLog = $existingLogs->get($user->id);

                    if ($existingLog) {
                        // If the recipient was already fully processed, skip completely to prevent duplicates
                        if (filled($existingLog->sent_at)) {
                            continue;
                        }

                        // If log exists but sent_at is NULL, the run was interrupted.
                        // Reuse the existing log ID and queue the recipient job.
                        $jobs[] = new SendCampaignRecipientJob($deliveryId, $existingLog->id, $user->id);

                        continue;
                    }

                    // For new recipients, create a brand-new log record and queue the job
                    $logId = (string) Str::uuid();
                    $logsData[] = [
                        'id' => $logId,
                        'delivery_id' => $deliveryId,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'email_status' => 'queued',
                        'notification_status' => 'queued',
                        'email_sent' => false,
                        'notification_sent' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $jobs[] = new SendCampaignRecipientJob($deliveryId, $logId, $user->id);
                }

                // Bulk insert only the new recipient logs to avoid duplicate key violations
                if (! empty($logsData)) {
                    CampaignLog::insert($logsData);
                }
            });

            // 3. Dispatch Jobs Batch if there are any remaining recipients to process
            if (empty($jobs)) {
                Log::info('ProcessCampaignDeliveryJob completed: All recipients already processed', [
                    'delivery_id' => $deliveryId,
                ]);

                $status = 'sent';
                if ($delivery->total_failed > 0) {
                    $status = ($delivery->total_failed >= $delivery->total_recipients) ? 'failed' : 'partially_sent';
                }
                $delivery->update([
                    'status' => $status,
                    'completed_at' => now(),
                ]);

                $this->syncCampaignStatus($campaign);

                return;
            }

            $batchName = 'Campaign Send: '.$campaign->title.' (Run: '.$delivery->scheduled_at->format('Y-m-d H:i').')';

            $queueName = env('CAMPAIGN_QUEUE_NAME', 'default');

            Log::info('Dispatching SendCampaignRecipientJob batch', [
                'delivery_id' => $deliveryId,
                'campaign_id' => $campaign->id,
                'remaining_recipient_count' => count($jobs),
                'batch_name' => $batchName,
                'queue' => $queueName,
            ]);

            $batch = Bus::batch($jobs)
                ->name($batchName)
                ->then(function (Batch $batch) use ($deliveryId) {
                    Log::info('Campaign Send Batch completed successfully', [
                        'delivery_id' => $deliveryId,
                        'batch_id' => $batch->id,
                    ]);

                    // Success callback
                    $del = CampaignDelivery::with('campaign')->find($deliveryId);
                    if ($del) {
                        $status = 'sent';
                        if ($del->total_failed > 0) {
                            $status = ($del->total_failed >= $del->total_recipients) ? 'failed' : 'partially_sent';
                        }
                        $del->update([
                            'status' => $status,
                            'completed_at' => now(),
                        ]);

                        $campaign = $del->campaign;
                        if ($campaign) {
                            (new ProcessCampaignDeliveryJob($deliveryId))->syncCampaignStatus($campaign);
                        }
                    }
                })
                ->catch(function (Batch $batch, Throwable $e) use ($deliveryId) {
                    Log::error('Campaign Send Batch failed', [
                        'delivery_id' => $deliveryId,
                        'batch_id' => $batch->id,
                        'error' => $e->getMessage(),
                    ]);

                    // Failure callback
                    $del = CampaignDelivery::with('campaign')->find($deliveryId);
                    if ($del) {
                        $del->update([
                            'status' => 'failed',
                            'error_message' => 'Batch Execution Failed: '.$e->getMessage(),
                            'completed_at' => now(),
                        ]);

                        $campaign = $del->campaign;
                        if ($campaign) {
                            (new ProcessCampaignDeliveryJob($deliveryId))->syncCampaignStatus($campaign);
                        }
                    }
                });

            if ($queueName && $queueName !== 'default') {
                $batch->onQueue($queueName);
            }

            $batch = $batch->dispatch();

            $delivery->update(['batch_id' => $batch->id]);

        } catch (Throwable $e) {
            Log::error('ProcessCampaignDeliveryJob failure', [
                'delivery_id' => $this->deliveryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $delivery->update([
                'status' => 'failed',
                'error_message' => 'System Execution Error: '.$e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->syncCampaignStatus($campaign);
        }
    }

    public function syncCampaignStatus(AdminCampaign $campaign): void
    {
        $aggregates = DB::table('campaign_deliveries')
            ->where('campaign_id', $campaign->id)
            ->selectRaw('
                SUM(total_recipients) as total_recipients,
                SUM(total_email_sent) as total_email_sent,
                SUM(total_notification_sent) as total_notification_sent,
                SUM(total_failed) as total_failed
            ')
            ->first();

        $latestDelivery = CampaignDelivery::where('campaign_id', $campaign->id)
            ->latest('scheduled_at')
            ->first();

        if ($latestDelivery) {
            $schedule = $campaign->schedule;
            $newStatus = $latestDelivery->status;

            if ($schedule && $schedule->schedule_type !== 'immediately') {
                if ($schedule->next_run_at !== null) {
                    $newStatus = 'active';
                } else {
                    $newStatus = 'completed';
                }
            }

            $campaign->update([
                'total_recipients' => (int) ($aggregates->total_recipients ?? 0),
                'total_email_sent' => (int) ($aggregates->total_email_sent ?? 0),
                'total_notification_sent' => (int) ($aggregates->total_notification_sent ?? 0),
                'total_failed' => (int) ($aggregates->total_failed ?? 0),
                'status' => $newStatus,
                'sent_at' => $latestDelivery->completed_at ?: $campaign->sent_at,
            ]);
        }
    }
}
