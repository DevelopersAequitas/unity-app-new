<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreServiceabilityRequest;
use App\Services\Store\ServiceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceabilityController extends BaseApiController
{
    protected ServiceabilityService $serviceabilityService;

    public function __construct(ServiceabilityService $serviceabilityService)
    {
        $this->serviceabilityService = $serviceabilityService;
    }

    public function check(StoreServiceabilityRequest $request): JsonResponse
    {
        $pincode = $request->input('pincode');
        $result = $this->serviceabilityService->checkPincode($pincode);

        return $this->success($result, 'Serviceability checked successfully');
    }

    public function pickupPoints(Request $request): JsonResponse
    {
        $pincode = $request->input('pincode');
        $points = $this->serviceabilityService->getPickupPoints($pincode);

        return $this->success($points, 'Pickup points retrieved');
    }

    public function pickupPointDetails(string $id): JsonResponse
    {
        $point = $this->serviceabilityService->getPickupPointById($id);
        if (! $point) {
            return $this->error('Pickup point not found', 404);
        }

        return $this->success($point, 'Pickup point details retrieved');
    }
}
