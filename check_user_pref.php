<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'deised.ricardo@gmai.com')->first();
if (!$user) {
    die("User not found\n");
}

$pref = App\Models\Notifications\NotificationPreference::where('user_id', $user->id)->first();
echo "Preferences: " . ($pref ? json_encode($pref->toArray()) : "NULL") . "\n";

$count = App\Models\Notifications\AppNotification::where('user_id', $user->id)->whereDate('created_at', today())->count();
echo "Notifications today: $count\n";
