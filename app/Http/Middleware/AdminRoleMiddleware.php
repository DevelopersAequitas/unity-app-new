<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $admin->loadMissing('roles:id,key');
        $adminRoleKeys = $admin->roles->pluck('key')->all();

        $requiredRoles = $this->normalizedRoles($allowedRoles);

        if (empty($requiredRoles)) {
            return $next($request);
        }

        if (in_array('global_admin', $adminRoleKeys, true)) {
            return $next($request);
        }

        if (! array_intersect($requiredRoles, $adminRoleKeys)) {
            return response()
                ->view('admin.errors.forbidden', [
                    'message' => 'You do not have permission to access this section.',
                ], 403);
        }

        return $next($request);
    }

    private function normalizedRoles(array $roles): array
    {
        return array_values(array_filter(array_map('trim', $roles)));
    }
}
