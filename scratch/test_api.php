<?php

use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventQrService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::query()->first();
if (! $user) {
    echo "No user found in database.\n";
    exit;
}

$registration = EventRegistration::query()->with('occurrence')->where('user_id', $user->id)->first();
if (! $registration) {
    // Let's find any registration and temporarily assign it
    $registration = EventRegistration::query()->with('occurrence')->first();
    if ($registration) {
        $registration->user_id = $user->id;
        $registration->save();
    }
}

if ($registration) {
    echo 'Registration ID: '.$registration->id."\n";
    echo 'Dynamic QR Status: '.$registration->qr_status."\n";

    // Test API response structure
    $qr = app(EventQrService::class);
    $response = [
        'registration_id' => $registration->id,
        'qr_status' => $registration->qr_status,
    ];
    print_r($response);
} else {
    echo "No registrations found to test.\n";
}
