<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCoupon;
use App\Services\Events\EventCouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventCouponWebController extends Controller
{
    public function __construct(
        private readonly EventCouponService $couponService
    ) {}

    public function index(Request $request): View
    {
        $query = EventCoupon::query()->with(['event:id,title']);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        if ($type = $request->input('discount_type')) {
            $query->where('discount_type', $type);
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($eventId = $request->input('event_id')) {
            $query->where('event_id', $eventId);
        }

        $coupons = $query->latest('created_at')->paginate(15)->withQueryString();
        $events = Event::query()->select(['id', 'title'])->orderBy('title')->get();

        $stats = [
            'total' => EventCoupon::query()->count(),
            'active' => EventCoupon::query()->where('is_active', true)->count(),
            'redemptions' => (int) EventCoupon::query()->sum('used_count'),
        ];

        return view('admin.events.coupons', compact('coupons', 'events', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:event_coupons,code'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', 'in:full,percentage,fixed'],
            'discount_value' => ['required_unless:discount_type,full', 'nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
            'generate_code' => ['nullable', 'boolean'],
            'code_prefix' => ['nullable', 'string', 'max:10'],
        ]);

        $generateCode = filter_var($request->input('generate_code', false), FILTER_VALIDATE_BOOLEAN);
        $code = ! empty($data['code']) ? trim((string) $data['code']) : null;

        if ($generateCode || empty($code)) {
            $prefix = ! empty($data['code_prefix'])
                ? (string) $data['code_prefix']
                : (! empty($data['name']) ? substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $data['name']) ?: 'SAVE', 0, 4) : 'SAVE');
            $data['code'] = $this->couponService->generateRandomCode(8, $prefix);
        } else {
            $data['code'] = strtoupper($code);
        }

        $data['discount_value'] = ($data['discount_type'] === 'full')
            ? 100.00
            : (float) ($data['discount_value'] ?? 0);

        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
            $data['discount_value'] = 100.00;
        }

        $data['is_active'] = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : false;

        if (empty($data['event_id'])) {
            $data['event_id'] = null;
        }

        unset($data['generate_code'], $data['code_prefix']);

        $coupon = EventCoupon::query()->create($data);

        return redirect()->route('admin.event-coupons.index')
            ->with('success', "Coupon code '{$coupon->code}' created successfully.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('event_coupons', 'code')->ignore($coupon->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', 'in:full,percentage,fixed'],
            'discount_value' => ['required_unless:discount_type,full', 'nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
        ]);

        if (! empty($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        } else {
            unset($data['code']);
        }

        $data['discount_value'] = ($data['discount_type'] === 'full')
            ? 100.00
            : (float) ($data['discount_value'] ?? 0);

        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
            $data['discount_value'] = 100.00;
        }

        $data['is_active'] = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : false;

        if (empty($data['event_id'])) {
            $data['event_id'] = null;
        }

        $coupon->update($data);

        return redirect()->route('admin.event-coupons.index')
            ->with('success', "Coupon '{$coupon->code}' updated successfully.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();

        return redirect()->route('admin.event-coupons.index')
            ->with('success', "Coupon '{$code}' deleted successfully.");
    }
}
