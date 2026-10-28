<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Console\Command;

class AutoStartSpeedNetworkingEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'speed-networking:auto-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-start scheduled Speed Networking events that have reached their start time and notify registered attendees';

    /**
     * Execute the console command.
     */
    public function handle(SpeedNetworkingService $speedNetworkingService): int
    {
        $this->info('Checking for scheduled speed networking events to auto-start...');

        $started = $speedNetworkingService->checkAndAutoStartScheduledEvents();

        $count = count($started);
        if ($count > 0) {
            $this->info("Successfully auto-started {$count} speed networking event(s) and notified attendees.");
            foreach ($started as $event) {
                $this->line(" - Event ID: {$event->id} | Title: {$event->title}");
            }
        } else {
            $this->info('No speed networking events currently due for auto-start.');
        }

        return self::SUCCESS;
    }
}
