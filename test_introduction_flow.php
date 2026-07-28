<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Post;
use App\Models\Notifications\AppNotification;
use App\Services\Users\PeerIntroductionService;

// 1. Fetch two active users from database or create demo ones
$introducer = User::where('status', 'active')->first();
$introduced = User::where('status', 'active')
    ->where('id', '!=', $introducer?->id)
    ->first();

if (!$introducer || !$introduced) {
    echo "Could not find two active users in the database to run the test.\n";
    exit(1);
}

echo "Testing introduction flow locally:\n";
echo "Introducer (Referrer): {$introducer->display_name} ({$introducer->email})\n";
echo "Introduced (New Member): {$introduced->display_name} ({$introduced->email})\n";
echo "--------------------------------------------------\n";

try {
    // 2. Trigger PeerIntroductionService
    $service = app(PeerIntroductionService::class);
    $service->handlePeerIntroduction($introducer, $introduced);

    echo "Flow triggered successfully!\n\n";

    // 3. Output results
    // Let's find the latest timeline post
    $latestPost = Post::where('source_id', $introduced->id)->orderBy('created_at', 'desc')->first();
    if ($latestPost) {
        echo "✅ Post Created successfully on Timeline:\n";
        echo "   - Post ID: {$latestPost->id}\n";
        echo "   - Title: {$latestPost->title}\n";
        echo "   - Content: {$latestPost->content_text}\n";
        echo "   - Media Attached: " . json_encode($latestPost->media) . "\n\n";
    } else {
        echo "❌ Post was not found in database.\n\n";
    }

    // Let's find the latest push notification
    $latestNotification = AppNotification::where('user_id', $introducer->id)
        ->where('type', 'member_introduced')
        ->orderBy('created_at', 'desc')
        ->first();
        
    if ($latestNotification) {
        echo "✅ Push Notification Registered:\n";
        echo "   - Notification ID: {$latestNotification->id}\n";
        echo "   - Title: {$latestNotification->title}\n";
        echo "   - Body: {$latestNotification->body}\n";
        echo "   - Status: {$latestNotification->status}\n\n";
    } else {
        echo "❌ Notification was not found in database.\n\n";
    }

    echo "Check your timeline and notifications in the mobile app/admin panel!\n";

} catch (\Throwable $e) {
    echo "Error running flow: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
