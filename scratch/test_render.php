<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Find an admin user
$adminUser = AdminUser::first();
if (! $adminUser) {
    echo "No admin user found!\n";
    exit;
}

echo "Authenticating as admin: {$adminUser->email}\n";
// Log in the admin
Auth::guard('admin')->login($adminUser);

// Create request
$request = Request::create('/admin/circles/create', 'GET');
// Run the request through the router
try {
    $response = app()->handle($request);
    $html = $response->getContent();

    // Let's find the select name="city_id" block
    if (preg_match('/<select[^>]*name="city_id"[^>]*>(.*?)<\/select>/is', $html, $matches)) {
        echo "Found city_id select block:\n";
        echo $matches[0]."\n";
    } else {
        echo "city_id select block NOT found in HTML!\n";
        echo 'HTML length: '.strlen($html)."\n";
        if (strlen($html) < 2000) {
            echo "HTML content:\n".$html."\n";
        }
    }
} catch (\Exception $e) {
    echo 'Error rendering page: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
