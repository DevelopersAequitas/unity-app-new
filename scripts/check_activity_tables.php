<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = ['business_deals', 'referrals', 'referral_status', 'p2p_meetings', 'coins_ledger', 'user_coins', 'attendance_records', 'circle_meetings', 'event_registrations', 'events', 'testimonials', 'users'];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "=== {$table} ===\n";
        echo implode(', ', Schema::getColumnListing($table)) . "\n\n";
    } else {
        echo "=== {$table} (NOT FOUND) ===\n\n";
    }
}
