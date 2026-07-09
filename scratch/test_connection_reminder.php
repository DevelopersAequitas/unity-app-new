<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Connection;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;

$email = 'harsh@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "Error: User {$email} not found.\n";
    exit(1);
}

echo "Testing Pending Connection Request Reminders for: {$user->email} (ID: {$user->id})\n";

// 1. Clear recent pending connection reminders to bypass the 24h check
Notification::where('user_id', $user->id)
    ->where('type', 'activity_update')
    ->where('payload->notification_type', 'connection_request_pending_reminder')
    ->delete();

// 2. Ensure at least one pending connection request exists for this user as addressee
$sender = User::where('id', '!=', $user->id)->first();
if (!$sender) {
    echo "Error: Need at least one other user in the database to act as the connection sender.\n";
    exit(1);
}

$connection = Connection::where('addressee_id', $user->id)
    ->where('is_approved', false)
    ->first();

if (!$connection) {
    echo "No pending connection request found. Creating a mock connection from ID: {$sender->id} to harsh...\n";
    $connection = Connection::create([
        'requester_id' => $sender->id,
        'addressee_id' => $user->id,
        'is_approved' => false,
        'created_at' => now()->subDays(2),
    ]);
} else {
    echo "Using existing pending connection request from ID: {$connection->requester_id}.\n";
}

echo "Triggering connections:send-pending-reminders...\n";
Artisan::call('connections:send-pending-reminders');
echo Artisan::output();

// 3. Check if the database notification was created
$notification = Notification::where('user_id', $user->id)
    ->where('type', 'activity_update')
    ->where('payload->notification_type', 'connection_request_pending_reminder')
    ->first();

if ($notification) {
    echo "SUCCESS: Connection reminder notification created in DB! ID: {$notification->id}\n";
    echo "Payload: " . json_encode($notification->payload, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "FAILED: Notification row was not created in the database.\n";
}
