<?php

namespace App\Http\Controllers\Api;

use App\Models\Circle;
use App\Services\Zoho\CircleAddonSyncService;

class CirclePlansController extends BaseApiController
{
    public function __construct(private readonly CircleAddonSyncService $syncService)
    {
    }

    public function show(Circle $circle)
    {
        return $this->success([
            'circle_id' => $circle->id,
            'plans' => $this->syncService->resolveAvailablePlans($circle),
        ], 'Circle billing plans fetched successfully.');
    }
}
