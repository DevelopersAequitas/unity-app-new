<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\CreateAskDraftRequest;
use App\Http\Requests\Ask\LinkReferralRequest;
use App\Http\Requests\Ask\MyAsksFilterRequest;
use App\Http\Requests\Ask\PublishAskRequest;
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
use App\Models\BusinessDeal;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use App\Services\Ask\AskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    public function publish(PublishAskRequest $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $validated = $request->validated();

        if (isset($validated['post_to_timeline']) || isset($validated['publish_to_timeline'])) {
            $timelinePref = (bool) ($validated['post_to_timeline'] ?? $validated['publish_to_timeline']);
            $ask->update(['publish_to_timeline' => $timelinePref]);
            $ask->refresh();
        }

        /** @var User $user */
        $user = $request->user();
        $publishedAsk = $this->askService->publish($ask, $user, $validated);

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
    public function index(MyAsksFilterRequest $request): JsonResponse
    {
        if ($request->query('view') === 'legacy') {
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

        return app(AskFeedController::class)->myAsks($request);
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
     * API 14 — Cancel / Close / Fulfill Ask
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
            isset($validated['reason']) ? (string) $validated['reason'] : (isset($validated['note']) ? (string) $validated['note'] : null),
            $validated
        );

        $status = (string) $updatedAsk->status;
        $message = $status === Ask::STATUS_FULFILLED
            ? 'Ask fulfilled and outcome recorded successfully'
            : (! empty($validated['outcome_status']) || ! empty($validated['approx_value'])
                ? 'Ask status and outcome recorded successfully'
                : 'Ask status updated successfully.');

        $resourceData = (new AskResource($updatedAsk))->resolve($request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => array_merge($resourceData, [
                'id' => (string) $updatedAsk->id,
                'status' => (string) $updatedAsk->status,
                'outcome_status' => $updatedAsk->outcome_status ?? ($updatedAsk->metadata['outcome_status'] ?? null),
                'approx_value' => $updatedAsk->approx_deal_value ?? ($updatedAsk->metadata['approx_value'] ?? null),
                'fulfilled_at' => $updatedAsk->fulfilled_at?->toISOString() ?? ($updatedAsk->metadata['fulfilled_at'] ?? null),
            ]),
        ]);
    }

    /**
     * API 14b — Close and Thank Giver (Deal Closed & Feed Story)
     * POST /api/asks/{ask}/close
     */
    public function closeWithFeedback(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:closed,completed'],
            'outcome' => ['nullable', 'string', 'in:deal_closed,met_no_deal,contact_did_not_respond'],
            'business_value' => ['nullable', 'string', 'in:under_1_lakh,1_to_10_lakh,above_10_lakh'],
            'add_to_facilitated_total' => ['nullable', 'boolean'],
            'thank_you_note' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'story' => ['nullable', 'string', 'max:2000'],
            'share_on_feed' => ['nullable', 'boolean'],
            'giver_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'giver_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        /** @var User $currentUser */
        $currentUser = $request->user();

        $outcome = (string) ($validated['outcome'] ?? ($validated['status'] === 'completed' ? 'deal_closed' : 'met_no_deal'));
        $businessValue = $validated['business_value'] ?? null;
        $addToFacilitated = (bool) ($validated['add_to_facilitated_total'] ?? false);
        $thankYouNote = trim((string) ($validated['thank_you_note'] ?? $validated['story'] ?? $validated['notes'] ?? ''));
        $shareOnFeed = (bool) ($validated['share_on_feed'] ?? ($outcome === 'deal_closed'));

        // Update Ask to closed status
        $updatedAsk = $this->askService->updateStatus(
            $ask,
            $currentUser,
            Ask::STATUS_CLOSED,
            "Closed with outcome: {$outcome}"
        );

        // Resolve Giver
        $giverId = $validated['giver_user_id'] ?? $validated['giver_id'] ?? null;
        $giver = null;
        if ($giverId) {
            $giver = User::query()->find($giverId);
        }
        if (! $giver) {
            $response = $ask->responses()->with('responder')->latest('responded_at')->first();
            $giver = $response?->responder;
        }

        // Store BusinessDeal if anonymous total or deal closed
        if ($outcome === 'deal_closed' && $giver && $addToFacilitated) {
            $amountMap = [
                'under_1_lakh' => 50000,
                '1_to_10_lakh' => 500000,
                'above_10_lakh' => 1500000,
            ];
            $dealAmount = $amountMap[$businessValue] ?? 100000;

            try {
                BusinessDeal::create([
                    'from_user_id' => $giver->id,
                    'to_user_id' => $currentUser->id,
                    'deal_date' => now()->toDateString(),
                    'deal_amount' => $dealAmount,
                    'business_type' => 'new',
                    'comment' => $thankYouNote !== '' ? $thankYouNote : "Deal closed for Ask: {$ask->title}",
                    'is_deleted' => false,
                ]);
            } catch (Throwable $e) {
                Log::warning('BusinessDeal creation failed on ask close', ['error' => $e->getMessage()]);
            }
        }

        $congratsPost = null;

        // Post congratulations story on timeline if requested
        if ($shareOnFeed && $outcome === 'deal_closed') {
            $authorName = $currentUser->display_name ?: trim(($currentUser->first_name ?? '').' '.($currentUser->last_name ?? ''));
            $giverName = $giver ? ($giver->display_name ?: trim(($giver->first_name ?? '').' '.($giver->last_name ?? ''))) : 'Peer Member';

            $postContent = "🎉 Congratulations!\n\n";
            if ($giver) {
                $postContent .= "A deal has been closed between @[{$authorName}]({$currentUser->id}) and @[{$giverName}]({$giver->id}) for: \"{$ask->title}\".";
            } else {
                $postContent .= "A deal has been closed by @[{$authorName}]({$currentUser->id}) for: \"{$ask->title}\".";
            }

            if ($thankYouNote !== '') {
                $postContent .= "\n\n\"{$thankYouNote}\"";
            }

            try {
                $congratsPost = Post::create([
                    'user_id' => $currentUser->id,
                    'title' => "Deal Closed: {$ask->title}",
                    'content_text' => $postContent,
                    'media' => [],
                    'tags' => ['deal_closed', 'congratulations', 'ask'],
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'sponsored' => false,
                    'is_deleted' => false,
                    'active' => true,
                    'source_type' => 'ask',
                    'source_id' => $ask->id,
                    'source_event' => 'completed',
                    'post_type' => 'deal_closed',
                ]);

                if ($giver) {
                    PostMention::create([
                        'post_id' => $congratsPost->id,
                        'peer_id' => $giver->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Congratulations post creation failed on ask close', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ask closed and thank you story posted to timeline.',
            'data' => [
                'id' => (string) $updatedAsk->id,
                'status' => $updatedAsk->status,
                'outcome' => $outcome,
                'business_value' => $businessValue,
                'add_to_facilitated_total' => $addToFacilitated,
                'thank_you_note' => $thankYouNote !== '' ? $thankYouNote : null,
                'share_on_feed' => $shareOnFeed,
                'giver' => $giver ? [
                    'id' => (string) $giver->id,
                    'name' => $giver->display_name ?: trim(($giver->first_name ?? '').' '.($giver->last_name ?? '')),
                    'company_name' => $giver->company_name,
                    'city' => $giver->city,
                    'profile_photo_image' => $giver->profile_photo_file_id ? url('/api/v1/files/'.$giver->profile_photo_file_id) : $giver->profile_photo_url,
                ] : null,
                'congratulations_post' => $congratsPost ? [
                    'id' => (string) $congratsPost->id,
                    'content_text' => $congratsPost->content_text,
                    'visibility' => $congratsPost->visibility,
                    'created_at' => $congratsPost->created_at?->toISOString(),
                ] : null,
            ],
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
