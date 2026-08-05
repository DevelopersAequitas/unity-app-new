<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\User;
use App\Models\UserMobileVersion;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppUpdatesController extends Controller
{
    /**
     * Display the App Updates settings and user mobile devices list.
     */
    public function index(Request $request)
    {
        $androidConfig = AppVersion::where('platform', 'android')->first()
            ?? new AppVersion(['platform' => 'android', 'latest_version' => '1.0.0', 'min_version' => '1.0.0', 'update_type' => 'optional', 'is_active' => false]);

        $iosConfig = AppVersion::where('platform', 'ios')->first()
            ?? new AppVersion(['platform' => 'ios', 'latest_version' => '1.0.0', 'min_version' => '1.0.0', 'update_type' => 'optional', 'is_active' => false]);

        // Default Play Store and App Store URLs
        $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share';
        $appStoreUrl = 'https://apps.apple.com/in/app/peers-global-unity/id6739198477';

        // Query user mobile versions with search filter
        $search = $request->input('search');
        $query = UserMobileVersion::with('user');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('platform', 'like', "%{$search}%")
                ->orWhere('app_version', 'like', "%{$search}%")
                ->orWhere('device_model', 'like', "%{$search}%")
                ->orWhere('os_version', 'like', "%{$search}%");
        }

        $userVersions = $query->latest()->paginate(25);

        // Map status for each user record
        $userVersions->getCollection()->transform(function ($record) use ($androidConfig, $iosConfig) {
            $config = strtolower($record->platform) === 'ios' ? $iosConfig : $androidConfig;

            $record->status_text = 'Up to Date';
            $record->status_class = 'bg-emerald-50 text-emerald-700 border-emerald-200';

            if ($config && $config->is_active) {
                if (version_compare($record->app_version, $config->latest_version, '<')) {
                    $isForce = in_array(strtolower((string) $config->update_type), ['force', 'forced', 'mandatory'], true)
                        || version_compare($record->app_version, $config->min_version, '<');

                    if ($isForce) {
                        $record->status_text = 'Forced Update Required';
                        $record->status_class = 'bg-rose-50 text-rose-700 border-rose-200';
                    } else {
                        $record->status_text = 'Optional Update Required';
                        $record->status_class = 'bg-amber-50 text-amber-700 border-amber-200';
                    }
                }
            }

            return $record;
        });

        return view('admin.app-updates.index', compact('androidConfig', 'iosConfig', 'playStoreUrl', 'appStoreUrl', 'userVersions'));
    }

    /**
     * Save platform update settings.
     */
    public function saveSettings(Request $request, string $platform)
    {
        $request->validate([
            'latest_version' => 'required|string',
            'min_version' => 'required|string',
            'update_type' => 'required|string|in:optional,force',
            'is_active' => 'boolean',
            'release_notes' => 'nullable|string',
        ]);

        $config = AppVersion::firstOrNew(['platform' => $platform]);

        $oldLatestVersion = $config->latest_version;
        $versionChanged = $oldLatestVersion !== $request->input('latest_version');

        $config->latest_version = $request->input('latest_version');
        $config->min_version = $request->input('min_version');
        $config->update_type = $request->input('update_type');
        $config->is_active = $request->boolean('is_active');
        $config->release_notes = $request->input('release_notes');
        $config->save();

        // If the latest version was updated/changed, send push notification to all outdated users on this platform
        if ($versionChanged && $config->is_active) {
            $this->notifyAllOutdatedUsers($platform, $config);
        }

        return redirect()->route('admin.app-updates.index')->with('success', ucfirst($platform).' configuration updated successfully.');
    }

    /**
     * Notify manually selected users.
     */
    public function notifySelected(Request $request)
    {
        $userIds = $request->input('user_ids', []);

        if (empty($userIds)) {
            return response()->json(['success' => false, 'message' => 'No users selected.']);
        }

        $users = User::whereIn('id', $userIds)->get();
        $notificationService = app(NotificationService::class);
        $sentCount = 0;

        // Fetch configs
        $androidConfig = AppVersion::where('platform', 'android')->first();
        $iosConfig = AppVersion::where('platform', 'ios')->first();

        $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share';
        $appStoreUrl = 'https://apps.apple.com/in/app/peers-global-unity/id6739198477';

        foreach ($users as $user) {
            // Find latest mobile version record for this user
            $userVersion = UserMobileVersion::where('user_id', $user->id)->first();
            if (! $userVersion) {
                continue;
            }

            $config = strtolower($userVersion->platform) === 'ios' ? $iosConfig : $androidConfig;
            if (! $config || ! $config->is_active) {
                continue;
            }

            if (version_compare($userVersion->app_version, $config->latest_version, '<')) {
                $isForce = in_array(strtolower((string) $config->update_type), ['force', 'forced', 'mandatory'], true)
                    || version_compare($userVersion->app_version, $config->min_version, '<');

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

                $notificationService->sendToUser($user, 'app_update', $title, $body, $data, [
                    'channel' => 'push',
                    'bypass_daily_limit' => true,
                ]);

                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully sent update reminder to {$sentCount} outdated users.",
        ]);
    }

    /**
     * Helper to send notification to all outdated users of a platform.
     */
    private function notifyAllOutdatedUsers(string $platform, AppVersion $config)
    {
        $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share';
        $appStoreUrl = 'https://apps.apple.com/in/app/peers-global-unity/id6739198477';

        $outdatedRecords = UserMobileVersion::with('user')
            ->where('platform', $platform)
            ->get()
            ->filter(fn ($rec) => version_compare($rec->app_version, $config->latest_version, '<'));

        $notificationService = app(NotificationService::class);
        $isForce = in_array(strtolower((string) $config->update_type), ['force', 'forced', 'mandatory'], true);

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

        foreach ($outdatedRecords as $record) {
            if ($record->user) {
                try {
                    $notificationService->sendToUser($record->user, 'app_update', $title, $body, $data, [
                        'channel' => 'push',
                        'bypass_daily_limit' => true,
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed sending instant update notification to user ID: {$record->user_id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
