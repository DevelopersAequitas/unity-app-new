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
    protected $description = 'Automatically start scheduled Speed Networking events whose scheduled start time has arrived.';

    /**
     * Execute the console command.
     */
    public function handle(SpeedNetworkingService $service): int
    {
        $this->info('Checking for scheduled Speed Networking events to auto-start...');

        $started = $service->checkAndAutoStartScheduledEvents();
        $count = count($started);

        if ($count > 0) {
            $this->info("Successfully auto-started {$count} speed networking event(s).");
            foreach ($started as $event) {
                $this->line(" - Started event [{$event->id}] '{$event->title}' (Round 1 generated).");
            }
        } else {
            $this->info('No speed networking events currently due for auto-start.');
        }

        return Command::SUCCESS;
    }
}
