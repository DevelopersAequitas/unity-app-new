<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MyLeadershipCircleResource;
use App\Models\CircleMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CircleLeadershipController extends BaseApiController
{
    public function myLeadershipCircles(Request $request): JsonResponse
    {
        $allowedRoles = CircleMember::LEADERSHIP_ROLE_OPTIONS;

        $members = CircleMember::query()
            ->with(['circle.coverFile', 'roleModel'])
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereIn(DB::raw('LOWER(circle_members.status::text)'), ['approved', 'active', 'member']);
            })
            ->whereHas('circle')
            ->where(function ($query) use ($allowedRoles): void {
                $query->whereIn($this->normalizedColumn('circle_members.role'), $allowedRoles);

                if (Schema::hasColumn('circle_members', 'role_id')) {
                    $query->orWhereHas('roleModel', function ($roleQuery) use ($allowedRoles): void {
                        $roleColumns = array_values(array_filter(
                            ['slug', 'key', 'name', 'display_name'],
                            fn (string $column): bool => Schema::hasColumn('roles', $column)
                        ));

                        foreach ($roleColumns as $column) {
                            $roleQuery->orWhereIn($this->normalizedColumn('roles.' . $column), $allowedRoles);
                        }
                    });
                }
            })
            ->orderBy('joined_at')
            ->orderBy('created_at')
            ->get();

        return $this->success([
            'total' => $members->count(),
            'items' => MyLeadershipCircleResource::collection($members),
        ], 'My leadership circles fetched successfully.');
    }

    private function normalizedColumn(string $column): \Illuminate\Contracts\Database\Query\Expression
    {
        $normalizedColumn = "LOWER(REPLACE(REPLACE({$column}::text, ' ', '_'), '-', '_'))";

        return DB::raw("CASE WHEN {$normalizedColumn} = 'circle_member' THEN 'member' ELSE {$normalizedColumn} END");
    }
}
