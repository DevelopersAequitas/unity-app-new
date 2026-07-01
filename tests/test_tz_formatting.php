<?php

require 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CampaignDelivery;
use App\Models\CampaignSchedule;

$schedule = CampaignSchedule::where('id', '4d34650e-d13a-4946-b3cf-8c29f43b9048')->first();
if ($schedule) {
    $campaign = $schedule->campaign;
    echo 'Campaign Title: '.$campaign->title."\n";
    echo 'Campaign Status: '.$campaign->status."\n";
    echo 'Schedule Timezone: '.$schedule->timezone."\n";
    echo 'Campaign Display Timezone: '.$campaign->getDisplayTimezone()."\n";

    // Add a delivery
    $delivery = CampaignDelivery::where('campaign_id', $campaign->id)->first();
    if ($delivery) {
        echo 'Delivery scheduled_at (UTC): '.$delivery->scheduled_at."\n";
        echo 'Delivery formatTimestamp(scheduled_at): '.$delivery->formatTimestamp($delivery->scheduled_at)."\n";
        echo 'Campaign formatTimestamp(delivery->scheduled_at): '.$campaign->formatTimestamp($delivery->scheduled_at)."\n";
    } else {
        echo "No deliveries found for this campaign.\n";
    }
}
