<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Circles;

use App\Http\Controllers\Controller;
use App\Http\Resources\CircleMemberResource;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Services\Circles\CircleActivityMetricsService;
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
            ->whereHas('user', function ($uq): void {
                $uq->where(function ($sq): void {
                    $sq->whereNull('status')->orWhere('status', 'active');
                })->where('status', '!=', 'inactive')
                    ->whereNull('deleted_at');
            })
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

        $legacyDedIdToggle = $request->boolean('show_ded_id')
            || $request->boolean('include_ded_id')
            || $request->boolean('ded_id')
            || $request->boolean('with_ded_id')
            || $request->boolean('ded_id_toggle')
            || $request->boolean('ded_id_display')
            || $request->boolean('include_ded_and_id')
            || $request->boolean('show_ded_and_id')
            || in_array(strtolower(trim((string) $request->input('ded_id_toggle', ''))), ['1', 'true', 'on', 'yes'], true)
            || in_array(strtolower(trim((string) $request->input('show_ded_id', ''))), ['1', 'true', 'on', 'yes'], true)
            || in_array(strtolower(trim((string) $request->input('include_ded_id', ''))), ['1', 'true', 'on', 'yes'], true)
            || in_array(strtolower(trim((string) $request->input('ded_id_display', ''))), ['1', 'true', 'on', 'yes'], true)
            || in_array(strtolower(trim((string) $request->input('ded_id', ''))), ['1', 'true', 'on', 'yes'], true);

        $showDed = $request->has('show_ded')
            ? ($request->boolean('show_ded') || in_array(strtolower(trim((string) $request->input('show_ded', ''))), ['1', 'true', 'on', 'yes'], true))
            : $legacyDedIdToggle;

        $showId = ($request->has('show_id') || $request->has('show_ids'))
            ? ($request->boolean('show_id') || $request->boolean('show_ids') || in_array(strtolower(trim((string) ($request->input('show_id') ?? $request->input('show_ids') ?? ''))), ['1', 'true', 'on', 'yes'], true))
            : $legacyDedIdToggle;

        $onlyDedId = $request->boolean('only_ded_id')
            || $request->boolean('ded_id_only')
            || strtolower(trim((string) $request->input('ded_id', ''))) === 'only';

        if ($onlyDedId) {
            $dedIdRoles = ['ded', 'industry_director', 'id'];
            $query->where(function ($q) use ($dedIdRoles): void {
                $q->whereIn(DB::raw('LOWER(circle_members.role::text)'), $dedIdRoles)
                    ->orWhereHas('roleModel', function ($rq) use ($dedIdRoles): void {
                        $rq->whereIn(DB::raw('LOWER(key)'), $dedIdRoles)
                            ->orWhereIn(DB::raw('LOWER(name)'), $dedIdRoles);
                    });
            });
        } elseif ($request->filled('role')) {
            $roleStr = strtolower(trim((string) $request->input('role')));
            if ($roleStr !== 'all' && $roleStr !== 'any') {
                if (in_array($roleStr, ['ded_id', 'ded_and_id', 'ded,id', 'ded,industry_director'], true)) {
                    $dedIdRoles = ['ded', 'industry_director', 'id'];
                    $query->where(function ($q) use ($dedIdRoles): void {
                        $q->whereIn(DB::raw('LOWER(circle_members.role::text)'), $dedIdRoles)
                            ->orWhereHas('roleModel', function ($rq) use ($dedIdRoles): void {
                                $rq->whereIn(DB::raw('LOWER(key)'), $dedIdRoles)
                                    ->orWhereIn(DB::raw('LOWER(name)'), $dedIdRoles);
                            });
                    });
                } else {
                    $mappedRole = match ($roleStr) {
                        'founder', 'cf', 'circle_founder' => 'circle_founder',
                        'director', 'cd', 'circle_director' => 'circle_director',
                        'id', 'industry_director' => 'industry_director',
                        default => $roleStr,
                    };

                    $query->where(function ($q) use ($mappedRole): void {
                        $q->whereRaw('LOWER(circle_members.role::text) = ?', [$mappedRole])
                            ->orWhereHas('roleModel', function ($rq) use ($mappedRole): void {
                                $rq->whereRaw('LOWER(key) = ?', [$mappedRole])
                                    ->orWhereRaw('LOWER(name) = ?', [$mappedRole]);
                            });
                    });
                }
            }
        } elseif ($showDed || $showId) {
            // Partial or full inclusion of DED / ID
            $excludedRoles = CircleMember::REGIONAL_ROLES;
            if ($showDed) {
                $excludedRoles = array_values(array_diff($excludedRoles, ['ded']));
            }
            if ($showId) {
                $excludedRoles = array_values(array_diff($excludedRoles, ['industry_director', 'id']));
            }
            $query->whereNotIn(DB::raw('LOWER(circle_members.role::text)'), $excludedRoles);
        } else {
            // Toggle OFF (Default): Filter out all regional leaders including DED and ID from circle members list
            $query->whereNotIn(DB::raw('LOWER(circle_members.role::text)'), CircleMember::REGIONAL_ROLES);
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

        // Attach per-member activity metrics.
        $memberMetrics = app(CircleActivityMetricsService::class)->perMember($circle);
        foreach ($members as $member) {
            if ($member->user) {
                $uid = (string) $member->user->id;
                $stats = $memberMetrics[$uid] ?? [];
                foreach ($stats as $key => $value) {
                    $member->user->setAttribute($key, $value);
                }
            }
        }

        $responsePayload = [
            'success' => true,
            'message' => null,
            'data' => CircleMemberResource::collection($members),
            'show_ded' => $showDed,
            'show_id' => $showId,
            'show_ded_id' => $showDed && $showId,
            'ded_id_toggle' => $showDed || $showId || $onlyDedId,
        ];

        if ($showDed || $onlyDedId) {
            $dedMember = $members->first(fn ($m) => in_array(strtolower((string) ($m->role ?? '')), ['ded'], true)
                || strtolower((string) ($m->roleModel?->key ?? '')) === 'ded'
                || strtolower((string) ($m->roleModel?->name ?? '')) === 'ded');
            $responsePayload['ded'] = $dedMember ? new CircleMemberResource($dedMember) : null;
        }

        if ($showId || $onlyDedId) {
            $idMember = $members->first(fn ($m) => in_array(strtolower((string) ($m->role ?? '')), ['industry_director', 'id'], true)
                || in_array(strtolower((string) ($m->roleModel?->key ?? '')), ['industry_director', 'id'], true)
                || in_array(strtolower((string) ($m->roleModel?->name ?? '')), ['industry director', 'industry_director', 'id'], true));

            $responsePayload['industry_director'] = $idMember ? new CircleMemberResource($idMember) : null;
            $responsePayload['id'] = $idMember ? new CircleMemberResource($idMember) : null;
        }

        return response()->json($responsePayload);
    }

    private function ensureCircleMembersExist(Circle $circle): void
    {
        $circleLeadershipRoles = [
            'chair' => $circle->chair_user_id ?? null,
            'vice_chair' => $circle->vice_chair_user_id ?? null,
            'secretary' => $circle->secretary_user_id ?? null,
            'ded' => $circle->ded_user_id ?? data_get($circle->calendar, 'leadership.ded_user_id') ?? data_get($circle->calendar, 'settings.ded_user_id'),
            'industry_director' => $circle->industry_director_user_id ?? data_get($circle->calendar, 'leadership.industry_director_user_id') ?? data_get($circle->calendar, 'settings.industry_director_user_id'),
        ];

        foreach ($circleLeadershipRoles as $role => $userId) {
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
                    if (empty($existing->role) || $existing->role === 'member') {
                        $existing->role = $role;
                    }
                    $existing->save();
                } elseif (in_array($role, ['ded', 'industry_director'], true) && (empty($existing->role) || $existing->role === 'member')) {
                    $existing->role = $role;
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
