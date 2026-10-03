<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUnityUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow if the user is a standard User OR an AdminUser
        if (! $user instanceof User && ! $user instanceof \App\Models\AdminUser) {
            return response()->json([
                'success' => false,
                'message' => 'This API is only available for Unity users and Administrators.',
            ], 403);
        }

        return $next($request);
    }
}
