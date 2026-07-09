<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::where('email', 'harsh@gmail.com')->first();
if ($user) {
    echo "User details for harsh@gmail.com:\n";
    echo " - ID: {$user->id}\n";
    echo " - Status: " . ($user->status ?? 'NULL') . "\n";
    echo " - Membership Status: " . ($user->membership_status ?? 'NULL') . "\n";
    echo " - Deleted At: " . ($user->deleted_at ?? 'NULL') . "\n";
} else {
    echo "User harsh@gmail.com not found.\n";
}
