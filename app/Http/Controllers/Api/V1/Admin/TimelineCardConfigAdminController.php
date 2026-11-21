<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTimelineCardsConfigRequest;
use App\Services\TimelineCardConfigService;
use Illuminate\Http\JsonResponse;

class TimelineCardConfigAdminController extends Controller
{
    public function __construct(
        private readonly TimelineCardConfigService $service,
    ) {}

    public function show(): JsonResponse
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

    public function update(UpdateTimelineCardsConfigRequest $request): JsonResponse
    {
        $userId = $request->user()?->id ? (string) $request->user()->id : null;
        $validated = $request->validated();

        $updated = $this->service->updateTimelineCardsConfig($validated, $userId);

        return response()->json([
            'status' => true,
            'message' => 'Timeline cards configuration updated successfully',
            'data' => [
                'timeline_cards' => $updated,
            ],
        ]);
    }
}
