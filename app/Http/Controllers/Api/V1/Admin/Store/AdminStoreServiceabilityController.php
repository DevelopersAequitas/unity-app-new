<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use App\Models\Store\StoreBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminStoreServiceabilityController extends BaseApiController
{
    // ==========================================
    // PINCODES
    // ==========================================

    public function pincodes(Request $request): JsonResponse
    {
        $query = ServiceablePincode::orderBy('pincode', 'asc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pincode', 'ILIKE', "%{$search}%")
                    ->orWhere('city', 'ILIKE', "%{$search}%")
                    ->orWhere('state', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where(function ($q) use ($isActive) {
                if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
                    $q->where('is_active', $isActive);
                }
                if (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
                    $q->orWhere('serviceable', $isActive);
                }
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $pincodes = $query->paginate($perPage);

        return $this->success($pincodes, 'Serviceable pincodes retrieved');
    }

    public function storePincode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6|unique:serviceable_pincodes,pincode',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'serviceable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'delivery_days' => 'nullable|integer|min:1|max:30',
            'courier_code' => 'nullable|string|max:50',
        ]);

        $serviceable = $validated['serviceable'] ?? ($validated['is_active'] ?? true);

        $data = [
            'pincode' => $validated['pincode'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'delivery_days' => $validated['delivery_days'] ?? 3,
            'courier_code' => $validated['courier_code'] ?? 'STANDARD',
            'last_checked_at' => now(),
        ];

        if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
            $data['is_active'] = $serviceable;
        }
        if (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
            $data['serviceable'] = $serviceable;
        }

        $pincode = ServiceablePincode::create($data);

        return $this->success($pincode, 'Pincode added successfully', 201);
    }

    public function togglePincode(string $id): JsonResponse
    {
        $pincode = ServiceablePincode::findOrFail($id);
        $current = $pincode->is_active ?? $pincode->serviceable ?? true;
        $newState = ! $current;

        $updateData = [];
        if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
            $updateData['is_active'] = $newState;
        }
        if (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
            $updateData['serviceable'] = $newState;
        }

        $pincode->update($updateData);

        return $this->success($pincode, 'Pincode status updated');
    }

    public function deletePincode(string $id): JsonResponse
    {
        $pincode = ServiceablePincode::findOrFail($id);
        $pincode->delete();

        return $this->success(['deleted' => true], 'Pincode removed');
    }

    // ==========================================
    // PICKUP POINTS
    // ==========================================

    public function pickupPoints(Request $request): JsonResponse
    {
        $query = PickupPoint::orderBy('name', 'asc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_person', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_phone', 'ILIKE', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', strtoupper($status));
        }

        $perPage = (int) $request->input('per_page', 50);
        $points = $query->paginate($perPage);

        return $this->success($points, 'Pickup points retrieved');
    }

    public function storePickupPoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:500',
            'map_link' => 'nullable|string|url|max:500',
            'timings' => 'nullable|string|max:200',
            'contact_person' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:20',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        $point = PickupPoint::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'map_link' => $validated['map_link'] ?? null,
            'timings' => $validated['timings'] ?? '10:00 AM - 07:00 PM (Mon-Sat)',
            'contact_person' => $validated['contact_person'],
            'contact_phone' => $validated['contact_phone'],
            'status' => strtoupper($validated['status'] ?? 'ACTIVE'),
        ]);

        return $this->success($point, 'Pickup point created successfully', 201);
    }

    public function updatePickupPoint(Request $request, string $id): JsonResponse
    {
        $point = PickupPoint::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'address' => 'sometimes|required|string|max:500',
            'map_link' => 'nullable|string|url|max:500',
            'timings' => 'nullable|string|max:200',
            'contact_person' => 'sometimes|required|string|max:100',
            'contact_phone' => 'sometimes|required|string|max:20',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = strtoupper($validated['status']);
        }

        $point->update($validated);

        return $this->success($point, 'Pickup point updated successfully');
    }

    public function togglePickupPoint(string $id): JsonResponse
    {
        $point = PickupPoint::findOrFail($id);
        $newStatus = ($point->status === 'ACTIVE' || $point->status === '1') ? 'INACTIVE' : 'ACTIVE';
        $point->update(['status' => $newStatus]);

        return $this->success($point, 'Pickup point status updated');
    }

    public function deletePickupPoint(string $id): JsonResponse
    {
        $point = PickupPoint::findOrFail($id);
        $point->delete();

        return $this->success(['deleted' => true], 'Pickup point deleted');
    }

    // ==========================================
    // BANNERS
    // ==========================================

    public function banners(Request $request): JsonResponse
    {
        $query = StoreBanner::orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('subtitle', 'ILIKE', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', strtoupper($status));
        }

        $perPage = (int) $request->input('per_page', 50);
        $banners = $query->paginate($perPage);

        return $this->success($banners, 'Store banners retrieved');
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:300',
            'image_url' => 'required|string|url',
            'deep_link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
        ]);

        $banner = StoreBanner::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $validated['image_url'],
            'deep_link' => $validated['deep_link'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => strtoupper($validated['status'] ?? 'ACTIVE'),
            'start_at' => $validated['start_at'] ?? now(),
            'end_at' => $validated['end_at'] ?? null,
        ]);

        return $this->success($banner, 'Banner created successfully', 201);
    }

    public function updateBanner(Request $request, string $id): JsonResponse
    {
        $banner = StoreBanner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'subtitle' => 'nullable|string|max:300',
            'image_url' => 'sometimes|required|string|url',
            'deep_link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = strtoupper($validated['status']);
        }

        $banner->update($validated);

        return $this->success($banner, 'Banner updated successfully');
    }

    public function toggleBanner(string $id): JsonResponse
    {
        $banner = StoreBanner::findOrFail($id);
        $newStatus = ($banner->status === 'ACTIVE' || $banner->status === '1') ? 'INACTIVE' : 'ACTIVE';
        $banner->update(['status' => $newStatus]);

        return $this->success($banner, 'Banner status updated');
    }

    public function deleteBanner(string $id): JsonResponse
    {
        $banner = StoreBanner::findOrFail($id);
        $banner->delete();

        return $this->success(['deleted' => true], 'Banner deleted');
    }
}
