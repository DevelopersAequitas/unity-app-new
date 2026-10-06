<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\Store\StoreCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$raw = DB::table('products')->where('id', 'a05ceb9e-2747-4dba-841d-63da4d725c33')->first();
echo "Raw DB product:\n";
print_r($raw);

$catalogService = app(StoreCatalogService::class);
$product = $catalogService->getProductById('a05ceb9e-2747-4dba-841d-63da4d725c33');
echo "\nCatalogService getProductById result:\n";
var_dump($product !== null);
