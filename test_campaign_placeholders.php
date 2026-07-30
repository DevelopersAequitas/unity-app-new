<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationCampaign;
use App\Models\Requirement;
use App\Models\User;
use App\Services\Notifications\CampaignService;
use Illuminate\Contracts\Console\Kernel;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// 1. Find or create dummy users
$author = User::where('status', 'active')->first() ?? User::factory()->create(['status' => 'active', 'display_name' => 'John Author']);
$recipient = User::where('status', 'active')->where('id', '!=', $author->id)->first() ?? User::factory()->create(['status' => 'active', 'display_name' => 'Jane Recipient']);

echo "Author: {$author->display_name} ({$author->id})\n";
echo "Recipient: {$recipient->display_name} ({$recipient->id})\n";

// 2. Create a requirement by author
$requirement = Requirement::create([
    'user_id' => $author->id,
    'subject' => 'Need Laravel developer for a cool startup project',
    'description' => 'Looking for a senior developer with 5+ years of experience.',
    'status' => 'active',
]);
echo "Created Requirement: '{$requirement->subject}'\n";

// 3. Create a notification campaign in DB for requirement_lead
$campaign = NotificationCampaign::updateOrCreate(
    ['code' => 'requirement_lead'],
    [
        'name' => 'New requirement / lead available',
        'category' => 'New requirement / lead available',
        'channel' => 'push',
        'trigger_type' => 'requirement_match',
        'frequency' => 'daily',
        'audience_type' => 'matching_requirements',
        'title_template' => 'Potential Business Match Found!',
        'body_template' => '<person> is looking for: "[Requirement Title]"',
        'tap_screen' => 'requirement_details',
        'is_active' => true,
    ]
);

// 4. Force run the campaign
echo "Running Campaign...\n";
$service = app(CampaignService::class);
$run = $service->runCampaign($campaign);

echo "Campaign Run status: {$run->status}, Sent: {$run->sent_count}\n";

// 5. Query the last created notifications in app_notifications
$notifications = AppNotification::where('type', 'requirement_lead')->latest()->limit(5)->get();
foreach ($notifications as $n) {
    echo "\n--- Notification #{$n->id} ---\n";
    echo 'To User: '.User::find($n->user_id)?->display_name."\n";
    echo "Title: {$n->title}\n";
    echo "Body: {$n->body}\n";
}

// Clean up
$requirement->forceDelete();
echo "\nDone!\n";
