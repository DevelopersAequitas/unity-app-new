<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class TestMembershipWelcomeMailCommand extends Command
{
    protected $signature = 'mail:test-membership-welcome {email : Recipient email address for the local welcome email test}';

    protected $description = 'Send a local Membership Welcome Email test using configured From and welcome attachments.';

    public function handle(): int
    {
        $to = trim((string) $this->argument('email'));

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid recipient email address.');
            return self::FAILURE;
        }

        $from = (string) config('peers.membership_welcome_from_email', 'pravin@peersunity.com');
        $fromName = (string) config('peers.membership_welcome_from_name', 'Peers Global Unity');
        $subject = 'Membership Welcome Email Local Test';
        $attachments = $this->resolveAttachments();

        Log::info('Membership welcome email sending started', [
            'type' => 'membership_welcome_test',
            'user_id' => null,
            'to' => $to,
            'from' => $from,
            'mailer' => (string) config('mail.default'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'queue_connection' => (string) config('queue.default'),
            'attachment_1_exists' => $this->configuredAttachmentExists('membership_welcome_attachment_path_1'),
            'attachment_2_exists' => $this->configuredAttachmentExists('membership_welcome_attachment_path_2'),
        ]);

        try {
            Mail::raw('Test email from Peers Global Unity using localhost Membership Welcome Email configuration.', function ($message) use ($to, $from, $fromName, $subject, $attachments): void {
                $message->from($from, $fromName);
                $message->to($to);
                $message->subject($subject);

                foreach ($attachments as $attachment) {
                    $message->attach($attachment);
                }
            });

            if ((string) config('mail.default') === 'log') {
                Log::info('Membership welcome email written to log mailer, not delivered externally.', [
                    'type' => 'membership_welcome_test',
                    'to' => $to,
                    'from' => $from,
                ]);
            }

            Log::info('Membership welcome email sent successfully', [
                'type' => 'membership_welcome_test',
                'to' => $to,
                'from' => $from,
                'attachments_count' => count($attachments),
            ]);

            $this->info("Membership welcome test email sent successfully to {$to}");
            return self::SUCCESS;
        } catch (Throwable $throwable) {
            Log::error('Membership welcome email failed', [
                'type' => 'membership_welcome_test',
                'to' => $to,
                'from' => $from,
                'error' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
            ]);

            $this->error('Membership welcome test email failed: ' . $throwable->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveAttachments(): array
    {
        $paths = array_filter([
            config('peers.membership_welcome_attachment_path_1'),
            config('peers.membership_welcome_attachment_path_2'),
        ]);

        $attachments = [];
        $attached = [];

        foreach ($paths as $path) {
            $path = trim((string) $path);
            $fullPath = Str::startsWith($path, ['/']) ? $path : base_path($path);
            $realPath = realpath($fullPath);

            if ($realPath !== false && in_array($realPath, $attached, true)) {
                Log::info('Duplicate welcome attachment path skipped', ['path' => $path]);
                continue;
            }

            if (is_file($fullPath)) {
                $attachments[] = $fullPath;
                $attached[] = $realPath ?: $fullPath;
                continue;
            }

            Log::warning('Membership welcome attachment missing', ['path' => $path, 'resolved_path' => $fullPath]);
        }

        if ($attachments === []) {
            Log::warning('Membership welcome attachments unavailable; test email will be sent without attachments.');
        }

        return $attachments;
    }

    private function configuredAttachmentExists(string $configKey): bool
    {
        $path = trim((string) config('peers.' . $configKey, ''));
        if ($path === '') {
            return false;
        }

        $fullPath = Str::startsWith($path, ['/']) ? $path : base_path($path);

        return is_file($fullPath);
    }
}
