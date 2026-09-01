<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Services\SystemAppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderSystemController extends Controller
{
    public function __construct(
        private readonly SystemAppConfigService $systemAppConfigService
    ) {}

    /**
     * Get Leader App system configuration for force/optional update and maintenance mode.
     */
    public function appConfig(Request $request): JsonResponse
    {
        $platform = (string) ($request->query('platform') ?? $request->header('X-Platform') ?? 'android');
        $data = $this->systemAppConfigService->getSystemAppConfig('leader', $platform);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Leader app system configuration retrieved successfully.',
            'data' => $data,
        ]);
    }
}
