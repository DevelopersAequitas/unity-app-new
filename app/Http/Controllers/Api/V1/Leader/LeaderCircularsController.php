<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderPublishCircularRequest;
use App\Models\Circular;
use App\Models\User;
use App\Services\Leader\LeaderPermissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaderCircularsController extends Controller
{
    public function __construct(
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Get circulars filtered by the authenticated user's role.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $roleInfo = $this->permissionService->resolveUserRole($user);
        $userRole = (string) ($roleInfo['role'] ?? 'member');

        $now = now();

        $query = Circular::query()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->where(function ($q) use ($now): void {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', $now);
            });

        $circulars = $query
            ->orderByDesc('is_pinned')
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 3
                    WHEN 'important' THEN 2
                    ELSE 1
                END DESC
            ")
            ->orderByDesc('publish_date')
            ->take(50)
            ->get();

        // Filter by target_roles matching the user's role
        $filtered = $circulars->filter(function (Circular $c) use ($userRole): bool {
            $targetRoles = $c->target_roles;

            if ($targetRoles === null || empty($targetRoles)) {
                return true;
            }

            if (is_string($targetRoles)) {
                $targetRoles = json_decode($targetRoles, true);
            }

            if (! is_array($targetRoles)) {
                return true;
            }

            if (in_array('all', $targetRoles, true)) {
                return true;
            }

            return in_array($userRole, $targetRoles, true);
        });

        $data = $filtered->values()->map(function (Circular $c): array {
            $targetRoles = $c->target_roles;
            if (is_string($targetRoles)) {
                $targetRoles = json_decode($targetRoles, true);
            }

            $publishedAt = $c->publish_date ?? $c->created_at;
            $publishedAgo = $publishedAt instanceof Carbon
                ? $publishedAt->diffForHumans()
                : 'Recently';

            $creatorName = 'National Directorate';
            $creatorRole = 'Super Admin';
            if ($c->creator) {
                $creatorName = trim(($c->creator->first_name ?? '').' '.($c->creator->last_name ?? ''));
                if ($creatorName === '' || $creatorName === ' ') {
                    $creatorName = (string) ($c->creator->display_name ?? 'National Directorate');
                }
                $creatorRole = (string) ($c->creator->designation ?? 'Admin');
            }

            return [
                'id' => (string) $c->id,
                'title' => (string) $c->title,
                'content' => (string) ($c->content ?? $c->summary ?? ''),
                'target_roles' => is_array($targetRoles) ? $targetRoles : ['all'],
                'priority' => (string) ucfirst((string) ($c->priority ?? 'General')),
                'published_at' => $publishedAgo,
                'author_name' => $creatorName,
                'author_role' => $creatorRole,
                'attachment_url' => $c->attachment_url,
                'is_read' => false,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'data' => array_values($data),
        ]);
    }

    /**
     * Publish a new role-targeted circular.
     */
    public function publish(LeaderPublishCircularRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $roleInfo = $this->permissionService->resolveUserRole($user);
        $userRole = (string) ($roleInfo['role'] ?? 'member');

        $allowedPublishers = ['superAdmin', 'countryDirector', 'districtExecDirector', 'industryDirector'];
        if (! in_array($userRole, $allowedPublishers, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to publish circulars.',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $validated = $request->validated();

        $circular = Circular::query()->create([
            'id' => (string) Str::uuid(),
            'title' => (string) $validated['title'],
            'content' => (string) $validated['content'],
            'summary' => Str::limit((string) $validated['content'], 200),
            'target_roles' => $validated['target_roles'],
            'priority' => strtolower((string) $validated['priority']),
            'attachment_url' => $validated['attachment_url'] ?? null,
            'status' => 'published',
            'publish_date' => now(),
            'audience_type' => 'all_members',
            'category' => 'announcement',
            'created_by' => $user->id,
        ]);

        $creatorName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($creatorName === '' || $creatorName === ' ') {
            $creatorName = (string) ($user->display_name ?? 'National Directorate');
        }

        return response()->json([
            'success' => true,
            'message' => 'Circular published successfully',
            'data' => [
                'id' => (string) $circular->id,
                'title' => (string) $circular->title,
                'content' => (string) $circular->content,
                'target_roles' => $validated['target_roles'],
                'priority' => (string) ucfirst((string) $circular->priority),
                'published_at' => 'Just now',
                'author_name' => $creatorName,
                'author_role' => (string) ($roleInfo['custom_role_label'] ?? 'Super Admin'),
            ],
        ], 201);
    }
}
