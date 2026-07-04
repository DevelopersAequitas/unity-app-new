<?php

namespace App\Http\Controllers\Admin\Circles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Circles\StoreCircleMemberRequest;
use App\Http\Requests\Admin\Circles\UpdateCircleMemberRequest;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
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
        } catch (\Illuminate\Database\QueryException $e) {
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

        $circleMember->update([
            'role' => $request->validated()['role'],
        ]);

        return redirect()
            ->route('admin.circles.show', array_merge(['circle' => $circle], $redirectQuery))
            ->with('success', 'Member role updated.');
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
