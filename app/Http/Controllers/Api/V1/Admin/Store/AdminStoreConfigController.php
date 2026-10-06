<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminConfigUpdateRequest;
use App\Models\Store\StoreConfig;
use Illuminate\Http\JsonResponse;

class AdminStoreConfigController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $configs = StoreConfig::all();

        return $this->success($configs, 'All store configs retrieved');
    }

    public function show(string $key): JsonResponse
    {
        $config = StoreConfig::where('config_key', $key)->first();
        if (! $config) {
            return $this->error('Config not found', 404);
        }

        return $this->success($config, 'Store config retrieved');
    }

    public function update(AdminConfigUpdateRequest $request, string $key): JsonResponse
    {
        $config = StoreConfig::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $request->input('value'),
                'description' => $request->input('description'),
                'updated_at' => now(),
            ]
        );

        return $this->success($config, 'Store config updated successfully');
    }
}
