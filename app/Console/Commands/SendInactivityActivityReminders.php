<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityReminderService;
use Illuminate\Console\Command;

class SendInactivityActivityReminders extends Command
{
    protected $signature = 'app:send-inactivity-activity-reminders 
                            {--user-id= : Target a specific user UUID} 
                            {--dry-run : Simulate execution without dispatching push notifications} 
                            {--cooldown-days=3 : Cooldown window in days between activity reminders} 
                            {--new-user-days=3 : Minimum days a user must be registered before receiving inactivity reminders}';

    protected $description = 'Identify inactive users across key modules and dispatch prioritized activity reminder notifications.';

    public function handle(ActivityReminderService $service): int
    {
        $this->info('Starting Smart Inactivity Activity Reminders check...');

        $targetUserId = $this->option('user-id') ? (string) $this->option('user-id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $cooldownDays = max(1, (int) $this->option('cooldown-days'));
        $newUserDays = max(1, (int) $this->option('new-user-days'));

        if ($dryRun) {
            $this->warn('DRY RUN MODE ENABLED: No notifications will actually be dispatched.');
        }

        $result = $service->processInactivityReminders(
            $targetUserId,
            $dryRun,
            $cooldownDays,
            $newUserDays
        );

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users Processed', $result['processed_users']],
                ['Reminders Dispatched', $result['dispatched_reminders']],
                ['Skipped (New Users < '.$newUserDays.'d)', $result['skipped_new_users']],
                ['Skipped (Cooldown Window < '.$cooldownDays.'d)', $result['skipped_cooldown']],
                ['Skipped (Active in all checked modules)', $result['skipped_active']],
            ]
        );

        if (! empty($result['details'])) {
            $this->info('Dispatched Reminders Breakdown:');
            $this->table(
                ['User ID', 'User Name', 'Activity Key', 'Activity Type', 'Target Screen'],
                array_map(fn (array $d): array => [
                    $d['user_id'],
                    $d['user_name'],
                    $d['activity_key'],
                    $d['activity_type'],
                    $d['target_screen'],
                ], $result['details'])
            );
        }

        $this->info('Smart Inactivity Activity Reminders completed successfully.');

        return self::SUCCESS;
    }
}
