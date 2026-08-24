<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CircleCategoryUsageController extends Controller
{
    public function circleCategoryTree(string $circleId): JsonResponse
    {
        $circle = Circle::query()->where('id', $circleId)->first();

        if (! $circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);

        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'category' => null,
                    'children' => [],
                ],
            ]);
        }

        $mainCategory = CircleCategory::query()
            ->select(['id', 'name', 'slug', 'circle_key'])
            ->where('id', $mainCategoryId)
            ->first();

        if (! $mainCategory) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'category' => null,
                    'children' => [],
                ],
            ]);
        }

        $level2 = CircleCategoryLevel2::query()
            ->where('circle_category_id', $mainCategory->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $level2Ids = $level2->pluck('id')->values();

        $level3 = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level2_id']);

        $level3Ids = $level3->pluck('id')->values();

        $level4 = $level3Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel4::query()
                ->whereIn('level3_id', $level3Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level3_id']);

        $level4ByLevel3 = [];
        foreach ($level4 as $row) {
            $parentId = (int) ($row->level3_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level4ByLevel3[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 4,
            ];
        }

        $level3ByLevel2 = [];
        foreach ($level3 as $row) {
            $parentId = (int) ($row->level2_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level3ByLevel2[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 3,
                'children' => $level4ByLevel3[$row->id] ?? [],
            ];
        }

        $children = [];
        foreach ($level2 as $row) {
            $children[] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 2,
                'children' => $level3ByLevel2[$row->id] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'circle' => $this->formatCircleBasicDetails($circle),
                'category' => [
                    'id' => $mainCategory->id,
                    'name' => $mainCategory->name,
                    'slug' => $mainCategory->slug,
                    'circle_key' => $mainCategory->circle_key,
                ],
                'children' => $children,
            ],
        ]);
    }

    public function circleOpenCategories(Request $request, string $circleId): JsonResponse
    {
        $circle = Circle::query()->where('id', $circleId)->first();

        if (! $circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);

        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'message' => 'Open categories fetched successfully.',
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'level1_category' => null,
                    'open_categories' => [],
                ],
            ]);
        }

        $mainCategory = CircleCategory::query()
            ->select(['id', 'name', 'slug', 'circle_key'])
            ->where('id', $mainCategoryId)
            ->first();

        $closedMap = $this->getClosedLevel4CategoriesMap($circle->id);
        $closedLevel4Ids = array_keys($closedMap);

        $level2 = CircleCategoryLevel2::query()
            ->where('circle_category_id', $mainCategoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $level2Ids = $level2->pluck('id')->values();

        $level3 = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level2_id']);

        $level3Ids = $level3->pluck('id')->values();

        $level4Query = CircleCategoryLevel4::query()
            ->whereIn('level3_id', $level3Ids)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($closedLevel4Ids !== []) {
            $level4Query->whereNotIn('id', $closedLevel4Ids);
        }

        $level4 = $level3Ids->isEmpty()
            ? collect()
            : $level4Query->get(['id', 'name', 'level3_id']);

        $level4ByLevel3 = [];
        foreach ($level4 as $row) {
            $parentId = (int) ($row->level3_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level4ByLevel3[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 4,
                'is_closed' => false,
            ];
        }

        $level3ByLevel2 = [];
        foreach ($level3 as $row) {
            $parentId = (int) ($row->level2_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level3ByLevel2[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 3,
                'children' => $level4ByLevel3[$row->id] ?? [],
            ];
        }

        $openCategories = [];
        foreach ($level2 as $row) {
            $openCategories[] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 2,
                'children' => $level3ByLevel2[$row->id] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Open categories fetched successfully.',
            'data' => [
                'circle' => $this->formatCircleBasicDetails($circle),
                'level1_category' => $mainCategory ? [
                    'id' => $mainCategory->id,
                    'name' => $mainCategory->name,
                    'slug' => $mainCategory->slug,
                    'circle_key' => $mainCategory->circle_key,
                ] : null,
                'open_categories' => $openCategories,
            ],
        ]);
    }

    public function circleClosedCategories(Request $request, string $circleId): JsonResponse
    {
        $circle = Circle::query()->where('id', $circleId)->first();

        if (! $circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);

        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'message' => 'Closed categories fetched successfully.',
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'level1_category' => null,
                    'closed_categories' => [],
                ],
            ]);
        }

        $mainCategory = CircleCategory::query()
            ->select(['id', 'name', 'slug', 'circle_key'])
            ->where('id', $mainCategoryId)
            ->first();

        $closedMap = $this->getClosedLevel4CategoriesMap($circle->id);
        $closedLevel4Ids = array_keys($closedMap);

        if ($closedLevel4Ids === []) {
            return response()->json([
                'success' => true,
                'message' => 'Closed categories fetched successfully.',
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'level1_category' => $mainCategory ? [
                        'id' => $mainCategory->id,
                        'name' => $mainCategory->name,
                        'slug' => $mainCategory->slug,
                        'circle_key' => $mainCategory->circle_key,
                    ] : null,
                    'closed_categories' => [],
                ],
            ]);
        }

        $closedLevel4Records = CircleCategoryLevel4::query()
            ->whereIn('id', $closedLevel4Ids)
            ->with(['level3Category.level2Category'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $closedCategories = $closedLevel4Records->map(function (CircleCategoryLevel4 $row) use ($closedMap): array {
            $peers = $closedMap[$row->id] ?? [];

            return [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 4,
                'is_closed' => true,
                'parent_level3' => $row->level3Category ? [
                    'id' => $row->level3Category->id,
                    'name' => $row->level3Category->name,
                ] : null,
                'parent_level2' => $row->level3Category?->level2Category ? [
                    'id' => $row->level3Category->level2Category->id,
                    'name' => $row->level3Category->level2Category->name,
                ] : null,
                'occupied_by' => $peers,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Closed categories fetched successfully.',
            'data' => [
                'circle' => $this->formatCircleBasicDetails($circle),
                'level1_category' => $mainCategory ? [
                    'id' => $mainCategory->id,
                    'name' => $mainCategory->name,
                    'slug' => $mainCategory->slug,
                    'circle_key' => $mainCategory->circle_key,
                ] : null,
                'closed_categories' => $closedCategories,
            ],
        ]);
    }

    public function memberSelectedCategories(string $memberId): JsonResponse
    {
        $member = User::query()->select(['id'])->where('id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'data' => null,
            ], 404);
        }

        if (! Schema::hasTable('joined_circle_categories')) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => ['items' => []],
            ]);
        }

        $rows = JoinedCircleCategory::query()
            ->where('user_id', $member->id)
            ->with([
                'circle:id,name',
                'level1Category:id,name',
                'level2Category:id,name',
                'level3Category:id,name',
                'level4Category:id,name',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $items = $rows->map(function (JoinedCircleCategory $row): array {
            return [
                'circle' => [
                    'id' => $row->circle_id,
                    'name' => $row->circle?->name,
                ],
                'level1_category' => $row->level1Category
                    ? ['id' => $row->level1Category->id, 'name' => $row->level1Category->name]
                    : null,
                'level2_category' => $row->level2Category
                    ? ['id' => $row->level2Category->id, 'name' => $row->level2Category->name]
                    : null,
                'level3_category' => $row->level3Category
                    ? ['id' => $row->level3Category->id, 'name' => $row->level3Category->name]
                    : null,
                'level4_category' => $row->level4Category
                    ? ['id' => $row->level4Category->id, 'name' => $row->level4Category->name]
                    : null,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function memberAvailableCategories(Request $request, string $memberId): JsonResponse
    {
        $member = User::query()->select(['id'])->where('id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Member not found.',
                'data' => null,
            ], 404);
        }

        $circleId = (string) $request->query('circle_id', '');
        if ($circleId === '') {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'circle_id is required.',
                'data' => null,
            ], 422);
        }

        $circle = Circle::query()->where('id', $circleId)->first();
        if (! $circle) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);
        $mainCategory = null;
        if ($mainCategoryId) {
            $mainCategory = CircleCategory::query()
                ->select(['id', 'name', 'slug', 'circle_key'])
                ->where('id', $mainCategoryId)
                ->first();
        }

        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Available categories fetched successfully',
                'data' => [
                    'circle' => $this->formatCircleBasicDetails($circle),
                    'level1_category' => null,
                    'available_categories' => [],
                ],
            ]);
        }

        $closedMap = $this->getClosedLevel4CategoriesMap($circle->id);

        $level2Ids = CircleCategoryLevel2::query()
            ->where('circle_category_id', $mainCategoryId)
            ->pluck('id');

        $level3Ids = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->pluck('id');

        $level4Categories = CircleCategoryLevel4::query()
            ->where(function ($q) use ($level3Ids, $mainCategoryId): void {
                if ($level3Ids->isNotEmpty()) {
                    $q->whereIn('level3_id', $level3Ids);
                }
                $q->orWhere('circle_category_id', $mainCategoryId);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $availableCategories = $level4Categories->map(function (CircleCategoryLevel4 $row) use ($closedMap): array {
            $l4Id = (int) $row->id;
            $peers = $closedMap[$l4Id] ?? [];
            $isClosed = ! empty($peers);

            return [
                'id' => $l4Id,
                'name' => $row->name,
                'level' => 4,
                'is_closed' => $isClosed,
                'occupied_by' => $peers,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Available categories fetched successfully',
            'data' => [
                'circle' => $this->formatCircleBasicDetails($circle),
                'level1_category' => $mainCategory ? [
                    'id' => $mainCategory->id,
                    'name' => $mainCategory->name,
                    'slug' => $mainCategory->slug,
                    'circle_key' => $mainCategory->circle_key,
                ] : null,
                'available_categories' => $availableCategories,
            ],
        ]);
    }

    /**
     * Format circle basic details for API responses.
     *
     * @return array<string, mixed>
     */
    private function formatCircleBasicDetails(Circle $circle): array
    {
        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'slug' => $circle->slug,
            'description' => $circle->description,
            'purpose' => $circle->purpose,
            'status' => $circle->status,
            'type' => $circle->type,
            'city' => $circle->city_display ?? (is_string($circle->city) ? $circle->city : null),
            'country' => $circle->country,
            'meeting_mode' => $circle->meeting_mode,
            'meeting_frequency' => $circle->meeting_frequency,
            'cover_image_url' => $circle->cover_image_url,
            'cover_photo_url' => $circle->cover_image_url,
            'cover_file_id' => $circle->cover_file_id,
            'circle_image_file_id' => $circle->circle_image_file_id,
            'circle_image_url' => $circle->circle_image_url,
            'cover_image' => $circle->cover_file_id ? [
                'id' => (string) $circle->cover_file_id,
                'url' => $circle->cover_image_url,
            ] : null,
            'circle_image' => $circle->circle_image_file_id ? [
                'id' => (string) $circle->circle_image_file_id,
                'url' => $circle->circle_image_url,
            ] : null,
        ];
    }

    /**
     * Get array of closed (occupied) level4 category details for a given circle.
     * Returns an associative array mapping level4_category_id => list of occupied peer details.
     *
     * @return array<int, list<array{user_id: string|null, user_name: string|null, company_name: string|null, occupied_at: string|null}>>
     */
    private function getClosedLevel4CategoriesMap(string $circleId): array
    {
        $closedMap = [];

        if (Schema::hasTable('joined_circle_categories')) {
            $rows = JoinedCircleCategory::query()
                ->where('circle_id', $circleId)
                ->whereNotNull('level4_category_id')
                ->where('level4_category_id', '>', 0)
                ->with(['user:id,first_name,last_name,display_name,company_name'])
                ->orderBy('created_at')
                ->get();

            foreach ($rows as $row) {
                $l4Id = (int) $row->level4_category_id;
                if ($l4Id <= 0) {
                    continue;
                }

                $u = $row->user;
                $userName = trim((string) ($u?->display_name ?? ''));
                if ($userName === '') {
                    $userName = trim(($u?->first_name ?? '').' '.($u?->last_name ?? ''));
                }

                $occupiedAt = $row->created_at
                    ? $row->created_at->toIso8601String()
                    : ($row->updated_at ? $row->updated_at->toIso8601String() : null);

                $userId = $row->user_id ? (string) $row->user_id : null;

                $peerData = [
                    'user_id' => $userId,
                    'user_name' => $userName !== '' ? $userName : null,
                    'company_name' => $u?->company_name ?? null,
                    'occupied_at' => $occupiedAt,
                ];

                if (! isset($closedMap[$l4Id])) {
                    $closedMap[$l4Id] = [];
                }

                $exists = false;
                foreach ($closedMap[$l4Id] as $existing) {
                    if ($userId && $existing['user_id'] === $userId) {
                        $exists = true;
                        break;
                    }
                }

                if (! $exists) {
                    $closedMap[$l4Id][] = $peerData;
                }
            }
        }

        if (Schema::hasTable('circle_members') && Schema::hasColumn('circle_members', 'level_4_category_id')) {
            $members = DB::table('circle_members')
                ->where('circle_id', $circleId)
                ->whereNull('deleted_at')
                ->whereNotNull('level_4_category_id')
                ->where('level_4_category_id', '>', 0)
                ->orderBy('created_at')
                ->get(['id', 'user_id', 'level_4_category_id', 'created_at']);

            $userIds = $members->pluck('user_id')->filter()->unique()->values()->all();
            $users = [];
            if ($userIds !== []) {
                $users = User::query()
                    ->whereIn('id', $userIds)
                    ->get(['id', 'first_name', 'last_name', 'display_name', 'company_name'])
                    ->keyBy(fn ($u) => (string) $u->id);
            }

            foreach ($members as $m) {
                $l4Id = (int) $m->level_4_category_id;
                if ($l4Id <= 0) {
                    continue;
                }

                $u = $m->user_id ? ($users[(string) $m->user_id] ?? null) : null;
                $userName = trim((string) ($u?->display_name ?? ''));
                if ($userName === '') {
                    $userName = trim(($u?->first_name ?? '').' '.($u?->last_name ?? ''));
                }

                $occupiedAt = null;
                if (isset($m->created_at) && $m->created_at) {
                    try {
                        $occupiedAt = Carbon::parse($m->created_at)->toIso8601String();
                    } catch (\Throwable) {
                        $occupiedAt = null;
                    }
                }

                $userId = $m->user_id ? (string) $m->user_id : null;

                $peerData = [
                    'user_id' => $userId,
                    'user_name' => $userName !== '' ? $userName : null,
                    'company_name' => $u?->company_name ?? null,
                    'occupied_at' => $occupiedAt,
                ];

                if (! isset($closedMap[$l4Id])) {
                    $closedMap[$l4Id] = [];
                }

                $exists = false;
                foreach ($closedMap[$l4Id] as $existing) {
                    if ($userId && $existing['user_id'] === $userId) {
                        $exists = true;
                        break;
                    }
                }

                if (! $exists) {
                    $closedMap[$l4Id][] = $peerData;
                }
            }
        }

        return $closedMap;
    }

    private function resolveCircleMainCategoryId(string $circleId): ?int
    {
        if (! Schema::hasTable('circle_category_mappings')) {
            return null;
        }

        $id = DB::table('circle_category_mappings')
            ->where('circle_id', $circleId)
            ->orderBy('id')
            ->value('category_id');

        return $id ? (int) $id : null;
    }
}
