<?php

namespace App\Console\Commands;

use App\Models\Notifications\NotificationCampaign;
use App\Services\Notifications\CampaignService;
use Illuminate\Console\Command;

class RunNotificationCampaignsCommand extends Command
{
    protected $signature = 'notifications:campaigns {frequency=hourly}';

    protected $description = 'Run active notification campaigns for a scheduler frequency.';

    public function handle(CampaignService $service): int
    {
        $frequency = $this->argument('frequency');
        NotificationCampaign::where('is_active', true)->where(function ($q) use ($frequency) {
            $q->where('frequency', $frequency)->orWhereNull('frequency');
        })->each(fn ($c) => $service->runCampaign($c));

        return self::SUCCESS;
    }
}
