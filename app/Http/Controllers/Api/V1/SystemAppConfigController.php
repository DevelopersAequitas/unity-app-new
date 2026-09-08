<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SystemAppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SystemAppConfigController extends Controller
{
    public function __construct(
        private readonly SystemAppConfigService $systemAppConfigService
    ) {}

    /**
     * Get Remote System Configuration, App Version & Maintenance Control.
     *
     * Mapped in the client app via AppConfigModel.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $product = (string) ($request->query('product') ?? $request->header('X-Product') ?? 'peers');
            $platform = (string) ($request->query('platform') ?? $request->header('X-Platform') ?? 'android');

            $data = $this->systemAppConfigService->getSystemAppConfig($product, $platform);

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'System app configuration retrieved successfully.',
                'data' => $data,
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Unable to fetch system app configuration at the moment.',
                'data' => null,
            ], 500);
        }
    }
}
