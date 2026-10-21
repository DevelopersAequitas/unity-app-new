<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Models\UserTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserTagAdminController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));

        $tags = UserTag::query()
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('id', 'asc')
            ->get();

        $transformed = $tags->map(function (UserTag $tag) {
            return [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description ?? '',
                'is_active' => (bool) $tag->is_active,
                'is_system' => $tag->isSystemTag(),
                'users_count' => (int) $tag->users_count,
                'created_at' => $tag->created_at?->toIso8601String(),
                'updated_at' => $tag->updated_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'tags' => $transformed,
            'total' => $transformed->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:user_tags,slug', 'regex:/^[a-z0-9_-]+$/i'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'], '_')
            : Str::slug($data['name'], '_');

        if (UserTag::where('slug', $slug)->exists()) {
            return $this->error("The slug '{$slug}' is already in use.", 422);
        }

        $tag = UserTag::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => trim($data['description'] ?? ''),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success([
            'id' => (string) $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'description' => $tag->description ?? '',
            'is_active' => (bool) $tag->is_active,
            'is_system' => $tag->isSystemTag(),
            'users_count' => 0,
            'created_at' => $tag->created_at?->toIso8601String(),
            'updated_at' => $tag->updated_at?->toIso8601String(),
        ], 'Tag created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tag = UserTag::withCount('users')->where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $search = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));

        $usersQuery = $tag->users()
            ->whereNull('users.deleted_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('users.display_name', 'ILIKE', "%{$search}%")
                        ->orWhere('users.first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('users.last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('users.email', 'ILIKE', "%{$search}%")
                        ->orWhere('users.phone', 'ILIKE', "%{$search}%")
                        ->orWhere('users.company_name', 'ILIKE', "%{$search}%");
                });
            })
            ->withPivot('created_at')
            ->orderBy('user_tag_assignments.created_at', 'desc');

        $users = $usersQuery->paginate(50);

        $transformedUsers = collect($users->items())->map(function ($u) {
            $name = trim((string) ($u->display_name ?? ''));
            if ($name === '') {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            }
            if ($name === '') {
                $name = (string) ($u->email ?? 'User');
            }

            return [
                'id' => (string) $u->id,
                'name' => $name,
                'email' => $u->email,
                'phone' => $u->phone,
                'company_name' => $u->company_name ?? $u->business_name ?? '',
                'avatar_url' => $u->profile_photo_url ?? null,
                'assigned_at' => $u->pivot?->created_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'tag' => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description ?? '',
                'is_active' => (bool) $tag->is_active,
                'is_system' => $tag->isSystemTag(),
                'users_count' => (int) $tag->users_count,
                'created_at' => $tag->created_at?->toIso8601String(),
                'updated_at' => $tag->updated_at?->toIso8601String(),
            ],
            'users' => $transformedUsers,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $userTag = UserTag::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $isSystemTag = $userTag->isSystemTag();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (!$isSystemTag) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('user_tags', 'slug')->ignore($userTag->id),
                'regex:/^[a-z0-9_-]+$/i',
            ];
        }

        $data = $request->validate($rules);

        $updatePayload = [
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'is_active' => $request->boolean('is_active', true),
        ];

        if (!$isSystemTag && isset($data['slug'])) {
            $updatePayload['slug'] = Str::slug($data['slug'], '_');
        }

        $userTag->update($updatePayload);

        return $this->success([
            'id' => (string) $userTag->id,
            'name' => $userTag->name,
            'slug' => $userTag->slug,
            'description' => $userTag->description ?? '',
            'is_active' => (bool) $userTag->is_active,
            'is_system' => $userTag->isSystemTag(),
            'users_count' => $userTag->users()->count(),
            'created_at' => $userTag->created_at?->toIso8601String(),
            'updated_at' => $userTag->updated_at?->toIso8601String(),
        ], "Tag '{$userTag->name}' updated successfully.");
    }

    public function destroy(string $id): JsonResponse
    {
        $userTag = UserTag::where('id', $id)->orWhere('slug', $id)->firstOrFail();

        if ($userTag->isSystemTag()) {
            return $this->error("The system tag '{$userTag->name}' ({$userTag->slug}) is protected and cannot be deleted.", 403);
        }

        $tagName = $userTag->name;
        $userTag->delete();

        return $this->success(['deleted' => true], "Tag '{$tagName}' deleted successfully.");
    }

    public function assignUser(Request $request, string $id): JsonResponse
    {
        $userTag = UserTag::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($userTag->users()->where('users.id', $user->id)->exists()) {
            return $this->error("User '{$user->display_name}' is already assigned to {$userTag->name}.", 422);
        }

        $userTag->users()->attach($user->id);

        $message = "User '{$user->display_name}' assigned to '{$userTag->name}'.";
        if ($userTag->slug === UserTag::SLUG_TEAM_MEMBER) {
            $message .= ' This user is now excluded from public leaderboards.';
        }

        return $this->success([
            'assigned' => true,
            'users_count' => $userTag->users()->count(),
        ], $message);
    }

    public function removeUser(string $id, string $userId): JsonResponse
    {
        $userTag = UserTag::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $user = User::find($userId);

        $userTag->users()->detach($userId);

        $userName = $user ? ($user->display_name ?? $user->email) : 'User';
        $message = "Removed '{$userTag->name}' tag from {$userName}.";
        if ($userTag->slug === UserTag::SLUG_TEAM_MEMBER) {
            $message .= ' This user is now eligible to appear in public leaderboards.';
        }

        return $this->success([
            'removed' => true,
            'users_count' => $userTag->users()->count(),
        ], $message);
    }

    public function searchUsers(Request $request, string $id): JsonResponse
    {
        $userTag = UserTag::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $query = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));

        $driver = DB::connection()->getDriverName();
        $like = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        $users = User::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereDoesntHave('tags', function ($q) use ($userTag) {
                $q->where('user_tags.id', $userTag->id);
            })
            ->when($query !== '', function ($q) use ($query, $like) {
                $q->where(function ($sub) use ($query, $like) {
                    $sub->where('display_name', $like, "%{$query}%")
                        ->orWhere('first_name', $like, "%{$query}%")
                        ->orWhere('last_name', $like, "%{$query}%")
                        ->orWhere('email', $like, "%{$query}%")
                        ->orWhere('company_name', $like, "%{$query}%");
                });
            })
            ->limit(20)
            ->get(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'profile_photo_url']);

        $results = $users->map(function ($u) {
            $name = trim((string) ($u->display_name ?? ''));
            if ($name === '') {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            }
            if ($name === '') {
                $name = (string) ($u->email ?? 'User');
            }

            return [
                'id' => (string) $u->id,
                'name' => $name,
                'email' => $u->email,
                'phone' => $u->phone,
                'company_name' => $u->company_name ?? '',
                'avatar_url' => $u->profile_photo_url ?? null,
            ];
        });

        return $this->success($results);
    }
}
