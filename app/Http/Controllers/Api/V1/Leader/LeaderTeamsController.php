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
    public function summary(Request $request): JsonResponse
    {
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;
        $data = $this->teamsService->getTeamsSummary($districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get official master list of 18 industries with circle and peer counts.
     */
    public function industries(Request $request): JsonResponse
    {
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;

        $data = $this->teamsService->getIndustriesList($districtId, $request->user(), $status);

        return response()->json([
            'success' => true,
            'message' => 'Industries fetched successfully.',
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
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->teamsService->getCirclesList($industry, $status, $search, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get peers belonging to a specific circle.
     */
    public function circlePeers(string $circleId, Request $request): JsonResponse
    {
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $sort = $request->query('sort') ? (string) $request->query('sort') : null;
        $search = $request->query('search') ? (string) $request->query('search') : null;

        $data = $this->teamsService->getCirclePeers($circleId, $status, $sort, $search, $request->user());

        return response()->json($data);
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

    /**
     * Get active and open sub-industries breakdown for a circle.
     */
    public function subIndustries(string $id): JsonResponse
    {
        $data = $this->teamsService->getSubIndustries($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get circle events and assemblies.
     */
    public function events(string $id, Request $request): JsonResponse
    {
        $filter = $request->query('filter') ? (string) $request->query('filter') : null;
        $data = $this->teamsService->getCircleEvents($id, $filter);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
