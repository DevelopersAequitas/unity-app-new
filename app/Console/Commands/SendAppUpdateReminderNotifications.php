<?php

namespace App\Console\Commands;

use App\Models\AppVersion;
use App\Models\UserMobileVersion;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppUpdateReminderNotifications extends Command
{
    protected $signature = 'app:update-reminder-notifications';

    protected $description = 'Send app update reminder push notifications to users with outdated installed app versions in user_mobile_versions.';

    public function handle(NotificationService $notificationService): int
    {
        Log::info('App update reminder command started');
        $this->info('App update reminder command started.');

        // 1. Fetch platform configurations
        $androidConfig = AppVersion::where('platform', 'android')->where('is_active', true)->first();
        $iosConfig = AppVersion::where('platform', 'ios')->where('is_active', true)->first();

        if (! $androidConfig && ! $iosConfig) {
            Log::warning('App update reminder skipped because no active app version configuration was found.');
            $this->warn('No active configurations found.');

            return self::SUCCESS;
        }

        $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share';
        $appStoreUrl = 'https://apps.apple.com/in/app/peers-global-unity/id6739198477';

        // 2. Fetch all mobile versions with users
        $userVersions = UserMobileVersion::with('user')->get();

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($userVersions as $record) {
            if (! $record->user) {
                continue;
            }

            $config = strtolower($record->platform) === 'ios' ? $iosConfig : $androidConfig;
            if (! $config || ! $config->is_active) {
                continue;
            }

            // Compare version
            if (version_compare($record->app_version, $config->latest_version, '<')) {
                $isForce = in_array(strtolower((string) $config->update_type), ['force', 'forced', 'mandatory'], true)
                    || version_compare($record->app_version, $config->min_version, '<');

                $title = $isForce ? 'Important App Update Required' : 'New Version Available 🚀';
                $body = $isForce
                    ? 'Please update Peers Global Unity to continue using the latest features safely.'
                    : 'A newer Peers Global Unity app is ready. Update now for smoother networking and latest improvements.';

                $data = [
                    'type' => 'app_update',
                    'notification_type' => 'app_update',
                    'latest_version' => $config->latest_version,
                    'min_version' => $config->min_version,
                    'update_type' => $config->update_type,
                    'playstore_url' => $playStoreUrl,
                    'appstore_url' => $appStoreUrl,
                ];

                try {
                    $result = $notificationService->sendToUser($record->user, 'app_update', $title, $body, $data, [
                        'channel' => 'push',
                        'bypass_daily_limit' => true,
                    ]);

                    if ($result) {
                        $sentCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (Throwable $exception) {
                    $failedCount++;
                    Log::error('App update reminder notification failed', [
                        'user_id' => (string) $record->user_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        Log::info('App update reminder command finished', [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
        ]);

        $this->info(sprintf('Done. Sent: %d, Failed: %d, Skipped: %d.', $sentCount, $failedCount, $skippedCount));

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
