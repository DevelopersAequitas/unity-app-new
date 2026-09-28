<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\UpdateMatchRequest;
use App\Http\Resources\Ask\AskMatchResource;
use App\Models\Ask\Ask;
use App\Models\Ask\AskMatch;
use App\Models\User;
use App\Services\Ask\AskMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskMatchController extends Controller
{
    public function __construct(
        protected AskMatchingService $matchingService
    ) {}

    /**
     * API 15 — Generate Matches
     * POST /api/asks/{ask}/matches/generate
     */
    public function generate(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $matches = $this->matchingService->generateMatches($ask);

        return response()->json([
            'success' => true,
            'message' => 'Matches generated successfully.',
            'count' => $matches->count(),
            'data' => AskMatchResource::collection($matches),
        ]);
    }

    /**
     * API 16 — Get Matched Peers
     * GET /api/asks/{ask}/matches
     */
    public function index(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $matches = $this->matchingService->getMatches($ask, $request->all());

        return response()->json([
            'success' => true,
            'data' => AskMatchResource::collection($matches->items()),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'last_page' => $matches->lastPage(),
            ],
        ]);
    }

    /**
     * API 17 — Match Action (viewed, dismissed, interested, connected)
     * PATCH /api/asks/{ask}/matches/{match}
     */
    public function update(UpdateMatchRequest $request, Ask $ask, AskMatch $match): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        if ((string) $match->ask_id !== (string) $ask->id) {
            abort(404, 'Match record does not belong to this Ask.');
        }

        $validated = $request->validated();
        $updatedMatch = $this->matchingService->updateMatchStatus($match, (string) $validated['match_status']);

        return response()->json([
            'success' => true,
            'message' => 'Match status updated successfully.',
            'data' => new AskMatchResource($updatedMatch),
        ]);
    }

    protected function authorizeOwner(?User $user, Ask $ask): void
    {
        if (! $user || (string) $ask->user_id !== (string) $user->id) {
            abort(403, 'Unauthorized action on this Ask.');
        }
    }
}
