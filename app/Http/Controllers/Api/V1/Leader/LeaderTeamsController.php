<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Services\Leader\LeaderTeamsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderTeamsController extends Controller
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
    ) {}

    /**
     * Get teams overview summary metrics.
     */
    public function summary(): JsonResponse
    {
        $data = $this->teamsService->getTeamsSummary();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get circles directory list with health % and stats.
     */
    public function circles(Request $request): JsonResponse
    {
        $industry = $request->query('industry') ? (string) $request->query('industry') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $search = $request->query('search') ? (string) $request->query('search') : null;

        $data = $this->teamsService->getCirclesList($industry, $status, $search);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get circle detailed view.
     */
    public function showCircle(string $id): JsonResponse
    {
        $data = $this->teamsService->getCircleDetails($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
