<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\UserMobileVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserMobileVersionController extends BaseApiController
{
    /**
     * Store or update the authenticated user's mobile app version details.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['required', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;

        $userMobileVersion = UserMobileVersion::updateOrCreate(
            [
                'user_id' => $userId,
                'platform' => strtolower($validated['platform']),
            ],
            [
                'app_version' => $validated['app_version'],
                'device_model' => $validated['device_model'] ?? null,
                'os_version' => $validated['os_version'] ?? null,
            ]
        );

        return $this->success($userMobileVersion, 'User mobile version stored successfully.');
    }
}
