<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $platforms = \Illuminate\Support\Facades\DB::table('user_push_tokens')
        ->select('platform', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
        ->groupBy('platform')
        ->get();
    print_r($platforms->toArray());
} catch (\Throwable $e) {
    echo 'Error: '.$e->getMessage().PHP_EOL;
}
