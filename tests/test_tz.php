<?php

require 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CampaignSchedule;

$schedule = CampaignSchedule::where('id', '4d34650e-d13a-4946-b3cf-8c29f43b9048')->first();
if ($schedule) {
    echo 'Schedule Timezone: '.$schedule->timezone."\n";

    $nextRun = $schedule->next_run_at;
    if ($nextRun) {
        echo 'next_run_at instance timezone: '.$nextRun->timezone->getName()."\n";
        echo 'next_run_at raw: '.$nextRun->toDateTimeString()."\n";
        echo "next_run_at format('Y-m-d H:i:s'): ".$nextRun->format('Y-m-d H:i:s')."\n";

        $campaign = $schedule->campaign;
        if ($campaign) {
            echo 'getDisplayTimezone(): '.$campaign->getDisplayTimezone()."\n";
            echo 'formatted: '.$campaign->formatTimestamp($nextRun)."\n";
        } else {
            echo "Campaign relation is null!\n";
        }
    } else {
        echo "next_run_at is null\n";
    }
} else {
    echo "Schedule 4d34650e-d13a-4946-b3cf-8c29f43b9048 not found.\n";
}
