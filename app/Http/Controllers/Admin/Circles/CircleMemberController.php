<?php

namespace App\Http\Controllers\Admin\Circles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Circles\StoreCircleMemberRequest;
use App\Http\Requests\Admin\Circles\UpdateCircleMemberRequest;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
use App\Support\AdminAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $payload = [
                'user_id' => $data['user_id'],
                'role' => $data['role'],
                'status' => 'approved',
            ];

            if (Schema::hasColumn('circle_members', 'joined_at')) {
                $payload['joined_at'] = now();
            }

            $circle->members()->create($payload);
        } catch (QueryException $e) {
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

    public function update(UpdateCircleMemberRequest $request, Circle $circle, CircleMember $circleMember): RedirectResponse
    {
        if ($circleMember->circle_id !== $circle->id) {
            abort(404);
        }
        $redirectQuery = $this->peerFilterQuery($request);

        $newRole = (string) $request->validated()['role'];
        $oldRole = (string) $circleMember->role;

        DB::transaction(function () use ($circle, $circleMember, $newRole, $oldRole): void {
            $circleMember->update([
                'role' => $newRole,
            ]);

            $user = $circleMember->user;
            if ($user) {
                $this->syncCircleLeadershipColumns($circle, $user->id, $newRole, $oldRole);

                $adminUser = DB::table('admin_users')->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->first();
                if ($adminUser) {
                    AdminAccess::clearAdminUserCache($adminUser->id);
                }
            }
        });

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member role updated.');
    }

    private function syncCircleLeadershipColumns(Circle $circle, string $userId, string $newRole, string $oldRole): void
    {
        $map = [
            'chair' => 'chair_user_id',
            'vice_chair' => 'vice_chair_user_id',
            'secretary' => 'secretary_user_id',
            'circle_director' => 'circle_director_user_id',
            'director' => 'circle_director_user_id',
            'circle_founder' => 'circle_founder_user_id',
            'founder' => 'circle_founder_user_id',
        ];

        $oldCol = $map[$oldRole] ?? null;
        $newCol = $map[$newRole] ?? null;
        $updates = [];

        if ($oldCol && $oldCol !== $newCol) {
            if (($circle->{$oldCol} ?? null) === $userId) {
                $updates[$oldCol] = null;
            }
        }

        if ($newCol) {
            DB::table('circles')->where('id', $circle->id)->where($newCol, '!=', $userId)->update([$newCol => null]);
            $updates[$newCol] = $userId;
        }

        if (! empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('circles')->where('id', $circle->id)->update($updates);
        }
    }

    public function destroy(Request $request, Circle $circle, CircleMember $circleMember): RedirectResponse
    {
        if ($circleMember->circle_id !== $circle->id) {
            abort(404);
        }
        $redirectQuery = $this->peerFilterQuery($request);

        // 1. Prevent founder removal
        if ($circle->circle_founder_user_id === $circleMember->user_id || in_array($circleMember->role, ['circle_founder', 'founder'])) {
            return redirect()
                ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
                ->with('error', 'Cannot remove founder. Please transfer founder role first.');
        }

        DB::transaction(function () use ($circle, $circleMember) {
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
        });

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

        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }
}
