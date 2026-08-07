<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCoupon;
use App\Services\Events\EventCouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventCouponWebController extends Controller
{
    public function __construct(
        private readonly EventCouponService $couponService
    ) {}

    public function index(Request $request): View
    {
        $query = EventCoupon::query()->with('event');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        if ($type = $request->input('discount_type')) {
            $query->where('discount_type', $type);
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $coupons = $query->latest()->paginate(15)->withQueryString();
        $events = Event::query()->select(['id', 'title'])->orderBy('title')->get();

        return view('admin.events.coupons', compact('coupons', 'events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:50|unique:event_coupons,code',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:full,percentage,fixed',
            'discount_value' => 'required_unless:discount_type,full|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
            'event_id' => 'nullable|uuid|exists:events,id',
            'generate_code' => 'nullable|boolean',
            'code_prefix' => 'nullable|string|max:10',
        ]);

        if (! empty($data['generate_code']) || empty($data['code'])) {
            $prefix = ! empty($data['code_prefix']) ? $data['code_prefix'] : 'SAVE';
            $data['code'] = $this->couponService->generateRandomCode($prefix);
        }

        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['discount_value'] = ($data['discount_type'] === 'full') ? 100.00 : (float) ($data['discount_value'] ?? 0);
        $data['is_active'] = $request->has('is_active');

        EventCoupon::query()->create($data);

        return redirect()->route('admin.event-coupons.index')->with('success', "Coupon '{$data['code']}' created successfully.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:full,percentage,fixed',
            'discount_value' => 'required_unless:discount_type,full|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
            'event_id' => 'nullable|uuid|exists:events,id',
        ]);

        $data['discount_value'] = ($data['discount_type'] === 'full') ? 100.00 : (float) ($data['discount_value'] ?? 0);
        $data['is_active'] = $request->has('is_active');

        $coupon->update($data);

        return redirect()->route('admin.event-coupons.index')->with('success', "Coupon '{$coupon->code}' updated successfully.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();

        return redirect()->route('admin.event-coupons.index')->with('success', "Coupon '{$code}' deleted successfully.");
    }
}
