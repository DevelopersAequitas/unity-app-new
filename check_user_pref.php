<?php

use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'deised.ricardo@gmai.com')->first();
if (! $user) {
    exit("User not found\n");
}

$pref = NotificationPreference::where('user_id', $user->id)->first();
echo 'Preferences: '.($pref ? json_encode($pref->toArray()) : 'NULL')."\n";

$count = AppNotification::where('user_id', $user->id)->whereDate('created_at', today())->count();
echo "Notifications today: $count\n";
