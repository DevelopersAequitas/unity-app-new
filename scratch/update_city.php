<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$city = DB::table('cities')->where('name', 'ILIKE', '%Ahmedabad%')->first();

if (! $city) {
    $city = DB::table('cities')->where('name', 'LIKE', '%Ahmedabad%')->first();
}

if ($city) {
    DB::table('circles')->where('name', 'LIKE', '%Ahmedabad%')->update(['city_id' => $city->id]);
    echo 'Updated city_id for Ahmedabad circles to: '.$city->name.' (ID: '.$city->id.")\n";
} else {
    echo "Ahmedabad city record not found in cities table.\n";
}
