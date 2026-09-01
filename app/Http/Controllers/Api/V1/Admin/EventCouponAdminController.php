<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreEventCouponRequest;
use App\Http\Requests\Admin\UpdateEventCouponRequest;
use App\Models\EventCoupon;
use App\Services\Events\EventCouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventCouponAdminController extends BaseApiController
{
    public function __construct(
        private readonly EventCouponService $couponService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = EventCoupon::query()->with(['event:id,title', 'occurrence:id,event_id,start_at']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('discount_type')) {
            $query->where('discount_type', (string) $request->input('discount_type'));
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (string) $request->input('event_id'));
        }

        $coupons = $query->latest('created_at')->paginate((int) $request->input('per_page', 15));

        return $this->success($coupons, 'Event coupons fetched successfully.');
    }

    public function store(StoreEventCouponRequest $request): JsonResponse
    {
        $data = $request->validated();

        $code = ! empty($data['code']) ? Str::upper(trim((string) $data['code'])) : null;
        if (empty($code) || (! empty($data['generate_code']) && filter_var($data['generate_code'], FILTER_VALIDATE_BOOLEAN))) {
            $prefix = ! empty($data['name']) ? Str::upper(Str::slug(substr((string) $data['name'], 0, 4))) : '';
            $code = $this->couponService->generateRandomCode(8, $prefix);
        }

        unset($data['generate_code']);
        $data['code'] = $code;
        $data['discount_value'] = (float) ($data['discount_value'] ?? 0.00);

        if ($data['discount_type'] === 'full') {
            $data['discount_value'] = 100.00;
        }

        $coupon = EventCoupon::query()->create($data);

        return $this->success($coupon->load(['event:id,title', 'occurrence:id,event_id,start_at']), 'Event coupon created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $coupon = EventCoupon::query()
            ->with(['event', 'occurrence', 'registrations' => fn ($q) => $q->with('user:id,first_name,last_name,email,display_name')->latest('created_at')->limit(20)])
            ->findOrFail($id);

        return $this->success($coupon, 'Event coupon fetched successfully.');
    }

    public function update(UpdateEventCouponRequest $request, string $id): JsonResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('code', $data) && ! empty($data['code'])) {
            $data['code'] = Str::upper(trim((string) $data['code']));
        }

        if (array_key_exists('discount_type', $data) && $data['discount_type'] === 'full') {
            $data['discount_value'] = 100.00;
        }

        $coupon->update($data);

        return $this->success($coupon->fresh(['event:id,title', 'occurrence:id,event_id,start_at']), 'Event coupon updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = EventCoupon::query()->findOrFail($id);
        $coupon->delete();

        return $this->success(null, 'Event coupon deleted successfully.');
    }

    public function generateCode(Request $request): JsonResponse
    {
        $prefix = (string) $request->input('prefix', '');
        $code = $this->couponService->generateRandomCode(8, $prefix);

        return $this->success(['code' => $code], 'Random coupon code generated successfully.');
    }
}
