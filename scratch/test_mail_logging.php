<?php

use App\Models\EmailLog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// 1. Manually create a pending log (simulating application flow)
$recipient = 'test_logger_recipient_'.time().'@example.com';
$subject = 'Test Mail Logging - '.time();

$preLog = EmailLog::create([
    'id' => (string) Str::uuid(),
    'to_email' => $recipient,
    'template_key' => 'test_template',
    'subject' => $subject,
    'status' => 'pending',
    'created_at' => now(),
    'sent_at' => now(),
]);

echo 'Created pending log: '.$preLog->id.' for '.$recipient.PHP_EOL;

// 2. Send the email (this should trigger our listener)
echo 'Sending email...'.PHP_EOL;
Mail::raw('Hello, this is the body of the test email!', function ($message) use ($recipient, $subject) {
    $message->to($recipient)->subject($subject);
});

// 3. Retrieve the log and inspect its body
$postLog = EmailLog::find($preLog->id);
echo 'Database check:'.PHP_EOL;
echo 'Log ID: '.$postLog->id.PHP_EOL;
echo 'Recipient: '.$postLog->to_email.PHP_EOL;
echo 'Subject: '.$postLog->subject.PHP_EOL;
echo 'Body HTML: '.($postLog->body_html ?? 'NULL').PHP_EOL;
echo 'Body Text: '.($postLog->body_text ?? 'NULL').PHP_EOL;
