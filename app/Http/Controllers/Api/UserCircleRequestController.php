<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserCircleRequest\SubmitUserCircleRequest;
use App\Http\Requests\Api\UserCircleRequest\UpdateUserCircleRequestNominees;
use App\Models\CircleCategory;
use App\Models\UserCircleRequest;
use App\Models\UserCircleRequestNominee;
use App\Services\Circles\UserCircleNomineeStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserCircleRequestController extends BaseApiController
{
    public function __construct(
        private readonly UserCircleNomineeStatusResolver $nomineeResolver,
    ) {}
    /**
     * Get minimum nominees required (dynamic config) and Level 1 Categories.
     */
    public function config(Request $request): JsonResponse
    {
        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        $categories = CircleCategory::query()
            ->where('level', 1)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'circle_key']);

        return $this->success([
            'minimum_recommended_peers_required' => $minimum,
            'minimum_nominees_required'          => $minimum, // alias
            'recommended_peers_required'         => $minimum,
            'recommended_nominees'               => $minimum, // alias
            'message'                            => "You need to recommend at least {$minimum} peers to build your own circle.",
            'categories'                         => $categories,
        ], 'Circle build configuration fetched.');
    }

    /**
     * Submit a new "Build Your Own Circle" request.
     */
    public function store(SubmitUserCircleRequest $request): JsonResponse
    {
        $user = $request->user();
        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        // Check if user already has a pending/under_review request
        $existing = UserCircleRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->first();

        if ($existing) {
            return $this->error('You already have a pending circle request. Please wait for it to be reviewed.', 422);
        }

        $validated = $request->validated();
        $peers     = $validated['recommended_peers'] ?? $validated['nominees'] ?? [];

        $circleRequest = DB::transaction(function () use ($user, $validated, $peers, $minimum): UserCircleRequest {
            $req = UserCircleRequest::create([
                'user_id'                 => $user->id,
                'circle_name'             => $validated['circle_name'],
                'category_id'             => $validated['category_id'] ?? null,
                'description'             => $validated['description'] ?? null,
                'city'                    => $validated['city'] ?? null,
                'country'                 => $validated['country'] ?? 'India',
                'meeting_mode'            => $validated['meeting_mode'] ?? null,
                'meeting_frequency'       => $validated['meeting_frequency'] ?? null,
                'status'                  => UserCircleRequest::STATUS_PENDING,
                'minimum_peers_required'  => $minimum,
            ]);

            foreach ($peers as $peer) {
                UserCircleRequestNominee::create([
                    'user_circle_request_id' => $req->id,
                    'nominee_user_id'        => $peer['user_id'] ?? $peer['nominee_user_id'] ?? null,
                    'name'                   => $peer['name'],
                    'phone'                  => $peer['phone'] ?? null,
                    'email'                  => $peer['email'] ?? null,
                ]);
            }

            return $req;
        });

        $circleRequest->load('nominees');
        $count = $circleRequest->nominees->count();

        return $this->success([
            'request'                        => $circleRequest,
            'recommended_peers_count'        => $count,
            'nominees_count'                 => $count, // alias
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'has_minimum_nominees'          => $count >= $minimum, // alias
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'remaining_nominees'            => max(0, $minimum - $count), // alias
        ], 'Circle request submitted successfully.', 201);
    }

    /**
     * Get my circle requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'nominees'])
            ->withCount('nominees')
            ->orderByDesc('created_at')
            ->get();

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        return $this->success([
            'items'              => $requests->map(fn (UserCircleRequest $r) => $this->formatRequest($r, $minimum)),
            'minimum_required'   => $minimum,
        ], 'My circle requests fetched.');
    }

    /**
     * Show a single circle request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'nominees'])
            ->withCount('nominees')
            ->findOrFail($id);

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        return $this->success($this->formatRequest($circleRequest, $minimum));
    }

    /**
     * Add/replace recommended peers for an existing pending request.
     */
    public function updateNominees(UpdateUserCircleRequestNominees $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->findOrFail($id);

        $validated = $request->validated();
        $peers     = $validated['recommended_peers'] ?? $validated['nominees'] ?? [];
        $minimum   = (int) config('circle.build_your_own.minimum_nominees', 40);

        DB::transaction(function () use ($circleRequest, $peers): void {
            // Replace all existing entries
            UserCircleRequestNominee::where('user_circle_request_id', $circleRequest->id)->delete();

            foreach ($peers as $peer) {
                UserCircleRequestNominee::create([
                    'user_circle_request_id' => $circleRequest->id,
                    'nominee_user_id'        => $peer['user_id'] ?? $peer['nominee_user_id'] ?? null,
                    'name'                   => $peer['name'],
                    'phone'                  => $peer['phone'] ?? null,
                    'email'                  => $peer['email'] ?? null,
                ]);
            }
        });

        $circleRequest->load('nominees');
        $count = $circleRequest->nominees->count();

        return $this->success([
            'request'                        => $circleRequest,
            'recommended_peers_count'        => $count,
            'nominees_count'                 => $count, // alias
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'has_minimum_nominees'          => $count >= $minimum, // alias
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'remaining_nominees'            => max(0, $minimum - $count), // alias
        ], 'Recommended peers updated successfully.');
    }

    /**
     * Cancel my own request.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [UserCircleRequest::STATUS_PENDING, UserCircleRequest::STATUS_UNDER_REVIEW])
            ->findOrFail($id);

        $circleRequest->status = UserCircleRequest::STATUS_REJECTED;
        $circleRequest->save();

        return $this->success(null, 'Circle request cancelled.');
    }

    private function formatRequest(UserCircleRequest $r, int $minimum): array
    {
        $count = $r->nominees_count ?? $r->nominees->count();
        $resolvedNominees = $this->nomineeResolver->resolveNominees($r);
        $summary = $this->nomineeResolver->getSummary($r, $resolvedNominees);

        return [
            'id'                   => $r->id,
            'circle_name'          => $r->circle_name,
            'category_id'          => $r->category_id,
            'category'             => $r->category ? [
                'id'         => $r->category->id,
                'name'       => $r->category->name,
                'slug'       => $r->category->slug,
                'circle_key' => $r->category->circle_key,
            ] : null,
            'description'          => $r->description,
            'city'                 => $r->city,
            'country'              => $r->country,
            'meeting_mode'         => $r->meeting_mode,
            'meeting_frequency'    => $r->meeting_frequency,
            'status'               => $r->status,
            'recommended_peers_count'        => $count,
            'nominees_count'                 => $count, // alias
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'has_minimum_nominees'          => $count >= $minimum, // alias
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'remaining_nominees'            => max(0, $minimum - $count), // alias
            'summary'                        => $summary,
            'recommended_peers_joined_unity_count'  => $summary['joined_unity_count'],
            'recommended_peers_joined_circle_count' => $summary['joined_circle_count'],
            'created_circle_id'    => $r->created_circle_id,
            'circle_created_at'    => $r->circle_created_at?->toISOString(),
            'recommended_peers'    => $resolvedNominees,
            'nominees'             => $resolvedNominees, // alias
            'created_at'           => $r->created_at?->toISOString(),
            'updated_at'           => $r->updated_at?->toISOString(),
        ];
    }
}
