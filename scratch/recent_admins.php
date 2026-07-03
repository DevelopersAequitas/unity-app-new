<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\AdminUser;
use Illuminate\Contracts\Console\Kernel;

$admins = AdminUser::all();
echo "Admins:\n";
foreach ($admins as $admin) {
    echo "- ID: {$admin->id} | Name: {$admin->name} | Email: {$admin->email}\n";
}
