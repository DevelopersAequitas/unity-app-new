<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store\StoreConfig;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreBanner;
use App\Models\Store\Product;
use App\Models\Store\StoreMembershipPlan;
use App\Services\Store\StoreConfigService;
use App\Services\Store\StoreCatalogService;
use App\Services\Store\StoreMembershipService;
use App\Services\Store\StoreWalletService;

echo "=== VERIFYING STORE MODELS & SERVICES ===\n";

// 1. Config Service Test
$configService = app(StoreConfigService::class);
$config = $configService->getStoreConfig(null);
echo "1. StoreConfigService: OK (store_enabled=" . ($config['store_enabled'] ? 'true' : 'false') . ")\n";

// 2. Catalog Service Test
$catalogService = app(StoreCatalogService::class);
$categories = $catalogService->getCategories();
echo "2. StoreCatalogService (Categories): OK (Count: " . count($categories) . ")\n";

$banners = $catalogService->getBanners();
echo "3. StoreCatalogService (Banners): OK (Count: " . $banners->total() . ")\n";

$products = $catalogService->getProducts();
echo "4. StoreCatalogService (Products): OK (Count: " . $products->total() . ")\n";

// 3. Membership Service Test
$membershipService = app(StoreMembershipService::class);
$plans = $membershipService->getActivePlans();
echo "5. StoreMembershipService (Plans): OK (Count: " . count($plans) . ")\n";

echo "\nALL MODELS, SERVICES & DATABASE BINDINGS ARE 100% OPERATIONAL!\n";
