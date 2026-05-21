<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ActivityCreative;
use App\Models\Role;
use App\Services\ActivityCreativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ActivityCreativeListController extends BaseApiController
{
    public function __construct(private readonly ActivityCreativeService $creativeService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request->user())) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->fetchCreatives($request);
    }

    public function my(Request $request): JsonResponse
    {
        return $this->fetchCreatives($request, (string) $request->user()->id);
    }

    public function byUser(Request $request, string $userId): JsonResponse
    {
        $authId = (string) $request->user()->id;
        if ($authId !== $userId && ! $this->isAdmin($request->user())) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->fetchCreatives($request, $userId);
    }

    private function fetchCreatives(Request $request, ?string $forcedUserId = null): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'activity_type' => ['nullable', 'string', 'max:50'],
                'user_id' => ['nullable', 'uuid'],
                'from_date' => ['nullable', 'date'],
                'to_date' => ['nullable', 'date'],
                'search' => ['nullable', 'string', 'max:255'],
            ]);

            $perPage = (int) ($validated['per_page'] ?? 20);
            $query = ActivityCreative::query()->with(['user:id,display_name,company_name,city,profile_photo_url']);

            $filterUserId = $forcedUserId ?? ($validated['user_id'] ?? null);
            if ($filterUserId) {
                $query->where('user_id', $filterUserId);
            }

            if (! empty($validated['activity_type'])) {
                $query->where('activity_type', $this->creativeService->normalizeActivityType((string) $validated['activity_type']));
            }

            if (! empty($validated['from_date'])) {
                $query->whereDate('created_at', '>=', $validated['from_date']);
            }

            if (! empty($validated['to_date'])) {
                $query->whereDate('created_at', '<=', $validated['to_date']);
            }

            if (! empty($validated['search'])) {
                $search = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $validated['search']) . '%';
                $query->where(function ($q) use ($search): void {
                    $q->where('creative_title', 'ILIKE', $search)
                        ->orWhere('creative_text', 'ILIKE', $search);
                });
            }

            $paginator = $query->orderByDesc('created_at')->paginate($perPage);

            $items = collect($paginator->items())->map(function (ActivityCreative $creative): array {
                return [
                    'id' => (string) $creative->id,
                    'activity_type' => (string) $creative->activity_type,
                    'activity_id' => (string) $creative->activity_id,
                    'user_id' => (string) $creative->user_id,
                    'user' => [
                        'id' => (string) data_get($creative, 'user.id'),
                        'display_name' => data_get($creative, 'user.display_name'),
                        'company_name' => data_get($creative, 'user.company_name'),
                        'city' => data_get($creative, 'user.city'),
                        'profile_photo_url' => data_get($creative, 'user.profile_photo_url'),
                    ],
                    'creative_title' => (string) $creative->creative_title,
                    'creative_text' => (string) $creative->creative_text,
                    'download_url' => $this->creativeService->buildDownloadUrl((string) $creative->activity_type, (string) $creative->activity_id),
                    'downloaded_count' => (int) $creative->downloaded_count,
                    'last_downloaded_at' => optional($creative->last_downloaded_at)?->toISOString(),
                    'created_at' => optional($creative->created_at)?->toISOString(),
                ];
            })->values()->all();

            return $this->success([
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], 'Activity creatives fetched successfully.');
        } catch (Throwable $e) {
            return $this->error('Unable to fetch activity creatives', 500);
        }
    }

    private function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $allowedAdminRoleKeys = [
            'global_admin',
            'super_admin',
            'district_executive_director',
            'industry_director',
            'circle_founder',
            'circle_director',
        ];
        $roleIds = Role::query()->whereIn('key', $allowedAdminRoleKeys)->pluck('id');

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}
