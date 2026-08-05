<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Rbac\RbacService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPagePermission
{
    public function __construct(private readonly RbacService $rbac) {}

    /**
     * Usage in routes:
     *   ->middleware('rbac.page:view')
     *   ->middleware('rbac.page:edit')
     *   ->middleware('rbac.page:approve')
     *
     * The route_name of the current route is automatically matched
     * against admin_pages.route_name.
     */
    public function handle(Request $request, Closure $next, string $permissionKey = 'view'): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $routeName = $request->route()->getName();

        if (! $routeName) {
            // If route has no name we cannot check — let it through
            return $next($request);
        }

        if (! $this->rbac->can($admin, $routeName, $permissionKey)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. You do not have permission to perform this action.'], 403);
            }

            return response()
                ->view('admin.errors.forbidden', [
                    'message' => 'You do not have the required permission to access this page.',
                ], 403);
        }

        return $next($request);
    }
}
