<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventCoupon;
use App\Models\EventOccurrence;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventCouponService
{
    public function validateCoupon(string $code, Event $event, ?EventOccurrence $occurrence = null): EventCoupon
    {
        $normalizedCode = Str::upper(trim($code));

        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'coupon_code' => 'Invalid or expired coupon code',
            ]);
        }

        $coupon = EventCoupon::query()
            ->where('code', $normalizedCode)
            ->where('is_active', true)
            ->first();

        if (! $coupon || ! $coupon->isValidForEvent($event, $occurrence)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Invalid or expired coupon code',
            ]);
        }

        return $coupon;
    }

    public function calculateDiscount(EventCoupon $coupon, float $originalPrice): array
    {
        $originalPrice = round(max(0.0, $originalPrice), 2);

        if ($coupon->discount_type === 'full') {
            $discountAmount = $originalPrice;
            $finalPrice = 0.0;
        } elseif ($coupon->discount_type === 'percentage') {
            $percentage = min(100.0, max(0.0, (float) $coupon->discount_value));
            $discountAmount = round($originalPrice * ($percentage / 100.0), 2);
            $finalPrice = round(max(0.0, $originalPrice - $discountAmount), 2);
        } else {
            // fixed money discount
            $discountValue = round(max(0.0, (float) $coupon->discount_value), 2);
            $discountAmount = min($originalPrice, $discountValue);
            $finalPrice = round(max(0.0, $originalPrice - $discountAmount), 2);
        }

        return [
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
        ];
    }

    public function applyCoupon(EventCoupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    public function generateRandomCode(int $length = 8, string $prefix = ''): string
    {
        $prefix = Str::upper(trim($prefix));

        do {
            $randomPart = Str::upper(Str::random($length));
            $code = $prefix !== '' ? $prefix.'-'.$randomPart : $randomPart;
        } while (EventCoupon::query()->where('code', $code)->exists());

        return $code;
    }
}
