<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$migrator = app('migrator');
$paths = [database_path('migrations')];
$files = $migrator->getMigrationFiles($paths);
$ran = $migrator->getRepository()->getRan();
$pending = array_diff(array_keys($files), $ran);

echo 'Pending migrations count: '.count($pending)."\n";

foreach ($pending as $migration) {
    if (str_contains($migration, 'add_missing_values_to_notification_type_enum')) {
        echo "Keeping our own migration pending: $migration\n";

        continue;
    }

    echo "Registering pending migration as ran: $migration\n";
    DB::table('migrations')->insert([
        'migration' => $migration,
        'batch' => 999,
    ]);
}

echo "Done!\n";
