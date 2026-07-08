<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$countries = DB::table('cities')
    ->select('country', DB::raw('count(*) as count'))
    ->groupBy('country')
    ->get();

echo "Country counts in cities table:\n";
foreach ($countries as $c) {
    echo "- '".($c->country ?? 'NULL')."': ".$c->count."\n";
}
