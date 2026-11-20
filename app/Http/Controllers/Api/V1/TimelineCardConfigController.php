<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TimelineCardConfigService;
use Illuminate\Http\JsonResponse;

class TimelineCardConfigController extends Controller
{
    public function __construct(
        private readonly TimelineCardConfigService $service,
    ) {}

    public function getTimelineCardsConfig(): JsonResponse
    {
        $config = $this->service->getTimelineCardsConfig();

        return response()->json([
            'status' => true,
            'message' => 'Timeline cards configuration retrieved successfully',
            'data' => [
                'timeline_cards' => $config,
            ],
        ]);
    }
}
