<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use App\Services\Users\IntroducedPeerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntroducedPeerController extends BaseApiController
{
    /**
     * Get the list of peers introduced by the authenticated user,
     * sorted by introduced_count DESC.
     */
    public function index(Request $request, IntroducedPeerService $service): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = $service->getIntroducedPeersWithCount($user, $perPage, $page);

        return response()->json([
            'success' => true,
            'message' => 'Introduced peers fetched successfully.',
            'data' => UserResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
