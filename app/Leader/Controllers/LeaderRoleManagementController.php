<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderCreateRoleRequest;
use App\Leader\Requests\LeaderUpdateRoleMatrixRequest;
use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Leader\Services\LeaderRoleMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderRoleManagementController extends Controller
{
    public function __construct(
        private readonly LeaderRoleMatrixService $roleMatrixService,
    ) {}

    /**
     * Get 12-capability definitions and active roles matrix.
     */
    public function matrix(): JsonResponse
    {
        $data = $this->roleMatrixService->getMatrix();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update role capability assignments.
     */
    public function updateMatrix(LeaderUpdateRoleMatrixRequest $request): JsonResponse
    {
        $roleId = (string) $request->validated('role_id');
        $this->roleMatrixService->updateMatrix(
            $roleId,
            (array) $request->validated('enabled_capabilities'),
        );

        try {
            app(LeaderNotificationDispatcherService::class)->dispatchEvent(
                'role_matrix_modified',
                'Role Capability Matrix Updated',
                "Permissions matrix was updated for role {$roleId}.",
                [
                    'actor_id' => (string) ($request->user()?->id ?? ''),
                    'entity_id' => $roleId,
                    'reference_id' => $roleId,
                    'idempotency_window' => $roleId.'_'.date('Y-m-d-H'),
                ]
            );
        } catch (\Throwable) {
            // Non-blocking notification dispatch
        }

        return response()->json([
            'success' => true,
            'message' => 'Role capabilities updated successfully.',
        ]);
    }

    /**
     * Create a new custom role.
     */
    public function store(LeaderCreateRoleRequest $request): JsonResponse
    {
        $role = $this->roleMatrixService->createRole(
            (string) $request->validated('label'),
            (array) $request->validated('enabled_capabilities'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Custom role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Update custom role label / capabilities.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $label = $request->input('label') ? (string) $request->input('label') : null;
        $capabilities = $request->input('enabled_capabilities') ? (array) $request->input('enabled_capabilities') : null;

        $this->roleMatrixService->updateRole($id, $label, $capabilities);

        return response()->json([
            'success' => true,
            'message' => 'Custom role updated successfully.',
        ]);
    }

    /**
     * Delete a custom role.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->roleMatrixService->deleteRole($id);

        return response()->json([
            'success' => true,
            'message' => 'Custom role deleted successfully.',
        ]);
    }
}
