<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Address;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class StoreAddressService
{
    public function getUserAddresses(User $user): Collection
    {
        return Address::query()
            ->where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createAddress(User $user, array $data): Address
    {
        if (! empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        } else {
            $hasAny = Address::where('user_id', $user->id)->exists();
            if (! $hasAny) {
                $data['is_default'] = true;
            }
        }

        // Map request fields to PostgreSQL table columns
        $payload = [
            'user_id' => $user->id,
            'name' => $data['name'] ?? ($data['full_name'] ?? ''),
            'phone' => $data['phone'] ?? '',
            'line1' => $data['line1'] ?? '',
            'line2' => $data['line2'] ?? null,
            'landmark' => $data['landmark'] ?? null,
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'pincode' => $data['pincode'] ?? '',
            'country' => $data['country'] ?? 'India',
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];

        return Address::create($payload);
    }

    public function getAddressById(User $user, string $id): Address
    {
        $address = Address::where('id', $id)->where('user_id', $user->id)->first()
            ?? Address::where('id', $id)->first();

        if (! $address) {
            throw new Exception(StoreErrorCodes::ADDRESS_NOT_FOUND, 404);
        }

        return $address;
    }

    public function updateAddress(User $user, string $id, array $data): Address
    {
        $address = $this->getAddressById($user, $id);

        if (! empty($data['is_default'])) {
            Address::where('user_id', $user->id)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $payload = [];
        if (isset($data['name']) || isset($data['full_name'])) {
            $payload['name'] = $data['name'] ?? $data['full_name'];
        }
        if (isset($data['phone'])) {
            $payload['phone'] = $data['phone'];
        }
        if (isset($data['line1'])) {
            $payload['line1'] = $data['line1'];
        }
        if (array_key_exists('line2', $data)) {
            $payload['line2'] = $data['line2'];
        }
        if (array_key_exists('landmark', $data)) {
            $payload['landmark'] = $data['landmark'];
        }
        if (isset($data['city'])) {
            $payload['city'] = $data['city'];
        }
        if (isset($data['state'])) {
            $payload['state'] = $data['state'];
        }
        if (isset($data['pincode'])) {
            $payload['pincode'] = $data['pincode'];
        }
        if (isset($data['country'])) {
            $payload['country'] = $data['country'];
        }
        if (isset($data['is_default'])) {
            $payload['is_default'] = (bool) $data['is_default'];
        }

        $address->update($payload);

        return $address;
    }

    public function deleteAddress(User $user, string $id): bool
    {
        $address = $this->getAddressById($user, $id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = Address::where('user_id', $user->id)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return true;
    }
}
