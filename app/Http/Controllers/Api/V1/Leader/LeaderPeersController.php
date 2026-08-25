<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderSendWishRequest;
use App\Services\Leader\LeaderPeersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderPeersController extends Controller
{
    public function __construct(
        private readonly LeaderPeersService $peersService,
    ) {}

    /**
     * List peers with filters & sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $sort = $request->query('sort') ? (string) $request->query('sort') : null;
        $search = $request->query('search') ? (string) $request->query('search') : null;

        $data = $this->peersService->listPeers($circleId, $status, $sort, $search);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get birthdays and anniversaries celebrations.
     */
    public function celebrations(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $data = $this->peersService->getCelebrations($circleId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get detailed peer profile.
     */
    public function show(string $id): JsonResponse
    {
        $data = $this->peersService->getPeer($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Send celebration wish to peer.
     */
    public function sendWish(LeaderSendWishRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $senderId = $user ? (string) $user->id : 'usr_987214';

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
}
