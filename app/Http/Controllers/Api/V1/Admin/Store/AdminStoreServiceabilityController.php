<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use App\Models\Store\StoreBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminStoreServiceabilityController extends BaseApiController
{
    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    // ==========================================
    // PINCODES
    // ==========================================

    public function pincodes(Request $request): JsonResponse
    {
        $like = $this->likeOperator();
        $query = ServiceablePincode::orderBy('pincode', 'asc');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search, $like) {
                $q->where('pincode', $like, "%{$search}%")
                    ->orWhere('city', $like, "%{$search}%")
                    ->orWhere('state', $like, "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where(function ($q) use ($isActive) {
                if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
                    $q->where('is_active', $isActive);
                } elseif (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
                    $q->where('serviceable', $isActive);
                }
            });
        }

        $perPage = max(1, min(200, (int) $request->input('per_page', 100)));
        $pincodes = $query->paginate($perPage);

        return $this->success($pincodes, 'Serviceable pincodes retrieved');
    }

    public function storePincode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => ['required', 'string', 'regex:/^\d{6}$/', 'unique:serviceable_pincodes,pincode'],
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'serviceable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'delivery_days' => 'nullable|integer|min:1|max:30',
            'delivery_sla_days' => 'nullable|integer|min:1|max:30',
            'estimated_delivery_days' => 'nullable|integer|min:1|max:30',
            'courier_code' => 'nullable|string|max:50',
        ]);

        $isActive = array_key_exists('is_active', $validated) && $validated['is_active'] !== null
            ? (bool) $validated['is_active']
            : (array_key_exists('serviceable', $validated) && $validated['serviceable'] !== null
                ? (bool) $validated['serviceable']
                : true);

        $deliveryDays = (int) ($validated['delivery_days']
            ?? $validated['delivery_sla_days']
            ?? $validated['estimated_delivery_days']
            ?? 3);

        $data = [
            'pincode' => trim($validated['pincode']),
            'city' => trim($validated['city']),
            'state' => trim($validated['state']),
            'delivery_days' => $deliveryDays,
            'courier_code' => $validated['courier_code'] ?? 'STANDARD',
            'last_checked_at' => now(),
        ];

        if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
            $data['is_active'] = $isActive;
        }
        if (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
            $data['serviceable'] = $isActive;
        }

        $pincode = ServiceablePincode::create($data);

        return $this->success($pincode, 'Pincode added successfully', 201);
    }

    public function updatePincode(Request $request, string $id): JsonResponse
    {
        $pincode = ServiceablePincode::findOrFail($id);

        $validated = $request->validate([
            'pincode' => ['sometimes', 'required', 'string', 'regex:/^\d{6}$/', Rule::unique('serviceable_pincodes', 'pincode')->ignore($pincode->id)],
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'serviceable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'delivery_days' => 'nullable|integer|min:1|max:30',
            'delivery_sla_days' => 'nullable|integer|min:1|max:30',
            'estimated_delivery_days' => 'nullable|integer|min:1|max:30',
            'courier_code' => 'nullable|string|max:50',
        ]);

        $updateData = [];
        if (isset($validated['pincode'])) {
            $updateData['pincode'] = trim($validated['pincode']);
        }
        if (isset($validated['city'])) {
            $updateData['city'] = trim($validated['city']);
        }
        if (isset($validated['state'])) {
            $updateData['state'] = trim($validated['state']);
        }

        $deliveryDays = $validated['delivery_days']
            ?? $validated['delivery_sla_days']
            ?? $validated['estimated_delivery_days']
            ?? null;
        if ($deliveryDays !== null) {
            $updateData['delivery_days'] = (int) $deliveryDays;
        }
        if (isset($validated['courier_code'])) {
            $updateData['courier_code'] = $validated['courier_code'];
        }

        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $newState = (bool) $validated['is_active'];
        } elseif (array_key_exists('serviceable', $validated) && $validated['serviceable'] !== null) {
            $newState = (bool) $validated['serviceable'];
        } else {
            $newState = null;
        }

        if ($newState !== null) {
            if (Schema::hasColumn('serviceable_pincodes', 'is_active')) {
                $updateData['is_active'] = $newState;
            }
            if (Schema::hasColumn('serviceable_pincodes', 'serviceable')) {
                $updateData['serviceable'] = $newState;
            }
        }

        if (Schema::hasColumn('serviceable_pincodes', 'last_checked_at')) {
            $updateData['last_checked_at'] = now();
        }

        $pincode->update($updateData);

        return $this->success($pincode->fresh(), 'Pincode updated successfully');
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

        return $this->success($pincode->fresh(), 'Pincode status updated');
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
        $like = $this->likeOperator();
        $query = PickupPoint::orderBy('name', 'asc')->orderBy('created_at', 'desc');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                    ->orWhere('address', $like, "%{$search}%")
                    ->orWhere('contact_person', $like, "%{$search}%")
                    ->orWhere('contact_phone', $like, "%{$search}%");
                if (Schema::hasColumn('pickup_points', 'city')) {
                    $q->orWhere('city', $like, "%{$search}%");
                }
                if (Schema::hasColumn('pickup_points', 'state')) {
                    $q->orWhere('state', $like, "%{$search}%");
                }
                if (Schema::hasColumn('pickup_points', 'pincode')) {
                    $q->orWhere('pincode', $like, "%{$search}%");
                }
            });
        }

        if ($status = $request->input('status')) {
            $upper = strtoupper((string) $status);
            if (Schema::hasColumn('pickup_points', 'status')) {
                $query->where('status', $upper);
            } elseif (Schema::hasColumn('pickup_points', 'is_active')) {
                $query->where('is_active', $upper === 'ACTIVE');
            }
        }

        $perPage = max(1, min(200, (int) $request->input('per_page', 100)));
        $points = $query->paginate($perPage);

        return $this->success($points, 'Pickup points retrieved');
    }

    public function storePickupPoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'address' => 'nullable|string|max:500',
            'full_address' => 'nullable|string|max:500',
            'address_line1' => 'nullable|string|max:250',
            'address_line2' => 'nullable|string|max:250',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'map_link' => 'nullable|string|url|max:500',
            'timings' => 'nullable|string|max:200',
            'operating_hours' => 'nullable|string|max:200',
            'contact_person' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:25',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE,active,inactive',
            'is_active' => 'nullable|boolean',
        ]);

        $name = trim($validated['name']);
        $address = trim((string) ($validated['address'] ?? $validated['full_address'] ?? $validated['address_line1'] ?? ''));
        if ($address === '') {
            return $this->error('Address is required.', 422);
        }

        // Prevent duplicate pickup hubs (case-insensitive match on name + address)
        $duplicate = PickupPoint::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->whereRaw('LOWER(TRIM(COALESCE(address, address_line1, \'\'))) = ?', [mb_strtolower($address)])
            ->first();

        if ($duplicate) {
            return $this->error("A pickup point named \"{$name}\" with this address already exists.", 422);
        }

        $isActive = array_key_exists('is_active', $validated) && $validated['is_active'] !== null
            ? (bool) $validated['is_active']
            : (! isset($validated['status']) || strtoupper($validated['status']) === 'ACTIVE');
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';

        $timings = trim((string) ($validated['timings'] ?? $validated['operating_hours'] ?? '10:00 AM - 07:00 PM (Mon-Sat)'));

        $point = PickupPoint::create([
            'name' => $name,
            'address' => $address,
            'address_line1' => $validated['address_line1'] ?? $address,
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => isset($validated['city']) ? trim($validated['city']) : null,
            'state' => isset($validated['state']) ? trim($validated['state']) : null,
            'pincode' => isset($validated['pincode']) ? trim($validated['pincode']) : null,
            'country' => 'India',
            'map_link' => $validated['map_link'] ?? null,
            'timings' => $timings,
            'working_hours' => $timings,
            'contact_person' => trim($validated['contact_person']),
            'contact_phone' => trim($validated['contact_phone']),
            'phone' => trim($validated['contact_phone']),
            'manager_name' => trim($validated['contact_person']),
            'status' => $status,
            'is_active' => $isActive,
        ]);

        return $this->success($point->fresh(), 'Pickup point created successfully', 201);
    }

    public function updatePickupPoint(Request $request, string $id): JsonResponse
    {
        $point = PickupPoint::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'address' => 'nullable|string|max:500',
            'full_address' => 'nullable|string|max:500',
            'address_line1' => 'nullable|string|max:250',
            'address_line2' => 'nullable|string|max:250',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'map_link' => 'nullable|string|url|max:500',
            'timings' => 'nullable|string|max:200',
            'operating_hours' => 'nullable|string|max:200',
            'contact_person' => 'sometimes|required|string|max:100',
            'contact_phone' => 'sometimes|required|string|max:25',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE,active,inactive',
            'is_active' => 'nullable|boolean',
        ]);

        $newName = isset($validated['name']) ? trim($validated['name']) : (string) $point->name;
        $newAddress = isset($validated['address']) || isset($validated['full_address']) || isset($validated['address_line1'])
            ? trim((string) ($validated['address'] ?? $validated['full_address'] ?? $validated['address_line1'] ?? ''))
            : (string) $point->address;

        // Prevent duplicate pickup hubs on rename/address update
        $duplicate = PickupPoint::query()
            ->where('id', '!=', $point->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($newName)])
            ->whereRaw('LOWER(TRIM(COALESCE(address, address_line1, \'\'))) = ?', [mb_strtolower($newAddress)])
            ->first();

        if ($duplicate) {
            return $this->error("Another pickup point named \"{$newName}\" with this address already exists.", 422);
        }

        $updateData = [
            'name' => $newName,
            'address' => $newAddress,
        ];

        if (isset($validated['address_line1']) || isset($validated['address']) || isset($validated['full_address'])) {
            $updateData['address_line1'] = $validated['address_line1'] ?? $newAddress;
        }
        if (array_key_exists('address_line2', $validated)) {
            $updateData['address_line2'] = $validated['address_line2'];
        }
        if (array_key_exists('city', $validated)) {
            $updateData['city'] = $validated['city'] !== null ? trim($validated['city']) : null;
        }
        if (array_key_exists('state', $validated)) {
            $updateData['state'] = $validated['state'] !== null ? trim($validated['state']) : null;
        }
        if (array_key_exists('pincode', $validated)) {
            $updateData['pincode'] = $validated['pincode'] !== null ? trim($validated['pincode']) : null;
        }
        if (array_key_exists('map_link', $validated)) {
            $updateData['map_link'] = $validated['map_link'];
        }
        if (isset($validated['timings']) || isset($validated['operating_hours'])) {
            $timings = trim((string) ($validated['timings'] ?? $validated['operating_hours']));
            $updateData['timings'] = $timings;
            $updateData['working_hours'] = $timings;
        }
        if (isset($validated['contact_person'])) {
            $updateData['contact_person'] = trim($validated['contact_person']);
            $updateData['manager_name'] = trim($validated['contact_person']);
        }
        if (isset($validated['contact_phone'])) {
            $updateData['contact_phone'] = trim($validated['contact_phone']);
            $updateData['phone'] = trim($validated['contact_phone']);
        }

        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $isActive = (bool) $validated['is_active'];
            $updateData['is_active'] = $isActive;
            $updateData['status'] = $isActive ? 'ACTIVE' : 'INACTIVE';
        } elseif (isset($validated['status'])) {
            $status = strtoupper($validated['status']);
            $updateData['status'] = $status;
            $updateData['is_active'] = $status === 'ACTIVE';
        }

        $point->update($updateData);

        return $this->success($point->fresh(), 'Pickup point updated successfully');
    }

    public function togglePickupPoint(string $id): JsonResponse
    {
        $point = PickupPoint::findOrFail($id);
        $currentlyActive = (bool) $point->is_active;
        $newActive = ! $currentlyActive;
        $point->update([
            'status' => $newActive ? 'ACTIVE' : 'INACTIVE',
            'is_active' => $newActive,
        ]);

        return $this->success($point->fresh(), 'Pickup point status updated');
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
