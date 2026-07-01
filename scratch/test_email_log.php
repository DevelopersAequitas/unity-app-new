<?php
// Run: php artisan tinker < scratch/test_email_log.php

use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\Log;

try {
    $service = app(EmailLogService::class);
    $result = $service->logSent([
        'to_email'      => 'test@example.com',
        'subject'       => 'Your Admin Login OTP',
        'template_key'  => 'admin_login_otp',
        'source_module' => 'Admin Auth',
        'body_text'     => 'Your admin login OTP is 9999. It expires in 5 minutes.',
        'payload'       => ['purpose' => 'admin_login_otp'],
    ]);

    if ($result) {
        echo "SUCCESS - ID: " . $result->id . PHP_EOL;
    } else {
        echo "RETURNED NULL - Check laravel.log for 'Email logging failed' warning" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

// Also check the warning log
$logPath = storage_path('logs/laravel.log');
$lines = file($logPath);
$last200 = array_slice($lines, -200);
foreach ($last200 as $line) {
    if (str_contains($line, 'Email logging failed')) {
        echo "LOG WARNING: " . $line;
    }
}
