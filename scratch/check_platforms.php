<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $platforms = DB::table('user_push_tokens')
        ->select('platform', DB::raw('count(*) as count'))
        ->groupBy('platform')
        ->get();
    print_r($platforms->toArray());
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage().PHP_EOL;
}
