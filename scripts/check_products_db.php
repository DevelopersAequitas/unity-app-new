<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$products = DB::table('products')->get(['id', 'name', 'status', 'is_featured']);
foreach ($products as $p) {
    echo "ID: " . $p->id . " | Name: " . $p->name . " | Status: " . $p->status . "\n";
}

