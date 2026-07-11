<?php

namespace App\Http\Controllers\Api;

use App\Models\CircleMember;
use Illuminate\Http\Request;

class MyCircleController extends BaseApiController
{
    public function index(Request $request)
    {
        $memberships = CircleMember::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->whereNull('left_at')
            ->with(['circle' => function ($query) {
                $query->select('id', 'name', 'cover_file_id', 'status');
            }])
            ->orderByRaw('CASE WHEN paid_starts_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('paid_starts_at')
            ->orderByDesc('joined_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($membership) {
                return [
                    'membership_id' => $membership->id,
                    'circle_id' => $membership->circle_id,
                    'circle_name' => $membership->circle?->name,
                    'cover_image_url' => $membership->circle?->cover_file_id
                        ? url("/api/v1/files/{$membership->circle->cover_file_id}")
                        : null,
                    'role' => $membership->role,
                    'status' => $membership->status,
                    'joined_at' => $membership->joined_at ? $membership->joined_at->toIso8601String() : null,
                ];
            });

        return $this->success([
            'items' => $memberships,
        ], 'My circles fetched successfully.');
    }
}
