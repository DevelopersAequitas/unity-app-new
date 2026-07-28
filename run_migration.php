<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    $sql = file_get_contents('database/manual_sql/anniversary_creative.sql');
    DB::unprepared($sql);
    echo "SQL script executed successfully!\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
