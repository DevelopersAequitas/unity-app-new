<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'harsh@gmail.com';
$token = $argv[1] ?? null;

if (!$token) {
    echo "Usage: php scratch/register_and_test.php <YOUR_PHONES_FCM_TOKEN>\n";
    exit(1);
}

$user = \App\Models\User::where('email', $email)->first();
if (!$user) {
    echo "Error: User {$email} not found.\n";
    exit(1);
}

echo "Registering token for {$email}...\n";
\App\Models\UserPushToken::registerTokenForUser($user, [
    'token' => $token,
    'platform' => 'android', // Change to 'ios' if you are testing on an iPhone
]);

echo "Token registered successfully!\n";
echo "Sending test push notification to {$email}...\n\n";

$exitCode = \Illuminate\Support\Facades\Artisan::call('notification:send-test', [
    'user' => $email,
    '--title' => 'Test Notification for Harsh',
    '--body' => 'Success! Push notification sent from local backend.',
]);

echo \Illuminate\Support\Facades\Artisan::output();
