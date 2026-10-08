<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use App\Models\Store\StoreBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStoreServiceabilityWebController extends Controller
{
    // ==========================================
    // SERVICEABLE PINCODES
    // ==========================================

    public function pincodes(Request $request)
    {
        $query = ServiceablePincode::orderBy('pincode', 'asc');
        $search = $request->input('search', '');
        $activeFilter = $request->input('is_active', '');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('pincode', 'ILIKE', "%{$s}%")
                    ->orWhere('city', 'ILIKE', "%{$s}%")
                    ->orWhere('state', 'ILIKE', "%{$s}%");
            });
        }

        if ($activeFilter !== '' && $activeFilter !== null) {
            $query->where('is_active', $activeFilter === '1');
        }

        $pincodes = $query->paginate(25);

        return view('admin.store.serviceability.pincodes', compact('pincodes', 'search', 'activeFilter'));
    }

    public function storePincode(Request $request)
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6|unique:serviceable_pincodes,pincode',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'delivery_available' => 'nullable|boolean',
            'pickup_available' => 'nullable|boolean',
            'courier_partner' => 'nullable|string|max:100',
            'delivery_days' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['delivery_available'] = $request->has('delivery_available');
        $validated['pickup_available'] = $request->has('pickup_available');
        $validated['is_active'] = $request->has('is_active');
        $validated['delivery_days'] = $validated['delivery_days'] ?? 4;

        ServiceablePincode::create($validated);

        return back()->with('success', 'Pincode coverage added.');
    }

    public function togglePincode(string $id)
    {
        $p = ServiceablePincode::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);

        return back()->with('success', "Pincode {$p->pincode} visibility updated.");
    }

    public function exportPincodesCsv(): StreamedResponse
    {
        $pincodes = ServiceablePincode::orderBy('pincode')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="serviceable_pincodes_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($pincodes) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pincode', 'City', 'State', 'Delivery Available', 'Pickup Available', 'Courier Partner', 'Estimated Transit Days', 'Is Active']);

            foreach ($pincodes as $p) {
                fputcsv($handle, [
                    $p->pincode,
                    $p->city,
                    $p->state,
                    $p->delivery_available ? 'YES' : 'NO',
                    $p->pickup_available ? 'YES' : 'NO',
                    $p->courier_partner ?? 'Standard Delivery',
                    $p->delivery_days ?? 4,
                    $p->is_active ? 'ACTIVE' : 'INACTIVE',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    // ==========================================
    // PICKUP HUBS & POINTS
    // ==========================================

    public function pickupPoints(Request $request)
    {
        $query = PickupPoint::orderBy('name', 'asc');
        $search = $request->input('search', '');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ILIKE', "%{$s}%")
                    ->orWhere('city', 'ILIKE', "%{$s}%")
                    ->orWhere('code', 'ILIKE', "%{$s}%");
            });
        }

        $points = $query->paginate(15);
        $pickupPoints = $points;

        return view('admin.store.serviceability.pickup-points', compact('points', 'pickupPoints', 'search'));
    }

    public function storePickupPoint(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'address_line1' => 'required|string|max:250',
            'address_line2' => 'nullable|string|max:250',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'required|string|size:6',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:25',
            'operating_hours' => 'nullable|string|max:100',
            'is_active' => 'nullable',
        ]);

        $addressParts = array_filter([
            $request->address_line1,
            $request->address_line2,
            $request->city,
            ($request->state ? $request->state : '').($request->pincode ? ' - '.$request->pincode : ''),
        ]);
        $fullAddress = implode(', ', $addressParts);

        $isActive = $request->has('is_active');
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';
        $code = 'HUB-' . strtoupper(Str::random(6));

        PickupPoint::create([
            'code' => $code,
            'name' => $request->name,
            'address' => $fullAddress,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'country' => 'India',
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'timings' => $request->operating_hours ?: '10:00 AM - 07:00 PM',
            'status' => $status,
        ]);

        return back()->with('success', 'Central Pickup Hub location created successfully.');
    }

    public function updatePickupPoint(Request $request, string $id)
    {
        $point = PickupPoint::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'address_line1' => 'required|string|max:250',
            'address_line2' => 'nullable|string|max:250',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:25',
            'operating_hours' => 'nullable|string|max:100',
            'is_active' => 'nullable',
        ]);

        $addressParts = array_filter([
            $request->address_line1,
            $request->address_line2,
            $request->city,
            ($request->state ? $request->state : '').($request->pincode ? ' - '.$request->pincode : ''),
        ]);
        $fullAddress = count($addressParts) > 0 ? implode(', ', $addressParts) : $request->address_line1;

        $isActive = $request->has('is_active');
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';

        $point->update([
            'name' => $request->name,
            'address' => $fullAddress,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'timings' => $request->operating_hours ?: ($point->timings ?: '10:00 AM - 07:00 PM'),
            'status' => $status,
        ]);

        return back()->with('success', 'Pickup Hub location updated successfully.');
    }

    public function togglePickupPoint(string $id)
    {
        $point = PickupPoint::findOrFail($id);
        $currentActive = ($point->status === 'ACTIVE' || $point->status === '1');
        $newStatus = $currentActive ? 'INACTIVE' : 'ACTIVE';

        $point->update([
            'status' => $newStatus,
        ]);

        return back()->with('success', 'Pickup Hub status toggled.');
    }

    // ==========================================
    // STORE BANNERS
    // ==========================================

    public function banners(Request $request)
    {
        $banners = StoreBanner::orderBy('sort_order', 'asc')->paginate(15);

        return view('admin.store.serviceability.banners', compact('banners'));
    }

    public function storeBanner(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:250',
            'image' => 'nullable|image|max:10240',
            'image_url' => 'nullable|string|max:500',
            'action_type' => 'nullable|string|max:50',
            'action_value' => 'nullable|string|max:250',
            'link_type' => 'nullable|string|max:50',
            'link_value' => 'nullable|string|max:250',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image')) {
            try {
                $fileModel = app(\App\Services\Media\FileUploadService::class)->store($request->file('image'), \Illuminate\Support\Facades\Auth::guard('admin')->user());
                $imageUrl = url('/api/v1/files/' . $fileModel->id);
            } catch (\Throwable $e) {
                $file = $request->file('image');
                $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/store/banners');
                if (! file_exists($path)) {
                    @mkdir($path, 0755, true);
                }
                $file->move($path, $filename);
                $imageUrl = asset('uploads/store/banners/' . $filename);
            }
        }

        if (empty($imageUrl)) {
            $imageUrl = 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1200&auto=format&fit=crop&q=80';
        }

        $actionType = $request->input('action_type', $request->input('link_type', 'URL'));
        $actionValue = $request->input('action_value', $request->input('link_value', ''));
        $isActive = $request->has('is_active');

        StoreBanner::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $imageUrl,
            'action_type' => $actionType,
            'action_value' => $actionValue,
            'link_type' => $actionType,
            'link_value' => $actionValue,
            'deep_link' => $actionValue,
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'is_active' => $isActive,
            'status' => $isActive ? 'ACTIVE' : 'INACTIVE',
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return back()->with('success', 'Store banner added successfully.');
    }

    public function updateBanner(Request $request, string $id)
    {
        $banner = StoreBanner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:250',
            'image' => 'nullable|image|max:10240',
            'image_url' => 'nullable|string|max:500',
            'action_type' => 'nullable|string|max:50',
            'action_value' => 'nullable|string|max:250',
            'link_type' => 'nullable|string|max:50',
            'link_value' => 'nullable|string|max:250',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        $imageUrl = $banner->getRawOriginal('image_url');
        if ($request->hasFile('image')) {
            try {
                $fileModel = app(\App\Services\Media\FileUploadService::class)->store($request->file('image'), \Illuminate\Support\Facades\Auth::guard('admin')->user());
                $imageUrl = url('/api/v1/files/' . $fileModel->id);
            } catch (\Throwable $e) {
                $file = $request->file('image');
                $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/store/banners');
                if (! file_exists($path)) {
                    @mkdir($path, 0755, true);
                }
                $file->move($path, $filename);
                $imageUrl = asset('uploads/store/banners/' . $filename);
            }
        } elseif ($request->filled('image_url')) {
            $imageUrl = $request->image_url;
        }

        if (empty($imageUrl)) {
            $imageUrl = 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1200&auto=format&fit=crop&q=80';
        }

        $actionType = $request->input('action_type', $request->input('link_type', $banner->action_type ?: 'URL'));
        $actionValue = $request->input('action_value', $request->input('link_value', $banner->action_value ?: ''));
        $isActive = $request->has('is_active');

        $banner->update([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $imageUrl,
            'action_type' => $actionType,
            'action_value' => $actionValue,
            'link_type' => $actionType,
            'link_value' => $actionValue,
            'deep_link' => $actionValue,
            'sort_order' => (int) ($validated['sort_order'] ?? $banner->sort_order),
            'is_active' => $isActive,
            'status' => $isActive ? 'ACTIVE' : 'INACTIVE',
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return back()->with('success', 'Store banner updated successfully.');
    }

    public function toggleBanner(string $id)
    {
        $banner = StoreBanner::findOrFail($id);
        $newActive = ! $banner->is_active;
        $banner->update([
            'is_active' => $newActive,
            'status' => $newActive ? 'ACTIVE' : 'INACTIVE',
        ]);

        return back()->with('success', 'Store banner status updated.');
    }

    public function deleteBanner(string $id)
    {
        $banner = StoreBanner::findOrFail($id);
        $banner->delete();

        return back()->with('success', 'Store banner deleted.');
    }
}
