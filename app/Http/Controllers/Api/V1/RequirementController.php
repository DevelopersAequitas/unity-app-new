<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Requirement\IncompleteRequirementResource;
use App\Http\Resources\Requirement\RequirementDetailResource;
use App\Models\BusinessDeal;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\Requirement;
use App\Models\RequirementInterest;
use App\Models\User;
use App\Services\Requirements\RequirementNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RequirementController extends Controller
{
    public function __construct(private readonly RequirementNotificationService $requirementNotificationService) {}

    private function createRequirementPostIfMissing(Requirement $requirement): void
    {
        $alreadyExists = Post::where('source_type', 'requirement')
            ->where('source_id', $requirement->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $contentText = trim((string) $requirement->subject.' '.(string) $requirement->description);

        Post::create([
            'user_id' => $requirement->user_id,
            'content_text' => $contentText,
            'media' => $requirement->media ?? [],
            'visibility' => 'public',
            'moderation_status' => 'pending',
            'source_type' => 'requirement',
            'source_id' => $requirement->id,
            'is_deleted' => false,
            'active' => true,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Create requirement request received', [
            'user_id' => auth()->id(),
            'payload' => $request->all(),
        ]);

        if (! auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
                'data' => null,
                'meta' => null,
            ], 401);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'media' => ['nullable', 'array'],
            'media.*.type' => ['nullable', 'string'],
            'media.*.file_id' => ['nullable', 'string'],
            'region_filter' => ['nullable', 'array'],
            'category_filter' => ['nullable', 'array'],
        ]);

        try {
            $requirement = Requirement::create([
                'user_id' => auth()->id(),
                'subject' => $validated['subject'],
                'description' => $validated['description'] ?? null,
                'media' => $validated['media'] ?? [],
                'region_filter' => $validated['region_filter'] ?? [],
                'category_filter' => $validated['category_filter'] ?? [],
                'status' => 'open',
            ]);

            $requirement->load('user');
            $this->createRequirementPostIfMissing($requirement);

            $notifiedCount = 0;
            try {
                $notifiedCount = $this->requirementNotificationService->notifyRequirementCreated($requirement);
            } catch (Throwable $exception) {
                Log::error('Requirement notification failed', [
                    'requirement_id' => (string) $requirement->id,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            $postId = $this->resolveTimelinePostId('requirement', (string) $requirement->id);

            return response()->json([
                'status' => true,
                'message' => 'Requirement created',
                'data' => array_merge($requirement->toArray(), ['post_id' => $postId]),
                'meta' => [
                    'notified_count' => $notifiedCount,
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Requirement create failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Server error',
                'data' => null,
                'meta' => null,
            ], 500);
        }
    }

    private function resolveTimelinePostId(string $sourceType, string $sourceId): ?string
    {
        return Post::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_deleted', false)
            ->latest('created_at')
            ->value('id');
    }

    public function myIndex(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $paginated = Requirement::query()
            ->with('user')
            ->withCount('interests')
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'My requirements fetched successfully.',
            'data' => RequirementDetailResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function incompleted(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        $paginated = Requirement::query()
            ->with(['user' => function ($query): void {
                $query->select(
                    'id',
                    'first_name',
                    'last_name',
                    'display_name',
                    'company_name',
                    'designation',
                    'profile_photo_url',
                    'profile_photo_file_id',
                    'membership_status'
                );
            }])
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereRaw('LOWER(status) <> ?', ['completed']);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $pagination = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Incomplete requirements fetched successfully.',
            'data' => [
                'requirements' => IncompleteRequirementResource::collection($paginated->items()),
                'pagination' => $pagination,
            ],
            'meta' => $pagination,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $requirement = Requirement::with('user')->findOrFail($id);
            $authUserId = (string) $request->user()->id;
            $isCreator = (string) $requirement->user_id === $authUserId;

            if (! $isCreator && (string) $requirement->status !== 'open') {
                return response()->json([
                    'status' => false,
                    'message' => 'Requirement not found',
                    'data' => null,
                    'meta' => null,
                ], 404);
            }

            $data = [
                'id' => (string) $requirement->id,
                'subject' => $requirement->subject,
                'description' => $requirement->description,
                'media' => $requirement->media ?? [],
                'region_filter' => $requirement->region_filter ?? [],
                'category_filter' => $requirement->category_filter ?? [],
                'status' => $requirement->status,
                'created_at' => optional($requirement->created_at)?->toISOString(),
                'user' => [
                    'id' => (string) $requirement->user?->id,
                    'display_name' => $requirement->user?->display_name,
                    'company_name' => $requirement->user?->company_name,
                    'city' => $requirement->user?->city,
                    'profile_photo_url' => $requirement->user?->profile_photo_url,
                ],
            ];

            if ($isCreator) {
                $interests = RequirementInterest::with('user')
                    ->where('requirement_id', $requirement->id)
                    ->orderByDesc('created_at')
                    ->get();

                $data['interested_peers'] = $interests->map(function (RequirementInterest $interest): array {
                    return [
                        'user_id' => (string) $interest->user_id,
                        'name' => $interest->user?->display_name,
                        'company' => $interest->user?->company_name,
                        'city' => $interest->user?->city,
                        'source' => $interest->source,
                        'comment' => $interest->comment,
                        'created_at' => optional($interest->created_at)?->toISOString(),
                    ];
                })->values()->all();
            }

            return response()->json([
                'status' => true,
                'message' => 'Requirement fetched successfully',
                'data' => $data,
                'meta' => null,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'Requirement not found',
                'data' => null,
                'meta' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Requirement show failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => (string) $request->user()->id,
                'requirement_id' => (string) $id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Server error',
                'data' => null,
                'meta' => null,
            ], 500);
        }
    }

    public function close(Request $request, $id): JsonResponse
    {
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

        try {
            $requirement = Requirement::query()->findOrFail($id);

            /** @var User $currentUser */
            $currentUser = $request->user() ?? auth()->user();

            if ((string) $requirement->user_id !== (string) $currentUser->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Forbidden',
                    'data' => null,
                    'meta' => null,
                ], 403);
            }

            $outcome = (string) ($validated['outcome'] ?? ($validated['status'] === 'completed' ? 'deal_closed' : 'met_no_deal'));
            $finalStatus = $outcome === 'deal_closed' ? 'completed' : ($validated['status'] ?? 'closed');
            $businessValue = $validated['business_value'] ?? null;
            $addToFacilitated = (bool) ($validated['add_to_facilitated_total'] ?? false);
            $thankYouNote = trim((string) ($validated['thank_you_note'] ?? $validated['story'] ?? $validated['notes'] ?? ''));
            $shareOnFeed = (bool) ($validated['share_on_feed'] ?? ($outcome === 'deal_closed'));

            // Resolve Giver
            $giverId = $validated['giver_user_id'] ?? $validated['giver_id'] ?? null;
            $giver = null;
            if ($giverId) {
                $giver = User::query()->find($giverId);
            }
            if (! $giver && Schema::hasTable('requirement_interests')) {
                try {
                    $giver = $requirement->interests()->latest('created_at')->first()?->user;
                } catch (Throwable) {
                    $giver = null;
                }
            }

            // Save status on requirement
            $requirement->status = $finalStatus;
            $requirement->save();

            // Store Business Deal if anonymous total or deal closed
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
                        'comment' => $thankYouNote !== '' ? $thankYouNote : "Deal closed for requirement: {$requirement->subject}",
                        'is_deleted' => false,
                    ]);
                } catch (Throwable $dealException) {
                    Log::warning('BusinessDeal creation failed on requirement close.', [
                        'error' => $dealException->getMessage(),
                    ]);
                }
            }

            $congratsPost = null;

            // Create congratulations timeline post if share_on_feed is true
            if ($shareOnFeed && $outcome === 'deal_closed') {
                $authorName = $currentUser->display_name ?: trim(($currentUser->first_name ?? '').' '.($currentUser->last_name ?? ''));
                $giverName = $giver ? ($giver->display_name ?: trim(($giver->first_name ?? '').' '.($giver->last_name ?? ''))) : 'Peer Member';

                $postContent = "🎉 Congratulations!\n\n";
                if ($giver) {
                    $postContent .= "A deal has been closed between @[{$authorName}]({$currentUser->id}) and @[{$giverName}]({$giver->id}) for requirement: \"{$requirement->subject}\".";
                } else {
                    $postContent .= "A deal has been closed by @[{$authorName}]({$currentUser->id}) for requirement: \"{$requirement->subject}\".";
                }

                if ($thankYouNote !== '') {
                    $postContent .= "\n\n\"{$thankYouNote}\"";
                }

                try {
                    $congratsPost = Post::create([
                        'user_id' => $currentUser->id,
                        'title' => "Deal Closed: {$requirement->subject}",
                        'content_text' => $postContent,
                        'media' => [],
                        'tags' => ['deal_closed', 'congratulations', 'requirement'],
                        'visibility' => 'public',
                        'moderation_status' => 'approved',
                        'sponsored' => false,
                        'is_deleted' => false,
                        'active' => true,
                        'source_type' => 'requirement',
                        'source_id' => $requirement->id,
                        'source_event' => 'completed',
                        'post_type' => 'deal_closed',
                    ]);

                    if ($giver) {
                        PostMention::create([
                            'post_id' => $congratsPost->id,
                            'peer_id' => $giver->id,
                        ]);
                    }
                } catch (Throwable $postException) {
                    Log::warning('Congratulations post creation failed on requirement close.', [
                        'error' => $postException->getMessage(),
                    ]);
                }
            }

            // Notify Giver
            if ($giver) {
                try {
                    $this->requirementNotificationService->notifyRequirementCompleted($requirement, $giver, $thankYouNote !== '' ? $thankYouNote : null);
                } catch (Throwable $notificationException) {
                    Log::warning('Requirement close notification failed.', [
                        'requirement_id' => (string) $requirement->id,
                        'error' => $notificationException->getMessage(),
                    ]);
                }
            }

            $postId = $congratsPost?->id ?? $this->resolveTimelinePostId('requirement', (string) $requirement->id);

            return response()->json([
                'status' => true,
                'message' => 'Requirement closed and thank you story posted to timeline.',
                'data' => [
                    'id' => (string) $requirement->id,
                    'status' => $requirement->status,
                    'outcome' => $outcome,
                    'business_value' => $businessValue,
                    'add_to_facilitated_total' => $addToFacilitated,
                    'thank_you_note' => $thankYouNote !== '' ? $thankYouNote : null,
                    'share_on_feed' => $shareOnFeed,
                    'post_id' => $postId ? (string) $postId : null,
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
                'meta' => null,
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'Requirement not found',
                'data' => null,
                'meta' => null,
            ], 404);
        } catch (Throwable $e) {
            Log::error('Requirement close failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'user_id' => auth()->id(),
                'requirement_id' => (string) $id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Server error',
                'data' => null,
                'meta' => null,
            ], 500);
        }
    }

    public function summary(Request $request, ?string $userId = null): JsonResponse
    {
        $targetUserId = $userId ?: $request->user()->id;

        $user = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'company_name',
                'designation',
                'profile_photo_url',
            ])
            ->where('id', $targetUserId)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        $givenRequirements = Requirement::query()
            ->where('user_id', $targetUserId)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'user' => $user,
                'total_requirements' => $givenRequirements,
                'given_requirements' => $givenRequirements,
                'received_requirements' => 0,
            ],
        ]);
    }
}
