<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StorePolicyService;
use Exception;
use Illuminate\Http\JsonResponse;

class StorePolicyController extends BaseApiController
{
    protected StorePolicyService $policyService;

    public function __construct(StorePolicyService $policyService)
    {
        $this->policyService = $policyService;
    }

    public function index(): JsonResponse
    {
        $policies = $this->policyService->getPublishedPolicies();

        return $this->success($policies, 'Published policies retrieved');
    }

    public function show(string $key): JsonResponse
    {
        try {
            $policy = $this->policyService->getPolicyByKey($key);

            return $this->success($policy, 'Policy retrieved successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }
}
