<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Services\LeaderMember360Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderMemberController extends Controller
{
    public function __construct(
        private readonly LeaderMember360Service $member360Service,
    ) {}

    /**
     * GET /api/v1/leader/members/{member_id}
     *
     * Return the complete Member 360° profile with summary activity counts.
     */
    public function show(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $data = $this->member360Service->getMemberProfile($memberId, $leader);

        return response()->json([
            'success' => true,
            'message' => 'Member profile retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/activities
     *
     * Return the unified chronological activity feed for the member.
     */
    public function activities(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'activity_type' => $request->query('activity_type'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
            'search' => $request->query('search'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getActivities($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member activities retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/posts
     *
     * Return paginated timeline posts for the member.
     */
    public function posts(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getMemberPosts($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member posts retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/creatives
     *
     * Return paginated creatives/media for the member.
     */
    public function creatives(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getMemberCreatives($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member creatives retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/badges
     *
     * Return paginated earned badges/milestones for the member.
     */
    public function badges(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getMemberBadges($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member badges retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/events
     *
     * Return paginated events associated with the member.
     */
    public function events(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getMemberEvents($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member events retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/leader/members/{member_id}/event-registrations
     *
     * Return paginated event registrations for the member.
     */
    public function eventRegistrations(string $memberId, Request $request): JsonResponse
    {
        /** @var User $leader */
        $leader = $request->user();

        $member = $this->member360Service->resolveMember($memberId, $leader);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        $filters = [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->member360Service->getMemberEventRegistrations($memberId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Member event registrations retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}
