<?php
require 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CampaignSchedule;
use App\Models\AdminCampaign;

// Let's run inside a database transaction to prevent persisting test changes
\Illuminate\Support\Facades\DB::transaction(function () {
    $campaign = AdminCampaign::create([
        'title' => 'Timezone Manual Test',
        'campaign_type' => 'email_only',
        'audience_type' => 'all_members',
        'status' => 'active',
    ]);
    
    $schedule = CampaignSchedule::create([
        'campaign_id' => $campaign->id,
        'schedule_type' => 'recurring',
        'timezone' => 'Asia/Kolkata',
        'start_date' => '2026-06-15',
        'send_time' => '13:10:00',
        'next_run_at' => \Carbon\Carbon::parse('2026-06-15 07:40:00', 'UTC'),
    ]);
    
    // Eager load schedule
    $campaign->load('schedule');
    
    echo "CASE 1: Schedule Timezone is Asia/Kolkata\n";
    echo "  next_run_at raw (UTC): " . $schedule->next_run_at->toDateTimeString() . " (" . $schedule->next_run_at->timezone->getName() . ")\n";
    echo "  Campaign Display Timezone: " . $campaign->getDisplayTimezone() . "\n";
    echo "  Formatted Next Run: " . $campaign->formatTimestamp($schedule->next_run_at) . "\n";
    echo "  (Expected: 15 Jun 2026 13:10)\n\n";
    
    // Update timezone to UTC
    $schedule->timezone = 'UTC';
    $schedule->save();
    $campaign->load('schedule'); // reload relation
    
    echo "CASE 2: Schedule Timezone is UTC\n";
    echo "  next_run_at raw (UTC): " . $schedule->next_run_at->toDateTimeString() . " (" . $schedule->next_run_at->timezone->getName() . ")\n";
    echo "  Campaign Display Timezone: " . $campaign->getDisplayTimezone() . "\n";
    echo "  Formatted Next Run: " . $campaign->formatTimestamp($schedule->next_run_at) . "\n";
    echo "  (Expected: 15 Jun 2026 07:40)\n\n";

    // Trigger transaction rollback to clean up
    throw new \Exception("Rollback transaction successfully");
});
