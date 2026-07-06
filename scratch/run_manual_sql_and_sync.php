<?php
// Run manual SQL files and run sync
require 'd:/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'd:/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Admin\DedLocationService;

$sqlFiles = [
    'database/manual_sql/2026_06_03_ded_district_assignments.sql',
    'database/manual_sql/2026_06_03_ded_circle_join_approval.sql',
    'database/sql/industry_director_assignments.sql',
    'database/sql/industry_director_user_mapping_view.sql'
];

foreach ($sqlFiles as $file) {
    $path = 'd:/unity-app 27-5-2026/unity-app/' . $file;
    if (file_exists($path)) {
        echo "Running SQL file: $file...\n";
        try {
            $sql = file_get_contents($path);
            DB::unprepared($sql);
            echo "Success!\n";
        } catch (\Exception $e) {
            echo "ERROR in $file: " . $e->getMessage() . "\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}

echo "Syncing known locations...\n";
try {
    $service = app(DedLocationService::class);
    $service->syncKnownLocations();
    echo "Sync complete!\n";
    
    echo "States count: " . DB::table('states')->count() . "\n";
    echo "Districts count: " . DB::table('districts')->count() . "\n";
    
    // Output a few states to verify they show up now
    $states = DB::table('states')->orderBy('name')->limit(10)->get();
    echo "First 10 states:\n";
    foreach ($states as $s) {
        echo " - {$s->name} ({$s->id})\n";
    }
} catch (\Exception $e) {
    echo "ERROR during sync: " . $e->getMessage() . "\n";
}
