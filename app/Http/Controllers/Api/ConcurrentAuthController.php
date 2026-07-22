<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\ForceLogoutEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ConcurrentAuthController extends Controller
{
    public function __construct(
        protected UserSessionService $sessionService
    ) {}

    /**
     * Handle user login with strict one-active-device concurrent session control.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash ?? $user->password ?? '')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $userId = (string) $user->id;
        $deviceId = $credentials['device_id'] ?? $request->header('User-Agent');

        // 1. Emit real-time FORCE_LOGOUT WebSocket event to existing active connections BEFORE registering new device
        event(new ForceLogoutEvent(
            userId: $userId,
            message: 'You have been logged out because your account was accessed on another device.',
            newDeviceId: $deviceId
        ));

        // 2. Generate a new unique session ID
        $newSessionId = $this->sessionService->generateSessionId();

        // 3. Overwrite any existing active session ID in Redis for this user
        $this->sessionService->setActiveSession($userId, $newSessionId);

        // 4. Issue token with session_id claim
        $token = $user->createToken('session:'.$newSessionId)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'session_id' => $newSessionId,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name ?? $user->display_name ?? '',
                ],
            ],
        ]);
    }

    /**
     * Handle user logout and clear Redis active session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->sessionService->invalidateSession((string) $user->id);
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
