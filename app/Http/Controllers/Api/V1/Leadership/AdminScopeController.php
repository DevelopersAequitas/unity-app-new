<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScopeController extends LeadershipBaseController
{
    public function __construct(
        protected ScopeService $scopeService
    ) {}

    /**
     * List campaign scopes for admin.
     */
    public function index(string $campaignId): JsonResponse
    {
        $scopes = $this->scopeService->getAdminScopes($campaignId);

        return $this->success($scopes, 'Campaign scopes fetched successfully.');
    }

    /**
     * Create campaign scope.
     */
    public function store(Request $request, string $campaignId): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => 'required|in:national,state,district,city,industry,circle,custom',
            'scope_name' => 'required|string|max:200',
            'scope_reference_id' => 'nullable|uuid',
            'parent_scope_id' => 'nullable|uuid',
            'settings' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ]);

        try {
            $scope = $this->scopeService->createScope($campaignId, $validated);

            return $this->success($scope, 'Scope created successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Update campaign scope.
     */
    public function update(Request $request, string $scopeId): JsonResponse
    {
        try {
            $scope = $this->scopeService->updateScope($scopeId, $request->all());

            return $this->success($scope, 'Scope updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Deactivate scope.
     */
    public function destroy(string $scopeId): JsonResponse
    {
        try {
            $scope = $this->scopeService->deactivateScope($scopeId);

            return $this->success($scope, 'Scope deactivated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
