<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\Store\StoreCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

DB::table('products')->update(['status' => 'ACTIVE']);
echo "Updated all products to ACTIVE\n";

$catalogService = app(StoreCatalogService::class);
$product = $catalogService->getProductById('a05ceb9e-2747-4dba-841d-63da4d725c33');
if ($product) {
    echo "SUCCESS: Product 'a05ceb9e-2747-4dba-841d-63da4d725c33' found! Name: ".$product->name.' | Status: '.$product->status."\n";
} else {
    echo "FAILED: Product not found\n";
}
