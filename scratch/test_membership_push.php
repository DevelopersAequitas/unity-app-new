<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Membership\MembershipNotificationService;
use App\Models\Notifications\AppNotification;

$email = 'harsh@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "Error: User {$email} not found in database.\n";
    exit(1);
}

echo "Testing Membership push flow for: {$user->email}\n";

$tokens = UserPushToken::where(UserPushToken::getUserIdColumn(), $user->id)
    ->where('is_active', true)
    ->get();

echo "Active push tokens count for user: " . $tokens->count() . "\n";
foreach ($tokens as $t) {
    echo " - Token: " . substr($t->token, 0, 30) . "...\n";
}

// Clear any existing duplicates to bypass dedupe check
AppNotification::where('user_id', $user->id)
    ->where('type', 'membership_first_purchase')
    ->delete();

echo "Triggering sendFirstPurchase...\n";
$service = app(MembershipNotificationService::class);
$notification = $service->sendFirstPurchase($user, 'test_membership_push');

if ($notification) {
    echo "SUCCESS: AppNotification row created. ID: {$notification->id}\n";
    echo "Notification details: Title: '{$notification->title}', Channel: '{$notification->channel}'\n";
} else {
    echo "FAILED: AppNotification row was not created.\n";
}
