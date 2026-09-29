<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CircleCategory;
use Illuminate\Support\Facades\DB;

class CategoryBulkActionService
{
    /**
     * Bulk delete (soft deactivate) child categories belonging to a main circle category.
     *
     * @param  array<int, int>  $level2Ids
     * @param  array<int, int>  $level3Ids
     * @param  array<int, int>  $level4Ids
     * @return array{deleted_level2: int, deleted_level3: int, deleted_level4: int, total_deleted: int}
     */
    public function bulkDestroyChildren(
        CircleCategory $category,
        array $level2Ids = [],
        array $level3Ids = [],
        array $level4Ids = []
    ): array {
        return DB::transaction(function () use ($category, $level2Ids, $level3Ids, $level4Ids): array {
            $now = now();
            $deletedL2 = 0;
            $deletedL3 = 0;
            $deletedL4 = 0;

            // 1. Level 2 Deactivations (and cascading child deactivations)
            if (! empty($level2Ids)) {
                $deletedL4 += DB::table('circle_category_level4')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('level2_id', $level2Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $deletedL3 += DB::table('circle_category_level3')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('level2_id', $level2Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $deletedL2 += DB::table('circle_category_level2')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('id', $level2Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);
            }

            // 2. Level 3 Deactivations (and cascading child Level 4 deactivations)
            if (! empty($level3Ids)) {
                $deletedL4 += DB::table('circle_category_level4')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('level3_id', $level3Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $deletedL3 += DB::table('circle_category_level3')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('id', $level3Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);
            }

            // 3. Level 4 Deactivations
            if (! empty($level4Ids)) {
                $deletedL4 += DB::table('circle_category_level4')
                    ->where('circle_category_id', $category->id)
                    ->whereIn('id', $level4Ids)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);
            }

            return [
                'deleted_level2' => $deletedL2,
                'deleted_level3' => $deletedL3,
                'deleted_level4' => $deletedL4,
                'total_deleted' => $deletedL2 + $deletedL3 + $deletedL4,
            ];
        });
    }
}
