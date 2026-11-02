<?php

namespace App\Http\Controllers\Admin\Circles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Circles\StoreCircleMemberRequest;
use App\Http\Requests\Admin\Circles\UpdateCircleMemberRequest;
use App\Models\Circle;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminAccess;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CircleMemberController extends Controller
{
    public function store(StoreCircleMemberRequest $request, Circle $circle)
    {
        $data = $request->validated();
        $redirectQuery = $this->peerFilterQuery($request);

        // 1. Check whether combination of circle_id and user_id already exists
        $exists = DB::table('circle_members')
            ->where('circle_id', $circle->id)
            ->where('user_id', $data['user_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This peer is already a member of this circle.',
                ], 422);
            }

            return redirect()
                ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
                ->withErrors(['user_id' => 'This peer is already a member of this circle.']);
        }

        try {
            DB::transaction(function () use ($circle, $data, $request): void {
                $rawJoinedAt = $data['joined_at'] ?? $data['circle_joined_at'] ?? null;
                $rawExpiresAt = $data['expires_at'] ?? $data['circle_expires_at'] ?? null;

                $joinedAt = $rawJoinedAt ? Carbon::parse($rawJoinedAt) : now();
                $expiresAt = $rawExpiresAt ? Carbon::parse($rawExpiresAt) : null;

                $showOnMemberList = $request->boolean('show_on_member_list', false);

                $roleInput = (string) $data['role'];
                $roleModel = null;
                if (Str::isUuid($roleInput)) {
                    $roleModel = Role::query()->find($roleInput);
                } else {
                    $normalizedKey = match (strtolower(trim($roleInput))) {
                        'founder', 'circle founder' => 'circle_founder',
                        'director', 'circle director' => 'circle_director',
                        'id', 'industry director' => 'industry_director',
                        default => strtolower(trim($roleInput)),
                    };
                    $roleModel = Role::query()->where('key', $normalizedKey)->orWhere('key', strtolower(trim($roleInput)))->first();
                }

                $roleSlug = $roleModel?->key ?? (Str::isUuid($roleInput) ? 'member' : strtolower(trim($roleInput)));
                $roleId = $roleModel?->id ?? (Str::isUuid($roleInput) ? $roleInput : null);

                $payload = [
                    'user_id' => $data['user_id'],
                    'role' => $roleSlug,
                    'status' => 'approved',
                ];

                if ($roleId) {
                    $payload['role_id'] = $roleId;
                }

                $memberRoleId = Role::idByKey('member');
                $circleChanged = false;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];

                if (in_array($roleSlug, ['circle_founder', 'founder'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('user_id', '!=', $data['user_id'])
                        ->whereIn('role', ['circle_founder', 'founder'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->circle_founder_user_id = $data['user_id'];
                    $circle->founder_user_id = $data['user_id'];
                    data_set($calendar, 'leadership.circle_founder_user_id', $data['user_id']);
                    data_set($calendar, 'leadership.founder_user_id', $data['user_id']);
                    $circleChanged = true;
                } elseif (in_array($roleSlug, ['circle_director', 'director', 'cd'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('user_id', '!=', $data['user_id'])
                        ->whereIn('role', ['circle_director', 'director', 'cd'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->circle_director_user_id = $data['user_id'];
                    $circle->director_user_id = $data['user_id'];
                    data_set($calendar, 'leadership.circle_director_user_id', $data['user_id']);
                    data_set($calendar, 'leadership.director_user_id', $data['user_id']);
                    $circleChanged = true;
                } elseif (in_array($roleSlug, ['industry_director', 'id'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('user_id', '!=', $data['user_id'])
                        ->whereIn('role', ['industry_director', 'id'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->industry_director_user_id = $data['user_id'];
                    data_set($calendar, 'leadership.industry_director_user_id', $data['user_id']);
                    $circleChanged = true;
                } elseif ($roleSlug === 'ded') {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('user_id', '!=', $data['user_id'])
                        ->where('role', 'ded')
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->ded_user_id = $data['user_id'];
                    data_set($calendar, 'leadership.ded_user_id', $data['user_id']);
                    $circleChanged = true;
                } elseif ($roleSlug === 'eed') {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('user_id', '!=', $data['user_id'])
                        ->where('role', 'eed')
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->eed_user_id = $data['user_id'];
                    data_set($calendar, 'leadership.eed_user_id', $data['user_id']);
                    $circleChanged = true;
                }

                if ($circleChanged) {
                    $circle->calendar = $calendar;
                    $circle->save();
                }

                if (Schema::hasColumn('circle_members', 'show_on_member_list')) {
                    $payload['show_on_member_list'] = $showOnMemberList;
                }

                if (Schema::hasColumn('circle_members', 'joined_at')) {
                    $payload['joined_at'] = $joinedAt;
                }

                if (Schema::hasColumn('circle_members', 'expires_at')) {
                    $payload['expires_at'] = $expiresAt;
                }

                $existingMember = CircleMember::withTrashed()
                    ->where('circle_id', $circle->id)
                    ->where('user_id', $data['user_id'])
                    ->first();

                if ($existingMember) {
                    if ($existingMember->trashed()) {
                        $existingMember->restore();
                    }
                    $payload['left_at'] = null;
                    $existingMember->forceFill($payload)->save();
                    $member = $existingMember;
                } else {
                    $member = $circle->members()->create($payload);
                }

                Circle::syncLeadershipFromMembers($circle);

                $level4Id = (int) ($data['level4_category_id'] ?? 0);
                if ($level4Id > 0 && Schema::hasTable('joined_circle_categories')) {
                    $level4 = CircleCategoryLevel4::find($level4Id);
                    $level1Id = $level4?->circle_category_id ?? ($data['level1_category_id'] ?? null);
                    $level2Id = $level4?->level2_id ?? null;
                    $level3Id = $level4?->level3_id ?? null;

                    JoinedCircleCategory::query()->updateOrCreate(
                        ['circle_member_id' => $member->id],
                        [
                            'user_id' => $data['user_id'],
                            'circle_id' => $circle->id,
                            'level1_category_id' => $level1Id,
                            'level2_category_id' => $level2Id,
                            'level3_category_id' => $level3Id,
                            'level4_category_id' => $level4Id,
                        ]
                    );
                } elseif (! empty($data['level1_category_id']) && Schema::hasTable('joined_circle_categories')) {
                    JoinedCircleCategory::query()->updateOrCreate(
                        ['circle_member_id' => $member->id],
                        [
                            'user_id' => $data['user_id'],
                            'circle_id' => $circle->id,
                            'level1_category_id' => (int) $data['level1_category_id'],
                            'level2_category_id' => null,
                            'level3_category_id' => null,
                            'level4_category_id' => null,
                        ]
                    );
                }

                $user = User::query()->find($data['user_id']);
                if ($user && empty($user->active_circle_id)) {
                    $user->active_circle_id = $circle->id;
                    $user->circle_joined_at = $joinedAt;
                    $user->circle_expires_at = $expiresAt;
                    $user->save();
                }
            });
        } catch (QueryException $e) {
            Log::error('Failed to add circle member', [
                'circle_id' => $circle->id,
                'user_id' => $data['user_id'],
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add member to the circle. Please try again.',
                ], 422);
            }

            return redirect()
                ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
                ->withErrors(['user_id' => 'Failed to add member to the circle. Please try again.']);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member added to the circle.',
            ]);
        }

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member added to the circle.');
    }

    public function update(UpdateCircleMemberRequest $request, Circle $circle, CircleMember $circleMember)
    {
        if ($circleMember->circle_id !== $circle->id) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Member not found in circle.'], 404);
            }
            abort(404);
        }
        $redirectQuery = $this->peerFilterQuery($request);

        $validated = $request->validated();
        $roleInput = isset($validated['role']) ? (string) $validated['role'] : null;
        $oldRole = (string) $circleMember->role;

        DB::transaction(function () use ($circle, $circleMember, $roleInput, $oldRole, $request): void {
            $roleSlug = null;
            if ($roleInput !== null) {
                $roleModel = null;
                if (Str::isUuid($roleInput)) {
                    $roleModel = Role::query()->find($roleInput);
                } else {
                    $normalizedKey = match (strtolower(trim($roleInput))) {
                        'founder', 'circle founder' => 'circle_founder',
                        'director', 'circle director' => 'circle_director',
                        'id', 'industry director' => 'industry_director',
                        default => strtolower(trim($roleInput)),
                    };
                    $roleModel = Role::query()->where('key', $normalizedKey)->orWhere('key', strtolower(trim($roleInput)))->first();
                }

                $roleSlug = $roleModel?->key ?? (Str::isUuid($roleInput) ? 'member' : strtolower(trim($roleInput)));
                $roleId = $roleModel?->id ?? (Str::isUuid($roleInput) ? $roleInput : null);

                $circleMember->role = $roleSlug;
                $circleMember->role_id = $roleId;

                $memberRoleId = Role::idByKey('member');
                $circleChanged = false;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];

                if (in_array($roleSlug, ['circle_founder', 'founder'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('id', '!=', $circleMember->id)
                        ->whereIn('role', ['circle_founder', 'founder'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->circle_founder_user_id = $circleMember->user_id;
                    $circle->founder_user_id = $circleMember->user_id;
                    data_set($calendar, 'leadership.circle_founder_user_id', $circleMember->user_id);
                    data_set($calendar, 'leadership.founder_user_id', $circleMember->user_id);
                    $circleChanged = true;
                } elseif ($circle->circle_founder_user_id === $circleMember->user_id) {
                    $circle->circle_founder_user_id = null;
                    $circle->founder_user_id = null;
                    data_set($calendar, 'leadership.circle_founder_user_id', null);
                    data_set($calendar, 'leadership.founder_user_id', null);
                    $circleChanged = true;
                }

                if (in_array($roleSlug, ['circle_director', 'director', 'cd'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('id', '!=', $circleMember->id)
                        ->whereIn('role', ['circle_director', 'director', 'cd'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->circle_director_user_id = $circleMember->user_id;
                    $circle->director_user_id = $circleMember->user_id;
                    data_set($calendar, 'leadership.circle_director_user_id', $circleMember->user_id);
                    data_set($calendar, 'leadership.director_user_id', $circleMember->user_id);
                    $circleChanged = true;
                } elseif ($circle->circle_director_user_id === $circleMember->user_id) {
                    $circle->circle_director_user_id = null;
                    $circle->director_user_id = null;
                    data_set($calendar, 'leadership.circle_director_user_id', null);
                    data_set($calendar, 'leadership.director_user_id', null);
                    $circleChanged = true;
                }

                if (in_array($roleSlug, ['industry_director', 'id'], true)) {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('id', '!=', $circleMember->id)
                        ->whereIn('role', ['industry_director', 'id'])
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->industry_director_user_id = $circleMember->user_id;
                    data_set($calendar, 'leadership.industry_director_user_id', $circleMember->user_id);
                    $circleChanged = true;
                } elseif ($circle->industry_director_user_id === $circleMember->user_id) {
                    $circle->industry_director_user_id = null;
                    data_set($calendar, 'leadership.industry_director_user_id', null);
                    $circleChanged = true;
                }

                if ($roleSlug === 'ded') {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('id', '!=', $circleMember->id)
                        ->where('role', 'ded')
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->ded_user_id = $circleMember->user_id;
                    data_set($calendar, 'leadership.ded_user_id', $circleMember->user_id);
                    $circleChanged = true;
                } elseif ($circle->ded_user_id === $circleMember->user_id) {
                    $circle->ded_user_id = null;
                    data_set($calendar, 'leadership.ded_user_id', null);
                    $circleChanged = true;
                }

                if ($roleSlug === 'eed') {
                    CircleMember::where('circle_id', $circle->id)
                        ->where('id', '!=', $circleMember->id)
                        ->where('role', 'eed')
                        ->update(['role' => 'member', 'role_id' => $memberRoleId]);

                    $circle->eed_user_id = $circleMember->user_id;
                    data_set($calendar, 'leadership.eed_user_id', $circleMember->user_id);
                    $circleChanged = true;
                } elseif ($circle->eed_user_id === $circleMember->user_id) {
                    $circle->eed_user_id = null;
                    data_set($calendar, 'leadership.eed_user_id', null);
                    $circleChanged = true;
                }

                if ($circleChanged) {
                    $circle->calendar = $calendar;
                    $circle->save();
                }
            }

            if ($request->has('show_on_member_list')) {
                $circleMember->show_on_member_list = $request->boolean('show_on_member_list');
            }

            $circleMember->save();

            if ($roleInput !== null) {
                $user = $circleMember->user;
                if ($user) {
                    $freshMember = $circleMember->fresh();
                    $resolvedNewRole = (string) ($freshMember?->role ?? $roleSlug ?? $roleInput);
                    $this->syncCircleLeadershipColumns($circle, $user->id, $resolvedNewRole, $oldRole);

                    $adminUser = DB::table('admin_users')->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->first();
                    if ($adminUser) {
                        AdminAccess::clearAdminUserCache($adminUser->id);
                    }
                }
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member updated.',
                'data' => $circleMember->fresh(),
            ]);
        }

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member updated.');
    }

    public function toggleShowOnMemberList(Request $request, Circle $circle, CircleMember $circleMember)
    {
        if ($circleMember->circle_id !== $circle->id) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Member not found in circle.'], 404);
            }
            abort(404);
        }

        $newValue = $request->has('show_on_member_list')
            ? $request->boolean('show_on_member_list')
            : ! (bool) ($circleMember->show_on_member_list ?? false);

        $circleMember->show_on_member_list = $newValue;
        $circleMember->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'show_on_member_list' => (bool) $circleMember->show_on_member_list,
                'message' => 'Member list visibility updated successfully.',
            ]);
        }

        $redirectQuery = $this->peerFilterQuery($request);

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member list visibility updated.');
    }

    private function syncCircleLeadershipColumns(Circle $circle, string $userId, string $newRole, string $oldRole): void
    {
        Circle::syncLeadershipFromMembers($circle);
    }

    public function updateCategory(Request $request, Circle $circle, CircleMember $circleMember)
    {
        if ($circleMember->circle_id !== $circle->id) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Member not found in circle.'], 404);
            }
            abort(404);
        }

        $data = $request->validate([
            'level4_category_id' => ['required', 'integer', 'min:1'],
        ]);

        $redirectQuery = $this->peerFilterQuery($request);

        $level4Id = (int) $data['level4_category_id'];
        $level4 = CircleCategoryLevel4::find($level4Id);

        if (! $level4) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Selected category not found.'], 404);
            }

            return redirect()
                ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
                ->with('error', 'Selected category not found.');
        }

        JoinedCircleCategory::query()->updateOrCreate(
            ['circle_member_id' => $circleMember->id],
            [
                'user_id' => $circleMember->user_id,
                'circle_id' => $circle->id,
                'level1_category_id' => $level4->circle_category_id ?? null,
                'level2_category_id' => $level4->level2_id ?? null,
                'level3_category_id' => $level4->level3_id ?? null,
                'level4_category_id' => $level4Id,
            ]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Category assigned successfully.',
            ]);
        }

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Category assigned successfully.');
    }

    public function destroy(Request $request, Circle $circle, CircleMember $circleMember)
    {
        if ($circleMember->circle_id !== $circle->id) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Member not found in circle.'], 404);
            }
            abort(404);
        }
        $redirectQuery = $this->peerFilterQuery($request);

        // 1. Prevent founder removal on standard web form unless force is requested
        if ($circle->circle_founder_user_id === $circleMember->user_id || in_array($circleMember->role, ['circle_founder', 'founder'], true)) {
            if ($request->expectsJson() || $request->ajax() || $request->boolean('force')) {
                $circle->circle_founder_user_id = null;
                $circle->founder_user_id = null;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];
                data_set($calendar, 'leadership.circle_founder_user_id', null);
                data_set($calendar, 'leadership.founder_user_id', null);
                data_set($calendar, 'leadership.circle_founder_name', null);
                $circle->calendar = $calendar;
                $circle->save();
            } else {
                return redirect()
                    ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
                    ->with('error', 'Cannot remove founder. Please transfer founder role first.');
            }
        }

        DB::transaction(function () use ($circle, $circleMember) {
            $circleChanged = false;
            $calendar = is_array($circle->calendar) ? $circle->calendar : [];

            if ($circle->circle_director_user_id === $circleMember->user_id || in_array($circleMember->role, ['circle_director', 'director', 'cd'], true)) {
                $circle->circle_director_user_id = null;
                $circle->director_user_id = null;
                data_set($calendar, 'leadership.circle_director_user_id', null);
                data_set($calendar, 'leadership.director_user_id', null);
                data_set($calendar, 'leadership.circle_director_name', null);
                $circleChanged = true;
            }

            if ($circle->industry_director_user_id === $circleMember->user_id || in_array($circleMember->role, ['industry_director', 'id'], true)) {
                $circle->industry_director_user_id = null;
                data_set($calendar, 'leadership.industry_director_user_id', null);
                data_set($calendar, 'leadership.industry_director_name', null);
                $circleChanged = true;
            }

            if ($circle->ded_user_id === $circleMember->user_id || $circleMember->role === 'ded') {
                $circle->ded_user_id = null;
                data_set($calendar, 'leadership.ded_user_id', null);
                $circleChanged = true;
            }

            if ($circle->eed_user_id === $circleMember->user_id || $circleMember->role === 'eed') {
                $circle->eed_user_id = null;
                data_set($calendar, 'leadership.eed_user_id', null);
                $circleChanged = true;
            }

            if ($circleChanged) {
                $circle->calendar = $calendar;
                $circle->save();
            }

            // 2. Nullify user's active circle association in users table if it matches this circle
            DB::table('users')
                ->where('id', $circleMember->user_id)
                ->where('active_circle_id', $circle->id)
                ->update([
                    'active_circle_id' => null,
                    'active_circle_addon_code' => null,
                    'active_circle_addon_name' => null,
                    'circle_joined_at' => null,
                    'circle_expires_at' => null,
                    'active_circle_subscription_id' => null,
                ]);

            // 3. Mark left_at and delete categories mappings
            $circleMember->forceFill([
                'left_at' => now(),
            ])->save();

            if (Schema::hasTable('joined_circle_categories')) {
                JoinedCircleCategory::query()
                    ->where('circle_member_id', $circleMember->id)
                    ->delete();
            }

            // 4. Soft delete membership record
            $circleMember->delete();

            Circle::syncLeadershipFromMembers($circle);
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member removed from the circle.',
            ]);
        }

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member removed from the circle.');
    }

    private function peerFilterQuery(Request $request): array
    {
        $peerName = trim((string) $request->input('peer_name', ''));
        $peerEmail = trim((string) $request->input('peer_email', ''));
        $page = (int) $request->input('page', 1);

        $query = [];

        if ($peerName !== '') {
            $query['peer_name'] = $peerName;
        }

        if ($peerEmail !== '') {
            $query['peer_email'] = $peerEmail;
        }

        if ($request->filled('show_ded')) {
            $query['show_ded'] = $request->input('show_ded');
        }

        if ($request->filled('show_id')) {
            $query['show_id'] = $request->input('show_id');
        }

        if ($request->filled('show_ids')) {
            $query['show_id'] = $request->input('show_ids');
        }

        if ($request->filled('show_ded_id')) {
            $query['show_ded_id'] = $request->input('show_ded_id');
        }

        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }
}
