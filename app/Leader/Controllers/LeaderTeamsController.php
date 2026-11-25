<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderAssignCircleRoleRequest;
use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Leader\Services\LeaderPeersService;
use App\Leader\Services\LeaderTeamsService;
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
        app(LeaderPeersService::class)->assertCircleInScope($circleId, $request->user());

        $status = $request->query('status') ? (string) $request->query('status') : null;
        $sort = $request->query('sort') ? (string) $request->query('sort') : null;
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $data = $this->teamsService->getCirclePeers($circleId, $status, $sort, $search, $request->user(), $page, $perPage);

        return response()->json($data);
    }

    /**
     * Get circle detailed view with leadership team and rich metrics.
     */
    public function showCircle(string $id, Request $request): JsonResponse
    {
        app(LeaderPeersService::class)->assertCircleInScope($id, $request->user());

        $data = $this->teamsService->getCircleDetails($id);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get active and open sub-industries breakdown for a circle.
     */
    public function subIndustries(string $id, Request $request): JsonResponse
    {
        app(LeaderPeersService::class)->assertCircleInScope($id, $request->user());

        $data = $this->teamsService->getSubIndustries($id);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Circle sub-industries fetched successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get circle events and assemblies.
     */
    public function events(string $id, Request $request): JsonResponse
    {
        app(LeaderPeersService::class)->assertCircleInScope($id, $request->user());

        $filter = $request->query('filter') ? (string) $request->query('filter') : null;
        $data = $this->teamsService->getCircleEvents($id, $filter);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Refer a peer or external contact for a circle category.
     */
    public function referCategory(string $id, Request $request): JsonResponse
    {
        app(LeaderPeersService::class)->assertCircleInScope($id, $request->user());

        $data = $this->teamsService->referCategory($id, $request->user(), $request->all());

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Category referral submitted successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Get saved/synced contacts for referring to a circle category.
     */
    public function contacts(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $limit = (int) $request->query('per_page', 60);
        $data = $this->teamsService->getUserContacts($request->user(), $search, $limit);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Contacts fetched successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Assign a circle officer or committee role (Chair, Vice Chair, Secretary, Committee Leader).
     */
    public function assignRole(LeaderAssignCircleRoleRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $data = $this->teamsService->assignCircleRole(
            circleId: $id,
            targetUserId: (string) $validated['user_id'],
            roleKey: (string) $validated['role'],
            actor: $request->user(),
            committeeName: isset($validated['committee_name']) ? (string) $validated['committee_name'] : null,
        );

        try {
            app(LeaderNotificationDispatcherService::class)->dispatchEvent(
                'leadership_tenure_appointed',
                'Leadership Role Assigned',
                'A leadership role assignment ('.(string) $validated['role'].') has been updated for this Circle.',
                [
                    'circle_id' => $id,
                    'actor_id' => (string) ($request->user()?->id ?? ''),
                    'explicit_recipient_ids' => [(string) $validated['user_id']],
                    'entity_id' => $id.'_'.(string) $validated['user_id'],
                    'reference_id' => $id,
                ]
            );
        } catch (\Throwable) {
            // Non-blocking notification dispatch
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Circle role assigned successfully.',
            'data' => $data,
        ]);
    }
}
