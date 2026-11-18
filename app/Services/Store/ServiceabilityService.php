<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Models\Store\PickupPoint;
use App\Models\Store\ServiceablePincode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class ServiceabilityService
{
    public function checkPincode(string $pincode): array
    {
        $record = null;
        try {
            if (Schema::hasTable('serviceable_pincodes')) {
                $record = ServiceablePincode::where('pincode', $pincode)->active()->first();
            }
        } catch (\Throwable) {
            $record = null;
        }

        $hasPickup = false;
        try {
            if (Schema::hasTable('pickup_points')) {
                $hasAddress = Schema::hasColumn('pickup_points', 'address');
                $hasPincode = Schema::hasColumn('pickup_points', 'pincode');
                $hasLine1 = Schema::hasColumn('pickup_points', 'address_line1');

                $hasPickup = PickupPoint::active()
                    ->where(function ($q) use ($pincode, $hasAddress, $hasPincode, $hasLine1): void {
                        $matched = false;
                        if ($hasPincode) {
                            $q->where('pincode', $pincode);
                            $matched = true;
                        }
                        if ($hasAddress) {
                            if ($matched) {
                                $q->orWhere('address', 'ILIKE', "%{$pincode}%");
                            } else {
                                $q->where('address', 'ILIKE', "%{$pincode}%");
                                $matched = true;
                            }
                        }
                        if ($hasLine1) {
                            if ($matched) {
                                $q->orWhere('address_line1', 'ILIKE', "%{$pincode}%");
                            } else {
                                $q->where('address_line1', 'ILIKE', "%{$pincode}%");
                                $matched = true;
                            }
                        }
                        if (! $matched) {
                            $q->whereRaw('1 = 0');
                        }
                    })
                    ->exists();
            }
        } catch (\Throwable) {
            $hasPickup = false;
        }

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
        try {
            if (! Schema::hasTable('pickup_points')) {
                return new Collection;
            }

            $query = PickupPoint::active();
            if ($pincode) {
                $hasAddress = Schema::hasColumn('pickup_points', 'address');
                $hasPincode = Schema::hasColumn('pickup_points', 'pincode');
                $hasLine1 = Schema::hasColumn('pickup_points', 'address_line1');

                $query->where(function ($q) use ($pincode, $hasAddress, $hasPincode, $hasLine1): void {
                    $matched = false;
                    if ($hasPincode) {
                        $q->where('pincode', $pincode);
                        $matched = true;
                    }
                    if ($hasAddress) {
                        if ($matched) {
                            $q->orWhere('address', 'ILIKE', "%{$pincode}%");
                        } else {
                            $q->where('address', 'ILIKE', "%{$pincode}%");
                            $matched = true;
                        }
                    }
                    if ($hasLine1) {
                        if ($matched) {
                            $q->orWhere('address_line1', 'ILIKE', "%{$pincode}%");
                        } else {
                            $q->where('address_line1', 'ILIKE', "%{$pincode}%");
                            $matched = true;
                        }
                    }
                });
            }

            return $query->get();
        } catch (\Throwable) {
            return new Collection;
        }
    }

    public function getPickupPointById(string $id): ?PickupPoint
    {
        try {
            if (! Schema::hasTable('pickup_points')) {
                return null;
            }

            return PickupPoint::active()->find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
