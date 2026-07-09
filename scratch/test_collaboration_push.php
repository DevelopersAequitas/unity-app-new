<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\CollaborationPost;
use App\Services\CollaborationNotificationService;
use App\Models\Notification;

$email = 'harsh@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "Error: User {$email} not found in database.\n";
    exit(1);
}

echo "Testing Collaboration push flow for: {$user->email}\n";

// Find any existing collaboration post, or create a mock one for testing
$collaboration = CollaborationPost::first();

if (!$collaboration) {
    echo "No collaboration posts found in the database. Creating a temporary mock one...\n";
    // Check if we can create one
    $type = \DB::table('collaboration_types')->first();
    $collaboration = CollaborationPost::create([
        'user_id' => $user->id,
        'title' => 'Mock Collaboration Title',
        'content_text' => 'Mock collaboration content text description.',
        'collaboration_type_id' => $type?->id ?? (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
    ]);
}

echo "Using Collaboration Post ID: {$collaboration->id}, Title: '{$collaboration->title}'\n";

// Clear any existing notifications to bypass deduplication checks
Notification::where('user_id', $user->id)
    ->where('source_type', 'collaboration_post')
    ->where('source_id', $collaboration->id)
    ->delete();

echo "Triggering sendCreatedNotificationsAndEmail...\n";
$service = app(CollaborationNotificationService::class);
$service->sendCreatedNotificationsAndEmail($collaboration);

// Check if the notification row was created in the legacy notifications table
$notification = Notification::where('user_id', $user->id)
    ->where('source_type', 'collaboration_post')
    ->where('source_id', $collaboration->id)
    ->first();

if ($notification) {
    echo "SUCCESS: Legacy Notification row created in DB. ID: {$notification->id}\n";
    echo "Notification details: Title: '{$notification->title}', Message: '{$notification->message}'\n";
} else {
    echo "FAILED: Legacy Notification row was not created in the database.\n";
}
