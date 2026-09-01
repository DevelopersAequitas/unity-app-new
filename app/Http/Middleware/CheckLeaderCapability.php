<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Leader\LeaderPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLeaderCapability
{
    public function __construct(
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $capabilityOrFlag): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required to access this resource.',
                'details' => null,
            ], 401);
        }

        $roleInfo = $this->permissionService->resolveUserRole($user);
        $role = $roleInfo['role'];

        $permissions = $this->permissionService->resolvePermissionMatrix($role);
        $enabledCapabilities = $this->permissionService->getEnabledCapabilitiesForRole($role);

        $allowed = false;

        if (isset($permissions[$capabilityOrFlag])) {
            $allowed = (bool) $permissions[$capabilityOrFlag];
        } elseif (in_array($capabilityOrFlag, $enabledCapabilities, true)) {
            $allowed = true;
        }

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHORIZED_ACCESS',
                'message' => "You do not have permission for capability [{$capabilityOrFlag}].",
                'details' => null,
            ], 403);
        }

        return $next($request);
    }
}
