<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderCreateP2pMeetingRequest;
use App\Http\Requests\Leader\LeaderSendWishRequest;
use App\Http\Requests\Leader\LeaderUpdatePeerRequest;
use App\Models\User;
use App\Services\Leader\LeaderPeersService;
use App\Services\Leader\LeaderPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderPeersController extends Controller
{
    public function __construct(
        private readonly LeaderPeersService $peersService,
    ) {}

    /**
     * List peers with filters & sorting scoped to circle or district with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $sort = $request->query('sort') ? (string) $request->query('sort') : null;
        $search = $request->query('search') ? (string) $request->query('search') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $result = $this->peersService->listPeers(
            circleId: $circleId,
            status: $status,
            sort: $sort,
            search: $search,
            districtId: $districtId,
            user: $request->user(),
            page: $page,
            perPage: $perPage,
        );

        return response()->json([
            'success' => true,
            'message' => 'Peers retrieved successfully.',
            'meta' => $result['meta'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Get birthdays and anniversaries celebrations.
     */
    public function celebrations(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->peersService->getCelebrations($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get detailed rich peer profile.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $data = $this->peersService->getPeer($id, $request->user());

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Peer not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peer profile details retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Send celebration wish to peer.
     */
    public function sendWish(LeaderSendWishRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $senderId = $user ? (string) $user->id : '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5';

        $message = $this->peersService->sendWish(
            $senderId,
            $id,
            (string) $request->validated('type'),
            (string) $request->validated('message'),
        );

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Get historical and scheduled meetings for a peer.
     */
    public function meetings(string $id): JsonResponse
    {
        $data = $this->peersService->getPeerMeetings($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get chronological audit feed of peer activities.
     */
    public function activities(string $id, Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);
        $data = $this->peersService->getPeerActivities($id, $page, $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Quick registration of a 1-on-1 P2P meeting.
     */
    public function storeP2pMeeting(LeaderCreateP2pMeetingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->peersService->createP2pMeeting($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'P2P meeting logged successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Update peer profile information.
     */
    public function update(LeaderUpdatePeerRequest $request, string $id): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();
        $permissionService = app(LeaderPermissionService::class);
        $roleInfo = $permissionService->resolveUserRole($authUser);
        $userRole = (string) ($roleInfo['role'] ?? 'member');

        $canEdit = in_array($userRole, ['superAdmin', 'countryDirector', 'districtExecDirector'], true)
            || in_array('manage_peers', $permissionService->getEnabledCapabilitiesForRole($userRole), true);

        if (! $canEdit && (string) $authUser->id !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this peer profile.',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $peer = User::query()->where('id', $id)->first();
        if (! $peer) {
            return response()->json([
                'success' => false,
                'message' => 'Peer not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $parts = explode(' ', trim((string) $validated['name']), 2);
            $peer->first_name = $parts[0];
            $peer->last_name = $parts[1] ?? '';
        }
        if (isset($validated['company'])) {
            $peer->company_name = (string) $validated['company'];
        }
        if (isset($validated['designation'])) {
            $peer->designation = (string) $validated['designation'];
        }
        if (isset($validated['phone'])) {
            $peer->phone = (string) $validated['phone'];
        }
        if (isset($validated['email'])) {
            $peer->email = (string) $validated['email'];
        }
        if (isset($validated['hide_phone'])) {
            $peer->hide_phone = (bool) $validated['hide_phone'];
        }
        if (isset($validated['hide_email'])) {
            $peer->hide_email = (bool) $validated['hide_email'];
        }
        if (isset($validated['status'])) {
            $peer->status = strtolower((string) $validated['status']);
        }
        if (array_key_exists('intro_video_url', $validated)) {
            $peer->intro_video_url = $validated['intro_video_url'];
        }

        $peer->save();

        return response()->json([
            'success' => true,
            'message' => 'Peer profile updated successfully',
            'data' => [
                'id' => (string) $peer->id,
                'name' => trim(($peer->first_name ?? '').' '.($peer->last_name ?? '')),
                'company' => (string) ($peer->company_name ?? ''),
                'designation' => (string) ($peer->designation ?? ''),
                'phone' => (string) ($peer->phone ?? ''),
                'email' => (string) ($peer->email ?? ''),
                'hide_phone' => (bool) ($peer->hide_phone ?? false),
                'hide_email' => (bool) ($peer->hide_email ?? false),
                'status' => (string) ucfirst((string) ($peer->status ?? 'active')),
                'intro_video_url' => $peer->intro_video_url,
            ],
        ]);
    }
}
