<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\UserMobileDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserMobileDetailController extends BaseApiController
{
    /**
     * Register or update the current mobile device details.
     * Revokes any other active token for the same user and platform on a different device.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_type' => ['required', 'string', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $userId = $user->id;
        $currentAccessToken = $user->currentAccessToken();
        $tokenId = $currentAccessToken ? (string) $currentAccessToken->id : null;

        // 1. Revoke any other device on the same platform
        $oldDevices = UserMobileDetail::where('user_id', $userId)
            ->where('device_type', $validated['device_type'])
            ->where('device_id', '!=', $validated['device_id'])
            ->get();

        foreach ($oldDevices as $oldDevice) {
            if ($oldDevice->token_id) {
                $user->tokens()->where('id', $oldDevice->token_id)->delete();
            }
            $oldDevice->delete();
        }

        // 2. Revoke previous token for the same device if logging in again
        $existingSameDevice = UserMobileDetail::where('user_id', $userId)
            ->where('device_id', $validated['device_id'])
            ->first();

        if ($existingSameDevice && $existingSameDevice->token_id && $existingSameDevice->token_id !== $tokenId) {
            $user->tokens()->where('id', $existingSameDevice->token_id)->delete();
        }

        // 3. Register or update the current device
        $device = UserMobileDetail::updateOrCreate(
            [
                'user_id' => $userId,
                'device_id' => $validated['device_id'],
            ],
            [
                'device_type' => $validated['device_type'],
                'device_name' => $validated['device_name'] ?? null,
                'os_version' => $validated['os_version'] ?? null,
                'token_id' => $tokenId,
                'last_login_at' => now(),
            ]
        );

        return $this->success($device->toArray(), 'Device registered successfully.');
    }

    /**
     * Manually logout a specific device by revoking its token.
     */
    public function logoutDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $userId = $user->id;

        $device = UserMobileDetail::where('user_id', $userId)
            ->where('device_id', $validated['device_id'])
            ->first();

        if ($device) {
            if ($device->token_id) {
                $user->tokens()->where('id', $device->token_id)->delete();
            }
            $device->delete();

            return $this->success(null, 'Device logged out successfully.');
        }

        return $this->error('Device not found.', 404);
    }

    /**
     * List all registered active devices for the authenticated user.
     */
    public function listDevices(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $devices = UserMobileDetail::where('user_id', $userId)->get();

        return $this->success($devices->toArray(), 'Active devices list retrieved successfully.');
    }
}
