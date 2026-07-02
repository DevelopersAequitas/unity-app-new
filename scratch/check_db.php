<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

try {
    $result = DB::select('SELECT enum_range(NULL::user_status_enum)');
    print_r($result);
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
