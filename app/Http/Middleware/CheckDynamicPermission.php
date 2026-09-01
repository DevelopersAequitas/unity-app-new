<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckDynamicPermission
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $routeName = $request->route()?->getName() ?? '';

        if ($routeName === '') {
            return $next($request);
        }

        // Always allow these core routes
        $alwaysAllowed = [
            'admin.logout',
            'admin.login',
            'admin.login.send-otp',
            'admin.login.verify',
            'admin.home',
            'admin.files.upload',
            'admin.switch-context',
            'admin.profile.remove-current-role',
            'admin.location.states.districts',
        ];

        if (in_array($routeName, $alwaysAllowed, true)) {
            return $next($request);
        }

        if (! $this->permissionService->canAccessRoute($admin, $routeName)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to access this page.',
                ], 403);
            }

            return response()
                ->view('admin.errors.forbidden', [
                    'message' => 'You do not have permission to access this section.',
                ], 403);
        }

        // Inject permission service into request for Blade checks
        $request->attributes->set('permissionService', $this->permissionService);

        return $next($request);
    }
}
