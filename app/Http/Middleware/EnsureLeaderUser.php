<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Leader\LeaderPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLeaderUser
{
    public function __construct(
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required to access this resource.',
                'details' => null,
            ], 401);
        }

        if (! $this->permissionService->isLeader($user)) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_A_LEADER',
                'message' => 'Access denied. Only peers with an assigned leadership role can access the Leader App.',
                'details' => null,
            ], 403);
        }

        return $next($request);
    }
}
