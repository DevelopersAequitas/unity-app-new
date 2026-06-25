<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMembershipEmailDelivery extends Command
{
    protected $signature = 'membership:mail-test {email=vinitchavda222@gmail.com}';

    protected $description = 'Send a membership email delivery diagnostic message through the configured mail transport.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $subject = 'Peers Global Membership Email Delivery Test';

        try {
            Log::info('Sending Membership Email', [
                'email' => $email,
                'subject' => $subject,
                'mail' => $this->mailDiagnostics(),
            ]);

            Mail::raw('This is a Peers Global membership email delivery diagnostic message.', function ($message) use ($email, $subject): void {
                $message->from('pravin@peersunity.com', 'Peers Global')
                    ->to($email)
                    ->subject($subject);
            });

            Log::info('Membership Email Sent Successfully', [
                'email' => $email,
                'subject' => $subject,
            ]);

            $this->info("Membership email delivery test sent to {$email}.");

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            Log::error('Membership Email Failed', [
                'email' => $email,
                'subject' => $subject,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'mail' => $this->mailDiagnostics(),
            ]);

            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function mailDiagnostics(): array
    {
        $defaultMailer = (string) config('mail.default');
        $mailerConfig = (array) config("mail.mailers.{$defaultMailer}", []);

        return [
            'default_mailer' => $defaultMailer,
            'transport' => (string) ($mailerConfig['transport'] ?? $defaultMailer),
            'host' => $mailerConfig['host'] ?? null,
            'port' => $mailerConfig['port'] ?? null,
            'username' => filled($mailerConfig['username'] ?? null) ? '[configured]' : null,
            'from_address' => 'pravin@peersunity.com',
            'from_name' => 'Peers Global',
        ];
    }
}
