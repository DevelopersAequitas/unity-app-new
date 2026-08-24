<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Throwable;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    /**
     * Public API endpoint to check app maintenance mode status.
     */
    public function index(): JsonResponse
    {
        try {
            $data = $this->maintenanceService->getCurrentMaintenanceStatus();

            return response()->json([
                'status' => true,
                'message' => 'Maintenance status fetched',
                'data' => $data,
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch maintenance status at the moment.',
                'data' => null,
            ], 500);
        }
    }
}
