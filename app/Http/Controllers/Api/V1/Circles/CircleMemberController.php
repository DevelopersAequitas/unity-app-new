<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Circles;

use App\Http\Controllers\Controller;
use App\Http\Resources\CircleMemberResource;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CircleMemberController extends Controller
{
    public function index(Request $request, Circle $circle): JsonResponse
    {
        $this->ensureCircleMembersExist($circle);

        $with = ['user', 'user.cityRelation', 'user.businessCategory', 'user.mainBusinessCategory'];

        if (Schema::hasTable('joined_circle_categories')) {
            $with['user.joinedCircleCategories'] = function ($query): void {
                $query->with([
                    'circle:id,name',
                    'level1Category:id,name',
                    'level2Category:id,name',
                    'level3Category:id,name',
                    'level4Category:id,name',
                ])->orderByDesc('updated_at');
            };
        }

        $query = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->with($with);

        if ($request->filled('status')) {
            $statusStr = strtolower(trim((string) $request->input('status')));
            if ($statusStr === 'active') {
                $query->where(function ($q): void {
                    $q->whereIn('status', CircleMember::activeStatuses())
                        ->orWhereNull('status');
                });
            } elseif ($statusStr !== 'all' && $statusStr !== 'any') {
                $query->where('status', $statusStr);
            }
        }

        if ($request->filled('role')) {
            $roleStr = strtolower(trim((string) $request->input('role')));
            if ($roleStr !== 'all' && $roleStr !== 'any') {
                $mappedRole = match ($roleStr) {
                    'founder', 'cf', 'circle_founder' => 'circle_founder',
                    'director', 'cd', 'circle_director' => 'circle_director',
                    'id', 'industry_director' => 'industry_director',
                    default => $roleStr,
                };

                if (in_array($mappedRole, CircleMember::ROLE_OPTIONS, true)) {
                    $query->where('role', $mappedRole);
                } else {
                    $query->whereRaw('role::text = ?', [$mappedRole]);
                }
            }
        }

        if ($request->filled('search') || $request->filled('q')) {
            $search = trim((string) ($request->input('search') ?? $request->input('q')));

            if ($search !== '') {
                $isPgSql = DB::connection()->getDriverName() === 'pgsql';
                $likeOp = $isPgSql ? 'ILIKE' : 'LIKE';
                $fullNameExpr = $isPgSql
                    ? "CONCAT_WS(' ', first_name, last_name)"
                    : "(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))";
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->whereHas('user', function ($q) use ($like, $likeOp, $fullNameExpr): void {
                    $q->where(function ($inner) use ($like, $likeOp, $fullNameExpr): void {
                        $inner->where('display_name', $likeOp, $like)
                            ->orWhere('first_name', $likeOp, $like)
                            ->orWhere('last_name', $likeOp, $like)
                            ->orWhereRaw("{$fullNameExpr} {$likeOp} ?", [$like])
                            ->orWhere('email', $likeOp, $like)
                            ->orWhere('phone', $likeOp, $like)
                            ->orWhere('company_name', $likeOp, $like);
                    });
                });
            }
        }

        $query->orderByDesc('created_at');

        if ($request->boolean('all', false) || $request->input('per_page') === 'all') {
            $members = $query->get();
        } else {
            $perPage = max(1, min((int) $request->input('per_page', 50), 500));
            $members = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => CircleMemberResource::collection($members),
        ]);
    }

    private function ensureCircleMembersExist(Circle $circle): void
    {
        $leadershipRoles = [
            'circle_founder' => $circle->circle_founder_user_id,
            'circle_director' => $circle->circle_director_user_id,
            'industry_director' => $circle->industry_director_user_id,
            'ded' => $circle->ded_user_id,
            'eed' => $circle->eed_user_id,
            'chair' => $circle->chair_user_id ?? null,
            'vice_chair' => $circle->vice_chair_user_id ?? null,
            'secretary' => $circle->secretary_user_id ?? null,
        ];

        foreach ($leadershipRoles as $role => $userId) {
            if (! empty($userId) && User::where('id', $userId)->exists()) {
                $existing = CircleMember::withTrashed()
                    ->where('circle_id', $circle->id)
                    ->where('user_id', $userId)
                    ->first();

                if (! $existing) {
                    CircleMember::query()->create([
                        'circle_id' => $circle->id,
                        'user_id' => $userId,
                        'role' => $role,
                        'status' => 'approved',
                        'joined_at' => $circle->created_at ?? now(),
                    ]);
                } elseif ($existing->trashed()) {
                    $existing->restore();
                    $existing->status = 'approved';
                    $existing->save();
                }
            }
        }

        $activeCircleUserIds = User::query()
            ->where('active_circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($activeCircleUserIds as $activeUserId) {
            $existing = CircleMember::withTrashed()
                ->where('circle_id', $circle->id)
                ->where('user_id', $activeUserId)
                ->first();

            if (! $existing) {
                CircleMember::query()->create([
                    'circle_id' => $circle->id,
                    'user_id' => $activeUserId,
                    'role' => 'member',
                    'status' => 'approved',
                    'joined_at' => now(),
                ]);
            } elseif ($existing->trashed()) {
                $existing->restore();
                $existing->status = 'approved';
                $existing->save();
            }
        }

        if (Schema::hasTable('joined_circle_categories')) {
            $categoryUserIds = DB::table('joined_circle_categories')
                ->where('circle_id', $circle->id)
                ->pluck('user_id')
                ->unique();

            foreach ($categoryUserIds as $catUserId) {
                if (! empty($catUserId) && User::where('id', $catUserId)->exists()) {
                    $existing = CircleMember::withTrashed()
                        ->where('circle_id', $circle->id)
                        ->where('user_id', $catUserId)
                        ->first();

                    if (! $existing) {
                        CircleMember::query()->create([
                            'circle_id' => $circle->id,
                            'user_id' => $catUserId,
                            'role' => 'member',
                            'status' => 'approved',
                            'joined_at' => now(),
                        ]);
                    } elseif ($existing->trashed()) {
                        $existing->restore();
                        $existing->status = 'approved';
                        $existing->save();
                    }
                }
            }
        }
    }
}
