<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            if ($request->bearerToken()) {
                $token = $request->bearerToken();
                /** @var AdminUser|User|null $user */
                $user = Auth::guard('sanctum')->user();

                if (! $user && class_exists(PersonalAccessToken::class)) {
                    $tokenModel = PersonalAccessToken::findToken($token);
                    if ($tokenModel && (! $tokenModel->expires_at || ! $tokenModel->expires_at->isPast())) {
                        $user = $tokenModel->tokenable;
                    }
                }

                if ($user instanceof AdminUser) {
                    Auth::guard('admin')->setUser($user);
                } elseif ($user instanceof User) {
                    $admin = AdminUser::query()
                        ->where('id', $user->id)
                        ->orWhere('email', $user->email)
                        ->first();
                    if ($admin) {
                        Auth::guard('admin')->setUser($admin);
                    }
                }
            }
        }

        if (! Auth::guard('admin')->check()) {
            $adminId = $request->session()->get('admin_user_id');
            if ($adminId) {
                $admin = AdminUser::find($adminId);
                if ($admin) {
                    Auth::guard('admin')->login($admin);
                }
            }
        }

        if (! Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
