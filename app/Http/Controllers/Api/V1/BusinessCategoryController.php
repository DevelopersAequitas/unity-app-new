<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessCategoryController extends BaseApiController
{
    public function main(): JsonResponse
    {
        if (Schema::hasTable('circle_categories')) {
            $query = DB::table('circle_categories')
                ->select(['id', 'name'])
                ->where(function ($q) {
                    $q->where('level', 1)
                        ->orWhereNull('parent_id');
                });

            if (Schema::hasColumn('circle_categories', 'is_active')) {
                $query->where(function ($q) {
                    $q->where('is_active', true)
                        ->orWhereNull('is_active');
                });
            }

            if (Schema::hasColumn('circle_categories', 'sort_order')) {
                $query->orderBy('sort_order');
            }

            $items = $query->orderBy('id')
                ->get()
                ->map(fn ($category): array => [
                    'id' => (int) $category->id,
                    'name' => trim(preg_replace('/\s+Circles?\b/i', '', (string) $category->name)),
                ])
                ->values();

            return $this->success($items, 'Main business categories fetched successfully.');
        }

        if (Schema::hasTable('business_categories')) {
            $query = DB::table('business_categories')->select(['id', 'name']);

            if (Schema::hasColumn('business_categories', 'is_active')) {
                $query->where('is_active', true);
            }

            if (Schema::hasColumn('business_categories', 'sort_order')) {
                $query->orderBy('sort_order');
            }

            $items = $query->orderBy('id')
                ->get()
                ->map(fn ($category): array => [
                    'id' => (int) $category->id,
                    'name' => trim(preg_replace('/\s+Circles?\b/i', '', (string) $category->name)),
                ])
                ->values();

            return $this->success($items, 'Main business categories fetched successfully.');
        }

        return $this->error('Business categories table not found.', 404);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        if (! Schema::hasTable('circle_categories')) {
            return $this->error('Circle categories table not found.', 404);
        }

        if (Schema::hasTable('business_categories') && ctype_digit($idOrSlug) && (int) $idOrSlug <= 11) {
            $businessCategory = DB::table('business_categories')->where('id', $idOrSlug)->first();
            if ($businessCategory) {
                $targetName = trim(preg_replace('/\s+Circles?\b/i', '', $businessCategory->name));
                $circleCategory = DB::table('circle_categories')
                    ->where('level', 1)
                    ->get()
                    ->first(fn ($cc): bool => strcasecmp(trim(preg_replace('/\s+Circles?\b/i', '', $cc->name)), $targetName) === 0);

                if ($circleCategory) {
                    $idOrSlug = (string) $circleCategory->id;
                }
            }
        }

        $categoryQuery = CircleCategory::query()->where('level', 1);

        if (ctype_digit($idOrSlug)) {
            $categoryQuery->where('id', (int) $idOrSlug);
        } else {
            $categoryQuery->where('slug', $idOrSlug);
        }

        $category = $categoryQuery->first();

        if (! $category) {
            return $this->error('Business category not found.', 404);
        }

        $category->name = trim(preg_replace('/\s+Circles?\b/i', '', $category->name));

        $level2Categories = $category->level2Categories()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level3Categories = $category->level3Categories()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level4Categories = $category->level4Categories()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level2Count = $level2Categories->count();
        $level3Count = $level3Categories->count();
        $level4Count = $level4Categories->count();

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
                    'level2' => $level2Count,
                    'level3' => $level3Count,
                    'level4' => $level4Count,
                    'total_children' => $level2Count + $level3Count + $level4Count,
                ],
                'level2_categories' => $level2Categories,
                'level3_categories' => $level3Categories,
                'level4_categories' => $level4Categories,
            ],
        ]);
    }

    public function children(string $parentId): JsonResponse
    {
        if (! Schema::hasTable('circle_categories')) {
            return $this->error('Circle categories table not found.', 404);
        }

        $otherOption = [
            'id' => 'other',
            'name' => 'Other',
            'is_other' => true,
            'parent_id' => is_numeric($parentId) ? (int) $parentId : $parentId,
        ];

        $level4Children = $this->level4ChildrenByCircleCategoryId($parentId);
        if ($level4Children->isNotEmpty()) {
            $level4Children->push($otherOption);

            return $this->success($level4Children, 'Business sub categories fetched successfully.');
        }

        $selfChildren = $this->selfReferencingChildren($parentId);
        if ($selfChildren->isNotEmpty()) {
            $selfChildren->push($otherOption);

            return $this->success($selfChildren, 'Business sub categories fetched successfully.');
        }

        $legacyChildren = $this->legacyLevelChildren($parentId);
        $legacyChildren->push($otherOption);

        return $this->success($legacyChildren, 'Business sub categories fetched successfully.');
    }

    private function level4ChildrenByCircleCategoryId(string $parentId)
    {
        $table = Schema::hasTable('circle_category_level4')
            ? 'circle_category_level4'
            : $this->level4CategoriesTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'circle_category_id')) {
            return collect();
        }

        $query = DB::table($table)
            ->select(['id', 'name'])
            ->where('circle_category_id', $parentId);

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhereNull('is_active');
            });
        }

        if (Schema::hasColumn($table, 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'level' => 4,
                'parent_id' => is_numeric($parentId) ? (int) $parentId : $parentId,
            ])
            ->values();
    }

    private function selfReferencingChildren(string $parentId)
    {
        if (! Schema::hasColumn('circle_categories', 'parent_id')) {
            return collect();
        }

        $query = DB::table('circle_categories')
            ->select(['id', 'name', 'level', 'parent_id'])
            ->where('parent_id', $parentId);

        if (Schema::hasColumn('circle_categories', 'is_active')) {
            $query->where('is_active', true);
        }

        return $this->orderCategoryQuery($query, 'circle_categories')
            ->get()
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'level' => (int) $category->level,
                'parent_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
            ])
            ->values();
    }

    private function legacyLevelChildren(string $parentId)
    {
        $parent = DB::table('circle_categories')->where('id', $parentId)->first();
        if ($parent) {
            $level4 = $this->level4ChildrenByCircleCategoryId($parentId);
            if ($level4->isNotEmpty()) {
                return $level4;
            }

            return $this->legacyLevel2Children($parentId);
        }

        $level2Table = $this->level2CategoriesTable();
        if (Schema::hasTable($level2Table)) {
            $level2 = DB::table($level2Table)->where('id', $parentId)->first();
            if ($level2) {
                return $this->legacyLevel3Children($parentId);
            }
        }

        $level3Table = $this->level3CategoriesTable();
        if (Schema::hasTable($level3Table)) {
            $level3 = DB::table($level3Table)->where('id', $parentId)->first();
            if ($level3) {
                return $this->legacyLevel4Children($parentId);
            }
        }

        return collect();
    }

    private function legacyLevel2Children(string $parentId)
    {
        $table = $this->level2CategoriesTable();
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table($table)
            ->select(['id', 'name', 'circle_category_id'])
            ->where('circle_category_id', $parentId);

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }

        return $this->orderCategoryQuery($query, $table)
            ->get()
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'level' => 2,
                'parent_id' => (int) $category->circle_category_id,
            ])
            ->values();
    }

    private function legacyLevel3Children(string $parentId)
    {
        $table = $this->level3CategoriesTable();
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table($table)
            ->select(['id', 'name', 'level2_id'])
            ->where('level2_id', $parentId);

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }

        return $this->orderCategoryQuery($query, $table)
            ->get()
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'level' => 3,
                'parent_id' => (int) $category->level2_id,
            ])
            ->values();
    }

    private function legacyLevel4Children(string $parentId)
    {
        $table = $this->level4CategoriesTable();
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table($table)
            ->select(['id', 'name', 'level3_id'])
            ->where('level3_id', $parentId);

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }

        return $this->orderCategoryQuery($query, $table)
            ->get()
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'level' => 4,
                'parent_id' => (int) $category->level3_id,
            ])
            ->values();
    }

    private function level2CategoriesTable(): string
    {
        return $this->firstExistingTable(['level2_categories', 'circle_category_level2']);
    }

    private function level3CategoriesTable(): string
    {
        return $this->firstExistingTable(['level3_categories', 'circle_category_level3']);
    }

    private function level4CategoriesTable(): string
    {
        return $this->firstExistingTable(['level4_categories', 'circle_category_level4']);
    }

    private function firstExistingTable(array $tables): string
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return $tables[0];
    }

    private function orderCategoryQuery($query, string $table)
    {
        if (Schema::hasColumn($table, 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('name')->orderBy('id');
    }
}
