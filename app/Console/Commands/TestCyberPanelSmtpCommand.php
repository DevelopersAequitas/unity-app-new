<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestCyberPanelSmtpCommand extends Command
{
    protected $signature = 'mail:test-cyberpanel {to : Recipient email address for the SMTP test}';

    protected $description = 'Send a direct CyberPanel SMTP test email using the configured Laravel mailer.';

    public function handle(): int
    {
        $to = trim((string) $this->argument('to'));

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid recipient email address.');
            return self::FAILURE;
        }

        $from = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        $subject = 'CyberPanel SMTP Test';

        $mailUsername = (string) config('mail.mailers.smtp.username');
        if (strcasecmp($mailUsername, $from) !== 0) {
            $this->warn('MAIL_USERNAME does not match MAIL_FROM_ADDRESS. CyberPanel may reject this with 553 Sender is not allowed to relay emails.');
            Log::warning('CyberPanel SMTP username/from mismatch', [
                'mail_username' => $mailUsername,
                'from' => $from,
            ]);
        }

        Log::info('CyberPanel SMTP test email sending started', [
            'to' => $to,
            'from' => $from,
            'mail_username' => $mailUsername,
            'mail_host' => (string) config('mail.mailers.smtp.host'),
            'mail_port' => (string) config('mail.mailers.smtp.port'),
            'mailer' => (string) config('mail.default'),
            'queue_connection' => (string) config('queue.default'),
            'subject' => $subject,
        ]);

        try {
            Mail::raw('Test email from Peers Global Unity using CyberPanel SMTP.', function ($message) use ($to, $from, $fromName, $subject): void {
                $message->from($from, $fromName);
                $message->to($to);
                $message->subject($subject);
            });

            Log::info('CyberPanel SMTP test email sent successfully', [
                'to' => $to,
                'from' => $from,
            ]);

            $this->info('CyberPanel SMTP test email sent. Check the recipient inbox and spam/junk folder.');
            return self::SUCCESS;
        } catch (Throwable $throwable) {
            Log::error('CyberPanel SMTP test email failed', [
                'to' => $to,
                'from' => $from,
                'error' => $throwable->getMessage(),
            ]);

            $this->error('CyberPanel SMTP test email failed: ' . $throwable->getMessage());
            return self::FAILURE;
        }
    }
}
