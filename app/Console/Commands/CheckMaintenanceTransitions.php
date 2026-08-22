<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class CheckMaintenanceTransitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-maintenance-transitions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process automated app maintenance state transitions and send FCM push notifications';

    /**
     * Execute the console command.
     */
    public function handle(MaintenanceService $service): int
    {
        $service->processMaintenanceTransitions();
        $this->info('Maintenance transitions processed successfully.');

        return Command::SUCCESS;
    }
}
