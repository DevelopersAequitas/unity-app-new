<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Requirement\RequirementTimelineResource;
use App\Services\Requirements\TimelineRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineRequirementController extends Controller
{
    public function __construct(
        private readonly TimelineRequirementService $timelineRequirementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->timelineRequirementService->getOpenRequirements($request);

        return response()->json([
            'status' => true,
            'message' => 'Open requirements fetched successfully.',
            'data' => RequirementTimelineResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
