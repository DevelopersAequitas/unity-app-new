<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\EntitlementService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DigitalLibraryController extends BaseApiController
{
    protected EntitlementService $entitlementService;

    public function __construct(EntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $library = $this->entitlementService->getUserLibrary($user, $perPage);

        return $this->success($library, 'User digital library retrieved');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $entitlement = $this->entitlementService->getEntitlementDetails($user, $id);

            return $this->success($entitlement, 'Entitlement details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function access(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $access = $this->entitlementService->generateSignedAccessUrl($user, $id);

            return $this->success($access, 'Temporary access URL generated');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 403);
        }
    }
}
