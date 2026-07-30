<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Api\V1\AppChangelogController;
use App\Http\Controllers\Api\V1\UserMobileVersionController;
use App\Models\User;
use App\Models\UserMobileVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "1. TESTING GET /api/v1/app/changelogs\n";
echo "==================================================\n";

$changelogController = app(AppChangelogController::class);

// Test listing all changelogs
$requestAll = Request::create('/api/v1/app/changelogs', 'GET');
$responseAll = $changelogController->index($requestAll);

echo "All Changelogs Response:\n";
echo json_encode(json_decode($responseAll->getContent()), JSON_PRETTY_PRINT) . "\n\n";

// Test listing only Android changelogs
$requestAndroid = Request::create('/api/v1/app/changelogs', 'GET', ['platform' => 'android']);
$responseAndroid = $changelogController->index($requestAndroid);

echo "Android Changelogs Response:\n";
echo json_encode(json_decode($responseAndroid->getContent()), JSON_PRETTY_PRINT) . "\n\n";

echo "==================================================\n";
echo "2. TESTING POST /api/v1/user/mobile-version\n";
echo "==================================================\n";

// Find an active user in the database to run the test
$user = User::where('status', 'active')->first();
if (!$user) {
    $user = User::first();
}

if (!$user) {
    echo "No user found to test POST /api/v1/user/mobile-version\n";
    exit(1);
}

echo "Testing version storage as user: {$user->display_name} ({$user->email})\n";

// Create request payload
$payload = [
    'platform' => 'android',
    'app_version' => '1.0.5',
    'device_model' => 'Samsung S24 Ultra',
    'os_version' => 'Android 14'
];

$mobileVersionController = app(UserMobileVersionController::class);

// Make the request and inject the user
$requestPost = Request::create('/api/v1/user/mobile-version', 'POST', $payload);
$requestPost->setUserResolver(fn() => $user);

$responsePost = $mobileVersionController->store($requestPost);

echo "POST Response:\n";
echo json_encode(json_decode($responsePost->getContent()), JSON_PRETTY_PRINT) . "\n\n";

// Verify DB entry
$stored = UserMobileVersion::where('user_id', $user->id)->where('platform', 'android')->first();
if ($stored) {
    echo "✅ Successfully verified database record exists:\n";
    echo "   - ID: {$stored->id}\n";
    echo "   - Platform: {$stored->platform}\n";
    echo "   - App Version: {$stored->app_version}\n";
    echo "   - Device: {$stored->device_model}\n";
    echo "   - OS Version: {$stored->os_version}\n";
} else {
    echo "❌ Database verification failed!\n";
}

echo "\nDone testing new APIs!\n";
