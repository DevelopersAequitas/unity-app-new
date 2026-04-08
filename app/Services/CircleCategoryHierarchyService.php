<?php

namespace App\Services;

use App\Models\CircleCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class CircleCategoryHierarchyService
{
    public function getMainCircles(): Collection
    {
        $query = $this->activeQuery()
            ->whereNull('parent_id')
            ->where('level', 1)
            ->withCount('children');

        return $this->orderedQuery($query)->get();
    }

    public function getChildren(int $parentId): Collection
    {
        if (! $this->hierarchyReady()) {
            return new Collection();
        }

        $query = $this->activeQuery()
            ->where('parent_id', $parentId)
            ->withCount('children');

        return $this->orderedQuery($query)->get();
    }

    public function getTree(int $mainCategoryId): ?CircleCategory
    {
        $mainCircle = $this->activeQuery()
            ->when($this->hierarchyReady(), fn ($query) => $query->withCount('children'))
            ->find($mainCategoryId);

        if (! $mainCircle) {
            return null;
        }

        $mainCircle->setRelation(
            'childrenRecursive',
            $this->buildChildrenTree($mainCircle->id, 2)
        );

        return $mainCircle;
    }

    public function getFinalCategories(?int $parentId): Collection
    {
        $query = $this->activeQuery();

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        } elseif ($this->hierarchyReady()) {
            $query->where('level', 4);
        }

        if ($this->hierarchyReady()) {
            $query->withCount('children');
        }

        return $this->orderedQuery($query)->get();
    }

    public function hierarchyReady(): bool
    {
        return Schema::hasColumn('circle_categories', 'parent_id')
            && Schema::hasColumn('circle_categories', 'level');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\CircleCategory>
     */
    private function buildChildrenTree(int $parentId, int $expectedLevel): Collection
    {
        if (! $this->hierarchyReady()) {
            return new Collection();
        }

        $childrenQuery = $this->activeQuery()
            ->where('parent_id', $parentId)
            ->when(
                Schema::hasColumn('circle_categories', 'level'),
                fn ($query) => $query->where('level', $expectedLevel)
            )
            ->withCount('children');

        $children = $this->orderedQuery($childrenQuery)->get();

        if ($children->isEmpty() || $expectedLevel >= 4) {
            return $children;
        }

        $children->each(function (CircleCategory $category) use ($expectedLevel): void {
            $category->setRelation(
                'childrenRecursive',
                $this->buildChildrenTree($category->id, $expectedLevel + 1)
            );
        });

        return $children;
    }

    private function activeQuery()
    {
        $query = CircleCategory::query();

        if (Schema::hasColumn('circle_categories', 'is_active')) {
            $query->where(function ($activeQuery): void {
                $activeQuery->where('is_active', true)
                    ->orWhereNull('is_active');
            });
        }

        return $query;
    }

    private function orderedQuery($query = null)
    {
        $query = $query ?? CircleCategory::query();

        if (Schema::hasColumn('circle_categories', 'sort_order')) {
            $query->orderByRaw('sort_order ASC NULLS LAST');
        }

        return $query->orderBy('id');
    }
}
