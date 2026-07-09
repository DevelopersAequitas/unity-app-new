<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Console\Command;

class SendTestAppNotificationCommand extends Command
{
    protected $signature = 'notification:send-test {user} {--title=Test Notification} {--body=This is a test notification from local backend.}';

    protected $description = 'Send a test notification to a specific user by email or UUID';

    public function handle(FcmService $fcmService): int
    {
        $userInput = $this->argument('user');

        $query = User::where('email', $userInput);
        if (\Illuminate\Support\Str::isUuid($userInput)) {
            $query->orWhere('id', $userInput);
        }
        $user = $query->first();

        if (! $user) {
            $this->error("User not found with email or UUID: {$userInput}");

            return 1;
        }

        $title = $this->option('title');
        $body = $this->option('body');

        $this->info("Sending test notification to user: {$user->email} (ID: {$user->id})");

        // 1. Create the in-app notification row in app_notifications
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'admin_test',
            'category' => 'admin_test',
            'title' => $title,
            'body' => $body,
            'channel' => 'push',
            'priority' => 'high',
            'screen' => 'home',
            'data' => ['screen' => 'home'],
            'status' => 'pending',
        ]);

        $this->info("In-app notification created successfully. ID: {$notification->id}");

        // 2. Fetch active push tokens
        $tokens = $fcmService->activeTokensForUser($user->id);

        $this->info('Active FCM push tokens count for user: '.$tokens->count());

        $attempted = false;
        $success = false;
        $errors = [];

        foreach ($tokens as $token) {
            $attempted = true;
            $this->info('Attempting FCM push to token: '.substr($token->token, 0, 15).'...');
            $result = $fcmService->sendToToken($token, $title, $body, $notification->dataPayload(), $notification);

            if ($result['success'] ?? false) {
                $success = true;
                $this->info('Push delivered successfully via FCM.');
            } else {
                $err = $result['error'] ?? 'Unknown FCM error';
                $errors[] = $err;
                $this->error('FCM push failed: '.$err);
            }
        }

        // Always keep the main in-app notification status as 'sent'
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        $this->info("Notification status updated to 'sent'.");

        if ($attempted) {
            if ($success) {
                $this->info('Test notification flow finished successfully.');
            } else {
                $this->warn('Push was attempted but failed: '.implode(', ', $errors));
            }
        } else {
            $this->warn('Push skipped because user has no push tokens registered.');
        }

        return 0;
    }
}
