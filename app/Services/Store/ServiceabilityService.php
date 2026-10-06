<?php

namespace App\Services\Store;

use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use Illuminate\Database\Eloquent\Collection;

class ServiceabilityService
{
    public function checkPincode(string $pincode): array
    {
        $record = ServiceablePincode::where('pincode', $pincode)->active()->first();
        // Since pickup_points table stores address text, search for pincode within address text
        $hasPickup = PickupPoint::active()
            ->where('address', 'ILIKE', "%{$pincode}%")
            ->exists();

        if ($record) {
            return [
                'serviceable' => true,
                'pincode' => $pincode,
                'city' => $record->city,
                'state' => $record->state,
                'delivery_available' => (bool) $record->serviceable,
                'pickup_available' => $hasPickup,
                'delivery_days' => 5,
            ];
        }

        return [
            'serviceable' => $hasPickup,
            'pincode' => $pincode,
            'city' => null,
            'state' => null,
            'delivery_available' => false,
            'pickup_available' => $hasPickup,
            'delivery_days' => null,
        ];
    }

    public function getPickupPoints(?string $pincode = null): Collection
    {
        $query = PickupPoint::active();
        if ($pincode) {
            $query->where('address', 'ILIKE', "%{$pincode}%");
        }

        return $query->get();
    }

    public function getPickupPointById(string $id): ?PickupPoint
    {
        return PickupPoint::active()->find($id);
    }
}
