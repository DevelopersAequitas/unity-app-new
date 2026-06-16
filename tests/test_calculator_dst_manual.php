<?php
require 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CampaignSchedule;
use App\Services\AdminCampaigns\CampaignScheduleCalculator;
use Carbon\Carbon;

$calculator = new CampaignScheduleCalculator();

$schedule = new CampaignSchedule([
    'schedule_type' => 'recurring',
    'recurrence_type' => 'daily',
    'frequency_interval' => 1,
    'start_date' => '2026-03-07',
    'send_time' => '13:10:00',
    'timezone' => 'America/New_York',
]);

// March 8, 2026 was the Spring Forward DST transition in NY.
// Day length was 23 hours.
// Evaluate next runs sequentially:
$current = Carbon::parse('2026-03-07 13:10:00', 'America/New_York')->setTimezone('UTC');

echo "DST RECURRENCE EVALUATION TRACE:\n";
for ($i = 0; $i < 4; $i++) {
    // Calculate next run starting from current time
    $next = $calculator->calculateNextRunAt($schedule, $current);
    
    echo "  Calculation from: " . $current->copy()->setTimezone('America/New_York')->toDateTimeString() . " EST/EDT (" . $current->toDateTimeString() . " UTC)\n";
    if ($next) {
        echo "  Next Run (UTC):   " . $next->toDateTimeString() . "\n";
        echo "  Next Run (Local): " . $next->copy()->setTimezone('America/New_York')->toDateTimeString() . " EST/EDT\n";
        
        // Advance current evaluation time for the next iteration (simulate job running at the scheduled time)
        $current = $next->copy();
        $schedule->last_run_at = $next;
    } else {
        echo "  Next Run is NULL!\n";
        break;
    }
    echo "  ---\n";
}
