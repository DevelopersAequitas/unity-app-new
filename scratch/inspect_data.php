<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$tables = ['users', 'introduction_creatives', 'introduced_peers', 'notification_delivery_logs', 'posts'];
foreach ($tables as $table) {
    if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
        $count = DB::table($table)->whereRaw("CAST(id AS TEXT) LIKE '%75fa33c4%'")->count();
        echo "Table $table matching id 75fa33c4: $count\n";
    }
}

$peers = DB::table('users')->where('first_name', 'ilike', '%Romesh%')
    ->orWhere('last_name', 'ilike', '%Vyas%')
    ->orWhere('first_name', 'ilike', '%Natasha%')
    ->orWhere('first_name', 'ilike', '%Samir%')
    ->get();
echo "Found matching name peers: " . $peers->count() . "\n";
foreach ($peers as $p) {
    echo "Peer: {$p->id} - {$p->first_name} {$p->last_name} - introduced_by: {$p->introduced_by}\n";
}
