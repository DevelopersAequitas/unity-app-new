<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreCatalogService;
use App\Services\Store\StoreConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreConfigController extends BaseApiController
{
    protected StoreConfigService $configService;
    protected StoreCatalogService $catalogService;

    public function __construct(StoreConfigService $configService, StoreCatalogService $catalogService)
    {
        $this->configService = $configService;
        $this->catalogService = $catalogService;
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $config = $this->configService->getStoreConfig($user);
        $banners = $this->catalogService->getBanners(10);

        $config['banners'] = $banners->items();

        return $this->success($config, 'Store configuration retrieved');
    }

    public function banners(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $banners = $this->catalogService->getBanners($perPage);

        return $this->success($banners, 'Active banners retrieved');
    }
}
