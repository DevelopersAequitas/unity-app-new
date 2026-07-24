<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Ads\IndexAdRequest;
use App\Http\Resources\V1\AdListResource;
use App\Http\Resources\V1\AdResource;
use App\Models\Ad;
use App\Models\AdClick;
use App\Models\AdView;
use App\Services\AdFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdController extends BaseApiController
{
    public function myAds(Request $request)
    {
        $ads = Ad::query()
            ->where('created_by', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Ad $ad): array => [
                'id' => $ad->id,
                'user_id' => $ad->created_by,
                'title' => $ad->title,
                'description' => $ad->description,
                'image_url' => $ad->image_url,
                'status' => $ad->is_active ? 'active' : 'inactive',
                'created_at' => $ad->created_at,
                'updated_at' => $ad->updated_at,
            ]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $ads->isEmpty() ? 'No ads found.' : 'Ads fetched successfully.',
            'data' => $ads->values(),
            'meta' => null,
        ]);
    }

    /**
     * GET /api/ads  — returns ALL currently active/visible ads for every user.
     * This is the backward-compatible public listing used by the mobile app.
     */
    public function allAds(Request $request)
    {
        $ads = Ad::query()
            ->currentlyVisible()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Ad $ad): array => [
                'id' => $ad->id,
                'user_id' => $ad->created_by,
                'title' => $ad->title,
                'description' => $ad->description,
                'image_url' => $ad->image_url,
                'status' => $ad->is_active ? 'active' : 'inactive',
                'created_at' => $ad->created_at,
                'updated_at' => $ad->updated_at,
            ]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $ads->isEmpty() ? 'No ads found.' : 'Ads fetched successfully.',
            'data' => $ads->values(),
            'meta' => null,
        ]);
    }

    public function index(IndexAdRequest $request)
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $ads = Ad::query()
            ->currentlyVisible()
            ->when(! empty($filters['page_name']), fn ($query) => $query->where('page_name', $filters['page_name']))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->success([
            'items' => AdResource::collection($ads->getCollection()),
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }

    public function publicIndex()
    {
        $ads = Ad::query()
            ->currentlyVisible()
            ->orderByDesc('created_at')
            ->get();

        if ($ads->isEmpty()) {
            return $this->success([], 'No ads found');
        }

        return $this->success(AdListResource::collection($ads), 'Ads fetched successfully');
    }

    public function show(string $id)
    {
        $ad = Ad::query()->currentlyVisible()->find($id);

        if (! $ad) {
            return $this->error('Ad not found', 404);
        }

        return $this->success(AdResource::make($ad));
    }

    public function timeline(AdFeedService $adFeedService)
    {
        $ads = $adFeedService->timelineAds();

        return $this->success(AdResource::collection($ads));
    }

    public function view(Request $request, string $id): JsonResponse
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return $this->error('Ad not found.', 404);
        }

        $user = auth()->user() ?? auth('admin')->user();
        $userId = $user?->id;
        $ipAddress = $request->ip();
        $sessionId = null;
        try {
            if ($request->hasSession()) {
                $sessionId = $request->session()->getId();
            }
        } catch (\Throwable $e) {
        }

        // Skip logging if identical interaction occurred within 24 hours
        $exists = AdView::where('ad_id', $ad->id)
            ->where('viewed_at', '>=', now()->subHours(24))
            ->where(function ($query) use ($userId, $ipAddress, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where(function ($sub) use ($ipAddress, $sessionId) {
                        $sub->where('ip_address', $ipAddress);
                        if ($sessionId) {
                            $sub->orWhere('session_id', $sessionId);
                        }
                    });
                }
            })
            ->exists();

        if (! $exists) {
            AdView::create([
                'user_id' => $userId,
                'ad_id' => $ad->id,
                'ip_address' => $ipAddress,
                'session_id' => $sessionId,
                'viewed_at' => now(),
            ]);
        }

        return $this->success(null, 'Ad view event logged successfully.');
    }

    public function click(Request $request, string $id): JsonResponse
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return $this->error('Ad not found.', 404);
        }

        $clickType = $request->input('click_type', 'visit');

        $user = auth()->user() ?? auth('admin')->user();
        $userId = $user?->id;
        $ipAddress = $request->ip();
        $sessionId = null;
        try {
            if ($request->hasSession()) {
                $sessionId = $request->session()->getId();
            }
        } catch (\Throwable $e) {
        }

        // Skip logging if identical interaction occurred within 24 hours
        $exists = AdClick::where('ad_id', $ad->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->where(function ($query) use ($userId, $ipAddress, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where(function ($sub) use ($ipAddress, $sessionId) {
                        $sub->where('ip_address', $ipAddress);
                        if ($sessionId) {
                            $sub->orWhere('session_id', $sessionId);
                        }
                    });
                }
            })
            ->exists();

        if (! $exists) {
            AdClick::create([
                'user_id' => $userId,
                'ad_id' => $ad->id,
                'click_type' => $clickType,
                'ip' => $ipAddress,
                'ip_address' => $ipAddress,
                'session_id' => $sessionId,
                'device' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return $this->success(null, 'Ad click event logged successfully.');
    }
}
