<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use App\Models\Store\StoreBanner;
use Illuminate\Http\Request;
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
            'is_active' => 'nullable|boolean'
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
        $p->update(['is_active' => !$p->is_active]);

        return back()->with('success', "Pincode {$p->pincode} visibility updated.");
    }

    public function exportPincodesCsv(): StreamedResponse
    {
        $pincodes = ServiceablePincode::orderBy('pincode')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="serviceable_pincodes_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function() use ($pincodes) {
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
                    $p->is_active ? 'ACTIVE' : 'INACTIVE'
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
            'is_active' => 'nullable'
        ]);

        $addressParts = array_filter([
            $request->address_line1,
            $request->address_line2,
            $request->city,
            ($request->state ? $request->state : '') . ($request->pincode ? ' - ' . $request->pincode : '')
        ]);
        $fullAddress = implode(', ', $addressParts);

        $isActive = $request->has('is_active');
        $status = $isActive ? 'ACTIVE' : 'INACTIVE';

        PickupPoint::create([
            'name' => $request->name,
            'address' => $fullAddress,
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
            'is_active' => 'nullable'
        ]);

        $addressParts = array_filter([
            $request->address_line1,
            $request->address_line2,
            $request->city,
            ($request->state ? $request->state : '') . ($request->pincode ? ' - ' . $request->pincode : '')
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
            'image_url' => 'required|string|max:500',
            'link_type' => 'required|string|in:CATEGORY,PRODUCT,MEMBERSHIP,URL',
            'link_value' => 'required|string|max:250',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 1;

        StoreBanner::create($validated);
        return back()->with('success', 'Store banner added.');
    }

    public function updateBanner(Request $request, string $id)
    {
        $banner = StoreBanner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:250',
            'image_url' => 'required|string|max:500',
            'link_type' => 'required|string|in:CATEGORY,PRODUCT,MEMBERSHIP,URL',
            'link_value' => 'required|string|max:250',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $banner->update($validated);

        return back()->with('success', 'Store banner updated.');
    }

    public function toggleBanner(string $id)
    {
        $banner = StoreBanner::findOrFail($id);
        $banner->update([
            'is_active' => ! $banner->is_active,
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

