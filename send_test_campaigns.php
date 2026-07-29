<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Notifications\NotificationCampaign;
use App\Services\Notifications\CampaignService;
use Illuminate\Contracts\Console\Kernel;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$email = isset($argv[1]) ? trim($argv[1]) : null;

if (!$email) {
    echo "Usage: php send_test_campaigns.php [user_email]\n";
    exit(1);
}

$user = User::where('email', $email)->first();
if (!$user) {
    echo "User not found with email: {$email}\n";
    exit(1);
}

echo "Found User: {$user->display_name} ({$user->id})\n";

// List of campaign codes to test
$campaignCodes = [
    'requirement_lead',
    'pending_requirement_reminder',
    'new_post_activity_circle',
    'circle_activity',
    'people_to_connect',
    'upcoming_event_reminder',
    'event_starting_now',
    'post_event_feedback',
    'unclaimed_coins'
];

$campaignService = app(CampaignService::class);

foreach ($campaignCodes as $code) {
    $campaign = NotificationCampaign::where('code', $code)->first();
    if (!$campaign) {
        echo "Campaign '{$code}' not found in database, skipping.\n";
        continue;
    }

    echo "Sending campaign '{$code}' to user...\n";
    
    // Resolve dynamic values
    $displayName = trim((string) ($user->display_name ?? '')) ?: trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? ''))) ?: (string) ($user->name ?? 'Peer');
    $personName = $displayName;
    $requirementTitle = 'a relevant requirement';
    $eventTitle = 'Upcoming Event';
    $circleName = 'your Circle';
    $eventDate = now()->format('d M Y');
    $xVal = '1';
    $postPreview = 'Check out the latest updates';

    // Resolve dynamic placeholders based on campaign code
    if ($campaign->code === 'requirement_lead' || $campaign->code === 'pending_requirement_reminder') {
        $latestRequirement = \App\Models\Requirement::where('status', 'active')
            ->where('user_id', '!=', $user->id)
            ->latest()
            ->first();
        if ($latestRequirement) {
            $creator = $latestRequirement->user;
            if ($creator) {
                $personName = trim((string) ($creator->display_name ?? '')) ?: trim(((string) ($creator->first_name ?? '')).' '.((string) ($creator->last_name ?? ''))) ?: (string) ($creator->name ?? 'A member');
            }
            $requirementTitle = $latestRequirement->subject;
        }
        if ($campaign->code === 'pending_requirement_reminder') {
            $pendingCount = \App\Models\Requirement::where('status', 'active')
                ->where('user_id', '!=', $user->id)
                ->count();
            $xVal = (string) ($pendingCount ?: 1);
        }
    } elseif ($campaign->code === 'new_post_activity_circle') {
        $latestPost = \App\Models\Post::where('user_id', '!=', $user->id)
            ->where('visibility', 'public')
            ->where('is_deleted', false)
            ->latest()
            ->first();
        if ($latestPost) {
            $author = $latestPost->user;
            if ($author) {
                $personName = trim((string) ($author->display_name ?? '')) ?: trim(((string) ($author->first_name ?? '')).' '.((string) ($author->last_name ?? ''))) ?: (string) ($author->name ?? 'A member');
            }
            $postPreview = \Illuminate\Support\Str::limit(strip_tags($latestPost->content_text ?? ''), 50) ?: 'published a new post';
        }
    } elseif ($campaign->code === 'circle_activity') {
        $latestCirclePost = \App\Models\Post::whereNotNull('circle_id')
            ->where('user_id', '!=', $user->id)
            ->latest()
            ->first();
        if ($latestCirclePost) {
            $author = $latestCirclePost->user;
            if ($author) {
                $personName = trim((string) ($author->display_name ?? '')) ?: trim(((string) ($author->first_name ?? '')).' '.((string) ($author->last_name ?? ''))) ?: (string) ($author->name ?? 'A member');
            }
            if ($latestCirclePost->circle) {
                $circleName = $latestCirclePost->circle->name;
            }
        }
    } elseif ($campaign->code === 'people_to_connect') {
        $connectionCount = \App\Models\User::where('id', '!=', $user->id)
            ->where('status', 'active')
            ->where('city', $user->city)
            ->count();
        if ($connectionCount === 0) {
            $connectionCount = \App\Models\User::where('id', '!=', $user->id)
                ->where('status', 'active')
                ->count();
        }
        $xVal = (string) min(10, max(3, $connectionCount));
    } elseif (in_array($campaign->code, ['upcoming_event_reminder', 'event_starting_now', 'post_event_feedback'], true)) {
        $latestEvent = \App\Models\Event::where('start_at', '>=', now())
            ->orderBy('start_at', 'asc')
            ->first();
        if (! $latestEvent) {
            $latestEvent = \App\Models\Event::latest()->first();
        }
        if ($latestEvent) {
            $eventTitle = $latestEvent->title;
            $eventDate = $latestEvent->start_at->format('d M Y');
        }
    } elseif ($campaign->code === 'unclaimed_coins') {
        $xVal = (string) max(10, (int) ($user->coin_balance ?? 0));
    }

    $placeholders = [
        'name' => $displayName,
        'person' => $personName,
        'requirement_title' => $requirementTitle,
        'event_title' => $eventTitle,
        'circle_name' => $circleName,
        'date' => $eventDate,
        'x' => $xVal,
        'post_preview_content' => $postPreview,
        'amount' => '₹0',
        'status' => 'Active',
        'badge_name' => 'Member',
    ];

    $title = app(\App\Services\Notifications\NotificationService::class)->renderTemplate($campaign->title_template, $placeholders);
    $body = app(\App\Services\Notifications\NotificationService::class)->renderTemplate($campaign->body_template, $placeholders);

    echo "  -> Title: {$title}\n";
    echo "  -> Body: {$body}\n";

    // Send it directly to the user
    app(\App\Services\Notifications\NotificationService::class)->sendToUser(
        $user,
        $campaign->code,
        $title,
        $body,
        ['screen' => $campaign->tap_screen, 'campaign_id' => $campaign->id],
        [
            'campaign' => $campaign,
            'channel' => 'push',
            'priority' => $campaign->priority,
            'screen' => $campaign->tap_screen
        ]
    );
}

echo "\nAll campaigns sent to your user! Check your mobile app notifications.\n";
