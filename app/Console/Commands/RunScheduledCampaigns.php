<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignSchedule;
use App\Models\CampaignDelivery;
use App\Jobs\ProcessCampaignDeliveryJob;
use App\Services\AdminCampaigns\CampaignScheduleCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:run {--manual}';
    protected $description = 'Trigger pending campaign schedule deliveries';

    public function handle(CampaignScheduleCalculator $calculator): int
    {
        $startTime = microtime(true);
        $now = \Carbon\Carbon::now('UTC')->startOfMinute();
        $triggeredType = $this->option('manual') ? 'manual_check' : 'scheduler';

        // Update heartbeat in cache
        \Illuminate\Support\Facades\Cache::put('scheduler_last_run_at', $now->toIso8601String(), 600);

        try {
            // Find active schedules that are due (next_run_at is in the past or now)
            $schedules = CampaignSchedule::with(['campaign', 'campaign.schedule'])
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', $now)
                ->whereHas('campaign', function ($query) {
                    // Only trigger campaigns in scheduled or active state.
                    $query->whereIn('status', ['scheduled', 'active']);
                })
                ->get();

            $processedCount = $schedules->count();
            $triggeredCount = 0;
            $processedSchedules = [];

            if ($processedCount > 0) {
                foreach ($schedules as $schedule) {
                    if (in_array($schedule->id, $processedSchedules)) {
                        continue;
                    }
                    $processedSchedules[] = $schedule->id;

                    $scheduledTimeStr = $schedule->campaign ? $schedule->campaign->formatTimestamp($schedule->next_run_at) : $schedule->next_run_at;
                    $this->info("Triggering campaign '{$schedule->campaign->title}' (ID: {$schedule->campaign_id}) scheduled for {$scheduledTimeStr}");

                    $delivery = null;
                    try {
                        DB::transaction(function () use ($schedule, $calculator, $now, &$triggeredCount, &$delivery) {
                            // Transition 'scheduled' to 'active' when first executed
                            if ($schedule->campaign->status === 'scheduled') {
                                $schedule->campaign->update(['status' => 'active']);
                                // Log the status transition in audit log
                                try {
                                    $ipAddress = '127.0.0.1';
                                    $userAgent = 'Console Scheduler';
                                    \App\Models\AdminAuditLog::create([
                                        'id' => (string) \Illuminate\Support\Str::uuid(),
                                        'admin_user_id' => null, // triggered by scheduler
                                        'action' => 'active', // transitions to active
                                        'target_table' => 'admin_campaigns',
                                        'target_id' => $schedule->campaign_id,
                                        'details' => [
                                            'campaign_title' => $schedule->campaign->title,
                                            'status' => 'active',
                                            'reason' => 'Scheduler execution started',
                                        ],
                                        'ip_address' => $ipAddress,
                                        'user_agent' => $userAgent,
                                        'created_at' => now(),
                                    ]);
                                } catch (\Exception $e) {
                                    Log::warning('Failed to log campaign schedule active transition: ' . $e->getMessage());
                                }
                            }

                            // 1. Create a campaign delivery run
                            $delivery = CampaignDelivery::create([
                                'campaign_id' => $schedule->campaign_id,
                                'schedule_id' => $schedule->id,
                                'status' => 'scheduled',
                                'scheduled_at' => $schedule->next_run_at,
                                'triggered_by' => $this->option('manual') ? 'manual' : 'scheduler',
                            ]);

                            // 2. Update the in-memory object's last_run_at and calculate next run date
                            $schedule->last_run_at = $schedule->next_run_at;
                            $nextRun = $calculator->calculateNextRunAt($schedule, $now);
                            
                            // 3. Update the schedule record
                            $schedule->update([
                                'last_run_at' => $schedule->last_run_at,
                                'next_run_at' => $nextRun,
                            ]);

                            $schedule->refresh();
                            if ($schedule->campaign) {
                                $schedule->campaign->refresh();
                                if ($schedule->campaign->schedule) {
                                    $schedule->campaign->schedule->refresh();
                                }
                            }

                            $triggeredCount++;
                        });

                        // 4. Dispatch background processing job AFTER transaction has successfully committed
                        if ($delivery) {
                            ProcessCampaignDeliveryJob::dispatch($delivery->id);
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to trigger campaign ID {$schedule->campaign_id}: " . $e->getMessage());
                    }
                }
            } else {
                $this->info("No campaign schedules due at this time.");
            }

            $this->info("Triggered {$triggeredCount} of {$processedCount} campaign schedules.");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
