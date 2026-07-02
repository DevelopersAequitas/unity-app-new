<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$users = User::withTrashed()->whereNotNull('deleted_at')->orderByDesc('deleted_at')->limit(5)->get();
echo "5 most recently deleted users:\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->display_name} | Email: {$u->email} | Deleted At: {$u->deleted_at}\n";
}
