<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$circles = DB::table('circles')
    ->select('id', 'name', 'circle_founder_user_id', 'circle_director_user_id', 'ded_user_id')
    ->get();

echo "CIRCLES COLUMNS:\n";
foreach ($circles as $c) {
    echo "{$c->name} -> Founder: {$c->circle_founder_user_id}, Director: {$c->circle_director_user_id}, DED: {$c->ded_user_id}\n";
}
