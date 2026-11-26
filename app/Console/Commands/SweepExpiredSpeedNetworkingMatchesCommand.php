<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Console\Command;

class SweepExpiredSpeedNetworkingMatchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'speed-networking:sweep-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire timed-out Speed Networking preview matches and active calls on the server.';

    /**
     * Execute the console command.
     */
    public function handle(SpeedNetworkingService $service): int
    {
        $result = $service->sweepExpiredMatches();

        $this->info(sprintf(
            'Speed Networking sweep completed: %d preview(s) expired, %d call(s) finalized.',
            $result['expired_previews'],
            $result['expired_calls']
        ));

        return Command::SUCCESS;
    }
}
