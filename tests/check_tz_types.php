<?php
require 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/vendor/autoload.php';
$app = require_once 'c:/Users/HP/Downloads/unity-app 27-5-2026/unity-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = DB::select("
    SELECT table_name, column_name, data_type 
    FROM information_schema.columns 
    WHERE table_name IN ('admin_campaigns', 'campaign_schedules', 'campaign_deliveries', 'campaign_logs')
      AND data_type LIKE '%time%'
");

foreach ($columns as $c) {
    echo "Table: {$c->table_name} | Column: {$c->column_name} | Type: {$c->data_type}\n";
}
