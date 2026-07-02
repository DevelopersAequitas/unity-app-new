<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CircleMember;

$members = CircleMember::withTrashed()->orderByDesc('updated_at')->limit(10)->get();
echo "10 most recently updated circle members:\n";
foreach ($members as $m) {
    echo "ID: {$m->id} | User ID: {$m->user_id} | Circle ID: {$m->circle_id} | Role: {$m->role} | Status: {$m->status} | Left At: {$m->left_at} | Deleted At: {$m->deleted_at} | Updated At: {$m->updated_at}\n";
}
