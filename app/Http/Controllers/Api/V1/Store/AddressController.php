<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreCreateAddressRequest;
use App\Http\Requests\Store\StoreUpdateAddressRequest;
use App\Services\Store\StoreAddressService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends BaseApiController
{
    protected StoreAddressService $addressService;

    public function __construct(StoreAddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = $this->addressService->getUserAddresses($user);

        return $this->success($addresses, 'User addresses retrieved');
    }

    public function store(StoreCreateAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $address = $this->addressService->createAddress($user, $request->validated());

        return $this->success($address, 'Address created successfully', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $address = $this->addressService->getAddressById($user, $id);

            return $this->success($address, 'Address details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function update(StoreUpdateAddressRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $address = $this->addressService->updateAddress($user, $id, $request->validated());

            return $this->success($address, 'Address updated successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->addressService->deleteAddress($user, $id);

            return $this->success(['deleted' => true], 'Address removed successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
