<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserTagController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

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
            ->paginate(15)
            ->withQueryString();

        return view('admin.user_tags.index', [
            'tags' => $tags,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.user_tags.create', [
            'tag' => new UserTag([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:user_tags,slug', 'regex:/^[a-z0-9_-]+$/i'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'The slug format may only contain letters, numbers, dashes, and underscores.',
            'slug.unique' => 'A tag with this slug already exists.',
        ]);

        $slug = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'], '_')
            : Str::slug($data['name'], '_');

        // Check again for slug uniqueness after slugification
        if (UserTag::where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['slug' => 'The slug "'.$slug.'" is already in use.']);
        }

        $tag = UserTag::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => trim($data['description'] ?? ''),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.user-tags.index')
            ->with('success', "Tag '{$tag->name}' created successfully.");
    }

    public function show(UserTag $userTag, Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $assignedUsers = $userTag->users()
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
            ->orderBy('user_tag_assignments.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.user_tags.show', [
            'tag' => $userTag,
            'users' => $assignedUsers,
            'search' => $search,
        ]);
    }

    public function edit(UserTag $userTag): View
    {
        return view('admin.user_tags.edit', [
            'tag' => $userTag,
        ]);
    }

    public function update(Request $request, UserTag $userTag): RedirectResponse
    {
        $isSystemTag = $userTag->isSystemTag();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (! $isSystemTag) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('user_tags', 'slug')->ignore($userTag->id),
                'regex:/^[a-z0-9_-]+$/i',
            ];
        }

        $data = $request->validate($rules, [
            'slug.regex' => 'The slug format may only contain letters, numbers, dashes, and underscores.',
            'slug.unique' => 'A tag with this slug already exists.',
        ]);

        $updatePayload = [
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'is_active' => $request->boolean('is_active', true),
        ];

        if (! $isSystemTag && isset($data['slug'])) {
            $updatePayload['slug'] = Str::slug($data['slug'], '_');
        }

        $userTag->update($updatePayload);

        return redirect()->route('admin.user-tags.index')
            ->with('success', "Tag '{$userTag->name}' updated successfully.");
    }

    public function destroy(UserTag $userTag): RedirectResponse
    {
        if ($userTag->isSystemTag()) {
            return back()->with('error', "The system tag '{$userTag->name}' ({$userTag->slug}) is required for core application logic and cannot be deleted.");
        }

        $tagName = $userTag->name;
        $userTag->delete();

        return redirect()->route('admin.user-tags.index')
            ->with('success', "Tag '{$tagName}' deleted successfully.");
    }

    public function assignUser(Request $request, UserTag $userTag): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $alreadyAssigned = $userTag->users()->where('users.id', $user->id)->exists();

        if ($alreadyAssigned) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "User '{$user->display_name}' is already assigned to {$userTag->name}.",
                ], 422);
            }

            return back()->with('error', "User '{$user->display_name}' is already assigned to {$userTag->name}.");
        }

        $userTag->users()->attach($user->id);

        $message = "User '{$user->display_name}' has been assigned to tag '{$userTag->name}'.";
        if ($userTag->slug === UserTag::SLUG_TEAM_MEMBER) {
            $message .= ' This user is now excluded from public leaderboards.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function removeUser(Request $request, UserTag $userTag, string $userId): RedirectResponse|JsonResponse
    {
        $user = User::find($userId);

        $userTag->users()->detach($userId);

        $userName = $user ? $user->display_name : 'User';
        $message = "Removed '{$userTag->name}' tag from {$userName}.";
        if ($userTag->slug === UserTag::SLUG_TEAM_MEMBER) {
            $message .= ' This user is now eligible to appear in public leaderboards.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function searchUsers(Request $request, UserTag $userTag): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            $users = User::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->whereDoesntHave('tags', function ($q) use ($userTag) {
                    $q->where('user_tags.id', $userTag->id);
                })
                ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
                ->limit(20)
                ->get();
        } else {
            $words = array_filter(explode(' ', $query));
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $users = User::query()
                ->whereNull('deleted_at')
                ->whereDoesntHave('tags', function ($q) use ($userTag) {
                    $q->where('user_tags.id', $userTag->id);
                })
                ->where(function ($sub) use ($words, $likeOperator): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $sub->where(function ($q2) use ($like, $likeOperator): void {
                            $q2->where('display_name', $likeOperator, $like)
                                ->orWhere('first_name', $likeOperator, $like)
                                ->orWhere('last_name', $likeOperator, $like)
                                ->orWhere('email', $likeOperator, $like)
                                ->orWhere('company_name', $likeOperator, $like)
                                ->orWhere('phone', $likeOperator, $like);
                        });
                    }
                })
                ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
                ->limit(20)
                ->get();
        }

        $results = $users->map(function (User $user) {
            $name = $user->display_name ?: trim($user->first_name.' '.$user->last_name);
            if (! $name) {
                $name = $user->email;
            }

            return [
                'id' => $user->id,
                'name' => $name,
                'email' => $user->email,
                'phone' => $user->phone,
                'company' => $user->company_name,
                'status' => $user->status,
                'coins' => (int) ($user->coins_balance ?? 0),
                'impacts' => (int) ($user->life_impacted_count ?? 0),
            ];
        });

        return response()->json($results);
    }
}
