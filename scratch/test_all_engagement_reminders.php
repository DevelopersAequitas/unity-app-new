<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\DailyNotificationReminder;
use App\Models\Notifications\AppNotification;
use App\Jobs\Notifications\SendNotificationChannelJob;

$email = 'harsh@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "Error: User {$email} not found in database.\n";
    exit(1);
}

echo "Loading daily notification templates...\n";
$reminders = DailyNotificationReminder::all();

if ($reminders->isEmpty()) {
    echo "No templates found in daily_notifications_reminder table.\n";
    exit(1);
}

echo "Found " . $reminders->count() . " notification templates.\n";
echo "Sending all notifications to: {$user->email}\n\n";

$placeholders = [
    '{Suggested Peer Name}' => 'Amit Patel',
    '{Industry}' => 'Software Engineering',
    '{Circle Name}' => 'Only Unity Peer Circle',
    '{X}' => '15',
    '{Advertiser Name}' => 'Premium Peers Sponsor',
    '{Category Name}' => 'Global Trade',
    '{Event Name}' => 'Peers Business Expo 2026',
    '{Leader Name}' => 'Rohan Shah',
    '{Insight Snippet}' => 'Collaborative networking increases business growth by up to 40%.',
];

$successCount = 0;

foreach ($reminders as $index => $reminder) {
    $num = $index + 1;
    $title = $reminder->notification_title;
    $body = $reminder->notification_body;

    // Replace placeholders
    foreach ($placeholders as $placeholder => $value) {
        $title = str_replace($placeholder, $value, $title);
        $body = str_replace($placeholder, $value, $body);
    }

    echo "[{$num}/" . $reminders->count() . "] Feature: '{$reminder->feature}'\n";
    echo "  - Title: '{$title}'\n";
    echo "  - Body: '{$body}'\n";

    try {
        // 1. Clear any existing duplicates for this specific activity to avoid deduplication
        AppNotification::where('user_id', $user->id)
            ->where('type', 'system')
            ->where('data->activity', $reminder->activity)
            ->delete();

        // 2. Create the in-app AppNotification row
        $appNotification = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'category' => 'engagement_reminder',
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'channel' => 'push',
            'priority' => 'medium',
            'screen' => 'home',
            'data' => [
                'notification_type' => 'engagement_reminder',
                'title' => $title,
                'body' => $body,
                'feature' => $reminder->feature,
                'activity' => $reminder->activity,
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // 3. Dispatch the FCM push job (runs synchronously since QUEUE_CONNECTION=sync)
        SendNotificationChannelJob::dispatch($appNotification->id, 'push');
        
        echo "  -> Sent successfully! DB ID: {$appNotification->id}\n\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "  -> Failed to send: {$e->getMessage()}\n\n";
    }
}

echo "Done! Sent {$successCount} of " . $reminders->count() . " notifications to your phone.\n";
