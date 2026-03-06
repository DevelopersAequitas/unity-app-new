<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Services\Zoho\CircleAddonSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCircleZohoAddons extends Command
{
    protected $signature = 'circles:sync-zoho-addons';

    protected $description = 'Sync all existing circles with Zoho Billing addons';

    public function __construct(private readonly CircleAddonSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        Circle::query()->orderBy('created_at')->chunkById(50, function ($circles) use (&$created, &$updated, &$skipped): void {
            foreach ($circles as $circle) {
                try {
                    $result = $this->syncService->syncCircle($circle);
                    $created += (int) ($result['created'] ?? 0);
                    $updated += (int) ($result['updated'] ?? 0);
                    $skipped += (int) ($result['skipped'] ?? 0);
                } catch (\Throwable $throwable) {
                    Log::error('Circle addon backfill failed', [
                        'circle_id' => $circle->id,
                        'error' => $throwable->getMessage(),
                    ]);
                    $this->warn("Failed: {$circle->id} {$circle->name}");
                }
            }
        }, 'id');

        $this->info("Done. created={$created}, updated={$updated}, skipped={$skipped}");

        return self::SUCCESS;
    }
}
