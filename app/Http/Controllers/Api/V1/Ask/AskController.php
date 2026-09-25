<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\CreateAskDraftRequest;
use App\Http\Requests\Ask\LinkReferralRequest;
use App\Http\Requests\Ask\SaveAskDetailsRequest;
use App\Http\Requests\Ask\SaveAskFiltersRequest;
use App\Http\Requests\Ask\SetAskVisibilityRequest;
use App\Http\Requests\Ask\SetTimelinePreferenceRequest;
use App\Http\Requests\Ask\UpdateAskRequest;
use App\Http\Requests\Ask\UpdateAskStatusRequest;
use App\Http\Resources\Ask\AskPreviewResource;
use App\Http\Resources\Ask\AskResource;
use App\Http\Resources\Ask\AskStatusHistoryResource;
use App\Models\Ask\Ask;
use App\Models\User;
use App\Services\Ask\AskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskController extends Controller
{
    public function __construct(
        protected AskService $askService
    ) {}

    /**
     * API 4 — Create Ask Draft
     * POST /api/asks
     */
    public function storeDraft(CreateAskDraftRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ask = $this->askService->createDraft($user, $request->validated());

        return response()->json([
            'success' => true,
            'ask_id' => (string) $ask->id,
            'status' => $ask->status,
            'data' => new AskResource($ask),
        ], 201);
    }

    /**
     * API 5 — Save / Update Ask Details
     * PUT /api/asks/{ask}
     */
    public function saveDetails(SaveAskDetailsRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $validated = $request->validated();
        $updatedAsk = $this->askService->saveDetails($ask, (array) ($validated['answers'] ?? []));

        return response()->json([
            'success' => true,
            'message' => 'Ask details saved successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 6 — Save Ask Filters
     * PUT /api/asks/{ask}/filters
     */
    public function saveFilters(SaveAskFiltersRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $filters = $request->validated();
        if (isset($filters['filters']) && is_array($filters['filters'])) {
            $filters = array_merge($filters, $filters['filters']);
            unset($filters['filters']);
        }

        $updatedAsk = $this->askService->saveFilters($ask, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Ask filters saved successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 7 — Set Ask Visibility
     * PUT /api/asks/{ask}/visibility
     */
    public function setVisibility(SetAskVisibilityRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $validated = $request->validated();
        $updatedAsk = $this->askService->setVisibility(
            $ask,
            (string) $validated['visibility_type'],
            isset($validated['district_id']) ? (string) $validated['district_id'] : null,
            isset($validated['circle_id']) ? (string) $validated['circle_id'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Ask visibility updated successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 8 — Set Timeline Publishing Preference
     * PUT /api/asks/{ask}/timeline-preference
     */
    public function setTimelinePreference(SetTimelinePreferenceRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $updatedAsk = $this->askService->setTimelinePreference($ask, $request->wantsTimeline());

        return response()->json([
            'success' => true,
            'message' => 'Timeline preference updated successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 9 — Preview Ask
     * GET /api/asks/{ask}/preview
     */
    public function preview(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $previewAsk = $this->askService->preview($ask);

        return response()->json([
            'success' => true,
            'data' => new AskPreviewResource($previewAsk),
        ]);
    }

    /**
     * API 10 — Publish Ask
     * POST /api/asks/{ask}/publish
     */
    public function publish(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        if ($request->has('post_to_timeline') || $request->has('publish_to_timeline')) {
            $timelinePref = $request->has('post_to_timeline')
                ? $request->boolean('post_to_timeline')
                : $request->boolean('publish_to_timeline');
            $ask->update(['publish_to_timeline' => $timelinePref]);
            $ask->refresh();
        }

        /** @var User $user */
        $user = $request->user();
        $publishedAsk = $this->askService->publish($ask, $user);

        return response()->json([
            'success' => true,
            'message' => 'Ask published successfully.',
            'data' => new AskResource($publishedAsk),
        ]);
    }

    /**
     * API 11 — My Asks (Listing)
     * GET /api/asks
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $asks = $this->askService->listUserAsks($user, $request->all());

        return response()->json([
            'success' => true,
            'data' => AskResource::collection($asks->items()),
            'meta' => [
                'current_page' => $asks->currentPage(),
                'per_page' => $asks->perPage(),
                'total' => $asks->total(),
                'last_page' => $asks->lastPage(),
            ],
        ]);
    }

    /**
     * API 12 — Ask Details
     * GET /api/asks/{ask}
     */
    public function show(Ask $ask): JsonResponse
    {
        $detailedAsk = $this->askService->getAskDetails($ask);

        return response()->json([
            'success' => true,
            'data' => new AskResource($detailedAsk),
        ]);
    }

    /**
     * API 13 — Update Ask
     * PATCH /api/asks/{ask}
     */
    public function update(UpdateAskRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $updatedAsk = $this->askService->updateAsk($ask, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ask updated successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 14 — Cancel / Close Ask
     * PATCH /api/asks/{ask}/status
     */
    public function updateStatus(UpdateAskStatusRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $updatedAsk = $this->askService->updateStatus(
            $ask,
            $user,
            (string) $validated['status'],
            isset($validated['reason']) ? (string) $validated['reason'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Ask status updated successfully.',
            'data' => new AskResource($updatedAsk),
        ]);
    }

    /**
     * API 24 — Get Ask Status History
     * GET /api/asks/{ask}/history
     */
    public function history(Ask $ask): JsonResponse
    {
        $history = $this->askService->getStatusHistory($ask);

        return response()->json([
            'success' => true,
            'data' => AskStatusHistoryResource::collection($history),
        ]);
    }

    /**
     * API 26 / 27 — Link Existing Referral to Ask
     * POST /api/asks/{ask}/referral-link
     */
    public function linkReferral(LinkReferralRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $validated = $request->validated();
        $this->askService->linkReferral($ask, (string) $validated['referral_id']);

        return response()->json([
            'success' => true,
            'message' => 'Referral linked to Ask successfully.',
        ]);
    }

    /**
     * Authorize that the current user owns the Ask.
     */
    protected function authorizeOwner(?User $user, Ask $ask): void
    {
        if (! $user || (string) $ask->user_id !== (string) $user->id) {
            abort(403, 'Unauthorized action on this Ask.');
        }
    }
}
