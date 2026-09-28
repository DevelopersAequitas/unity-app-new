<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (function_exists('ini_get')) {
    $currentMemory = (string) ini_get('memory_limit');
    if ($currentMemory !== '-1') {
        $memoryInBytes = (int) $currentMemory;
        $unit = strtolower(substr(trim($currentMemory), -1));
        if ($unit === 'g') {
            $memoryInBytes *= 1024 * 1024 * 1024;
        } elseif ($unit === 'm') {
            $memoryInBytes *= 1024 * 1024;
        } elseif ($unit === 'k') {
            $memoryInBytes *= 1024;
        }
        if ($memoryInBytes > 0 && $memoryInBytes < 256 * 1024 * 1024) {
            @ini_set('memory_limit', '256M');
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
