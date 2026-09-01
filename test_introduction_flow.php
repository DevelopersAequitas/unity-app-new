<?php

require 'vendor/autoload.php';

use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\IntroductionImageGenerator;
use App\Services\Users\PeerIntroductionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Optional CLI arguments for user IDs or search queries:
// php test_introduction_flow.php [introducer_id_or_name] [introduced_id_or_name]
$arg1 = $argv[1] ?? null;
$arg2 = $argv[2] ?? null;

$introducer = null;
$introduced = null;

if ($arg1) {
    $introducer = User::where(function ($q) use ($arg1) {
        if (Str::isUuid($arg1)) {
            $q->where('id', $arg1);
        }
        $q->orWhere('email', $arg1)
            ->orWhere('display_name', 'ilike', "%{$arg1}%")
            ->orWhere('first_name', 'ilike', "%{$arg1}%");
    })->first();
}

if ($arg2) {
    $introduced = User::where(function ($q) use ($arg2) {
        if (Str::isUuid($arg2)) {
            $q->where('id', $arg2);
        }
        $q->orWhere('email', $arg2)
            ->orWhere('display_name', 'ilike', "%{$arg2}%")
            ->orWhere('first_name', 'ilike', "%{$arg2}%");
    })->first();
}

// Fallback to active users
if (! $introducer) {
    $introducer = User::where('status', 'active')->whereNotNull('company_name')->first()
        ?: User::where('status', 'active')->first();
}

if (! $introduced) {
    $introduced = User::where('status', 'active')
        ->where('id', '!=', $introducer?->id)
        ->whereNotNull('company_name')
        ->first()
        ?: User::where('status', 'active')
            ->where('id', '!=', $introducer?->id)
            ->first();
}

if (! $introducer || ! $introduced) {
    echo "Could not find two active users in the database to run the test.\n";
    exit(1);
}

// Ensure notification preferences are enabled for testing
$pref = NotificationPreference::firstOrNew(['user_id' => $introducer->id]);
$pref->push_enabled = true;
$pref->email_enabled = true;
$pref->save();

$generator = app(IntroductionImageGenerator::class);

echo "==================================================\n";
echo "🚀 Generating Test Introduction Post on Local\n";
echo "==================================================\n";
echo "1. Introducer (Referrer):\n";
echo "   - Name: {$introducer->display_name} ({$introducer->first_name} {$introducer->last_name})\n";
echo '   - Company: '.($generator->resolveCompanyName($introducer) ?: '—')."\n";
echo '   - Category: '.($generator->resolveCategoryName($introducer) ?: '—')."\n";
echo "   - Email: {$introducer->email}\n";
echo "--------------------------------------------------\n";
echo "2. Introduced (New Member):\n";
echo "   - Name: {$introduced->display_name} ({$introduced->first_name} {$introduced->last_name})\n";
echo '   - Company: '.($generator->resolveCompanyName($introduced) ?: '—')."\n";
echo '   - Category: '.($generator->resolveCategoryName($introduced) ?: '—')."\n";
echo "   - Email: {$introduced->email}\n";
echo "--------------------------------------------------\n";

try {
    // Trigger PeerIntroductionService
    $service = app(PeerIntroductionService::class);
    $service->handlePeerIntroduction($introducer, $introduced);

    echo "✅ Creative generated and flow executed successfully!\n\n";

    // Output results
    $latestPost = Post::where('source_id', $introduced->id)->orderBy('created_at', 'desc')->first();
    if ($latestPost) {
        echo "📰 Timeline Post Created:\n";
        echo "   - Post ID: {$latestPost->id}\n";
        echo "   - Title: {$latestPost->title}\n";
        echo "   - Content: {$latestPost->content_text}\n";
        echo '   - Image URL: '.($latestPost->image ?? 'N/A')."\n\n";
    } else {
        echo "❌ Post was not found in database.\n\n";
    }

    $latestNotification = AppNotification::where('user_id', $introducer->id)
        ->where('type', 'member_introduced')
        ->orderBy('created_at', 'desc')
        ->first();

    if ($latestNotification) {
        echo "🔔 Push Notification Dispatched:\n";
        echo "   - Title: {$latestNotification->title}\n";
        echo "   - Body: {$latestNotification->body}\n";
        echo "   - Status: {$latestNotification->status}\n\n";
    }

    echo "🎉 You can now view the post on your local mobile app timeline or admin panel!\n";

} catch (Throwable $e) {
    echo '❌ Error running flow: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
