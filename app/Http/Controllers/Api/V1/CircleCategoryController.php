<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CircleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CircleCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CircleCategory::query()
            ->select([
                'id',
                'name',
                'slug',
                'circle_key',
                'level',
                'sort_order',
                'is_active',
            ])
            ->where('level', 1)
            ->where('is_active', true)
            ->withCount([
                'level2Categories as child_level2_count',
                'level3Categories as child_level3_count',
                'level4Categories as child_level4_count',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function show(Request $request, string $idOrSlug): JsonResponse
    {
        $categoryQuery = CircleCategory::query()->where('level', 1);

        if (ctype_digit($idOrSlug)) {
            $categoryQuery->where('id', (int) $idOrSlug);
        } else {
            $categoryQuery->where('slug', $idOrSlug);
        }

        $category = $categoryQuery->first();

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Circle category not found.',
                'data' => null,
            ], 404);
        }

        $perPage = (int) ($request->input('per_page') ?? $request->query('per_page', 30));
        if ($perPage <= 0) {
            $perPage = 30;
        }

        $search = trim((string) ($request->input('search') ?? $request->input('q') ?? $request->input('keyword') ?? ''));

        $subcategoriesQuery = $category->level4Categories();

        if ($search !== '') {
            $subcategoriesQuery->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        $paginated = $subcategoriesQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        $pagination = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'has_more' => $paginated->hasMorePages(),
        ];

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'circle_key' => $category->circle_key,
                'level' => $category->level,
                'sort_order' => $category->sort_order,
                'is_active' => (bool) $category->is_active,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'counts' => [
                    'level4' => $paginated->total(),
                    'total_children' => $paginated->total(),
                ],
                'pagination' => $pagination,
                'level4_categories' => $paginated->items(),
            ],
            'pagination' => $pagination,
        ]);
    }
}

