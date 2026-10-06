<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\Entitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreEntitlementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Entitlement::with(['user', 'product']);
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $perPage = (int) $request->input('per_page', 20);
        $entitlements = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($entitlements, 'Admin entitlements retrieved');
    }

    public function show(string $id): JsonResponse
    {
        $entitlement = Entitlement::with(['user', 'product', 'order'])->findOrFail($id);

        return $this->success($entitlement, 'Entitlement details retrieved');
    }

    public function grant(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'product_id' => 'required|uuid|exists:products,id',
            'feature_key' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $entitlement = Entitlement::create([
            'user_id' => $request->input('user_id'),
            'product_id' => $request->input('product_id'),
            'source_type' => 'ADMIN_GRANT',
            'feature_key' => $request->input('feature_key'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        return $this->success($entitlement, 'Entitlement granted successfully', 201);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $entitlement = Entitlement::findOrFail($id);
        $admin = $request->user();

        $entitlement->update([
            'status' => 'REVOKED',
            'is_active' => false,
            'revoked_by' => $admin ? $admin->id : null,
            'revoked_at' => now(),
            'revoke_reason' => $request->input('reason'),
        ]);

        return $this->success($entitlement, 'Entitlement revoked successfully');
    }
}
