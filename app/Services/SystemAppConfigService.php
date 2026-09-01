<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppConfigSetting;
use App\Models\AppMaintenance;
use App\Models\AppVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemAppConfigService
{
    /**
     * Get system configuration metadata for app availability, versions & maintenance control.
     *
     * @return array<string, mixed>
     */
    public function getSystemAppConfig(?string $product = null, ?string $platform = null): array
    {
        $product = strtolower(trim((string) ($product ?? 'peers')));
        $platform = strtolower(trim((string) ($platform ?? 'android')));

        $config = null;
        if (Schema::hasTable('app_config_settings')) {
            try {
                $config = AppConfigSetting::query()
                    ->where('is_active', true)
                    ->latest('updated_at')
                    ->first();
            } catch (Throwable) {
                // Ignore query failure and fall back safely to defaults
            }
        }

        $minVersion = $this->resolveMinVersion($config, $product, $platform);
        $latestVersion = $this->resolveLatestVersion($config, $product, $platform);

        $storeAndroid = $this->resolveStoreUrlAndroid($config, $product);
        $storeIos = $this->resolveStoreUrlIos($config, $product);

        $forceUpdateTitle = $this->resolveForceUpdateTitle($config, $product);
        $forceUpdateMessage = $this->resolveForceUpdateMessage($config, $product);
        $optionalUpdateTitle = $this->resolveOptionalUpdateTitle($config, $product);
        $optionalUpdateMessage = $this->resolveOptionalUpdateMessage($config, $product);

        [$isMaintenance, $maintenanceTitle, $maintenanceMessage] = $this->resolveMaintenanceStatus($config, $product);

        $allowedBypassRoles = $this->resolveAllowedBypassRoles($config, $product);

        return [
            'min_required_version' => $minVersion,
            'latest_version' => $latestVersion,
            'store_url_android' => $storeAndroid,
            'store_url_ios' => $storeIos,
            'force_update_title' => $forceUpdateTitle,
            'force_update_message' => $forceUpdateMessage,
            'optional_update_title' => $optionalUpdateTitle,
            'optional_update_message' => $optionalUpdateMessage,
            'is_maintenance_mode' => $isMaintenance,
            'maintenance_title' => $maintenanceTitle,
            'maintenance_message' => $maintenanceMessage,
            'allowed_bypass_roles' => $allowedBypassRoles,
        ];
    }

    private function resolveMinVersion(?AppConfigSetting $config, string $product, string $platform): string
    {
        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_min_required_version')) {
            $val = $config?->leader_min_required_version;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'min_required_version')) {
            $val = $config?->min_required_version;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_versions')) {
            try {
                $query = AppVersion::query()->where('platform', $platform);
                if (Schema::hasColumn('app_versions', 'product')) {
                    $versionRecord = (clone $query)->where('product', $product)->first() ?? $query->first();
                } else {
                    $versionRecord = $query->first();
                }

                if ($versionRecord && ! empty($versionRecord->min_version)) {
                    return (string) $versionRecord->min_version;
                }
            } catch (Throwable) {
                // Safe fallback below
            }
        }

        return (string) config('app_versions.min_required', '1.0.0');
    }

    private function resolveLatestVersion(?AppConfigSetting $config, string $product, string $platform): string
    {
        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_latest_version')) {
            $val = $config?->leader_latest_version;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'latest_version')) {
            $val = $config?->latest_version;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_versions')) {
            try {
                $query = AppVersion::query()->where('platform', $platform);
                if (Schema::hasColumn('app_versions', 'product')) {
                    $versionRecord = (clone $query)->where('product', $product)->first() ?? $query->first();
                } else {
                    $versionRecord = $query->first();
                }

                if ($versionRecord && ! empty($versionRecord->latest_version)) {
                    return (string) $versionRecord->latest_version;
                }
            } catch (Throwable) {
                // Safe fallback below
            }
        }

        return (string) config('app_versions.latest', '1.0.0');
    }

    private function resolveStoreUrlAndroid(?AppConfigSetting $config, string $product): string
    {
        if ($product === 'leader') {
            return 'https://play.google.com/store/apps/details?id=com.peersunity.leaderapp';
        }

        if ($config && ! empty($config->playstore_url)) {
            return (string) $config->playstore_url;
        }

        return (string) config('app_links.android.store_url', 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share');
    }

    private function resolveStoreUrlIos(?AppConfigSetting $config, string $product): string
    {
        if ($product === 'leader') {
            return 'https://apps.apple.com/app/leader-app/id123456789';
        }

        if ($config && ! empty($config->appstore_url)) {
            return (string) $config->appstore_url;
        }

        return (string) config('app_links.ios.store_url', 'https://apps.apple.com/in/app/peers-global-unity/id6739198477');
    }

    private function resolveForceUpdateTitle(?AppConfigSetting $config, string $product): string
    {
        $defaultTitle = $product === 'leader' ? 'Leader App Update Required' : 'App Update Required';

        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_force_update_title')) {
            $val = $config?->leader_force_update_title;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'force_update_title')) {
            $val = $config?->force_update_title;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        return $defaultTitle;
    }

    private function resolveForceUpdateMessage(?AppConfigSetting $config, string $product): string
    {
        $defaultMessage = $product === 'leader'
            ? 'A critical new version of Leader App is required to access your circle data. Please update from the store.'
            : 'A critical new version of Peers Global Unity is required to continue. Please update the app from the store.';

        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_force_update_message')) {
            $val = $config?->leader_force_update_message;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'force_update_message')) {
            $val = $config?->force_update_message;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        return $defaultMessage;
    }

    private function resolveOptionalUpdateTitle(?AppConfigSetting $config, string $product): string
    {
        $defaultTitle = 'New Update Available';

        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_optional_update_title')) {
            $val = $config?->leader_optional_update_title;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'optional_update_title')) {
            $val = $config?->optional_update_title;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        return $defaultTitle;
    }

    private function resolveOptionalUpdateMessage(?AppConfigSetting $config, string $product): string
    {
        $defaultMessage = $product === 'leader'
            ? 'A new version is available with enhanced analytics and performance improvements.'
            : 'A new version is available with enhanced features and performance improvements.';

        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_optional_update_message')) {
            $val = $config?->leader_optional_update_message;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'optional_update_message')) {
            $val = $config?->optional_update_message;
            if ($val !== null && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        return $defaultMessage;
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function resolveMaintenanceStatus(?AppConfigSetting $config, string $product): array
    {
        $defaultTitle = 'System Under Maintenance';
        $defaultMessage = 'We are currently performing essential infrastructure upgrades. Please check back shortly.';

        // 1. Leader-specific column in AppConfigSetting
        if ($product === 'leader' && Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'leader_is_maintenance_mode')) {
            if ((bool) ($config?->leader_is_maintenance_mode ?? false)) {
                $title = (string) ($config?->leader_maintenance_title ?? $defaultTitle);
                $message = (string) ($config?->leader_maintenance_message ?? $defaultMessage);

                return [true, $title, $message];
            }
        }

        // 2. Global column in AppConfigSetting
        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'is_maintenance_mode')) {
            if ((bool) ($config?->is_maintenance_mode ?? false)) {
                $title = (string) ($config?->maintenance_title ?? $defaultTitle);
                $message = (string) ($config?->maintenance_message ?? $defaultMessage);

                return [true, $title, $message];
            }
        }

        // 3. Dynamic AppMaintenance records
        if (Schema::hasTable('app_maintenances')) {
            try {
                $maintenance = AppMaintenance::query()
                    ->whereIn('status', ['scheduled', 'active'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($maintenance) {
                    $now = Carbon::now();
                    $isExpired = $maintenance->end_time && $now->greaterThanOrEqualTo($maintenance->end_time);

                    if (! $isExpired) {
                        $isActive = $maintenance->status === 'active' ||
                            ($maintenance->status === 'scheduled' && $maintenance->start_time && $now->greaterThanOrEqualTo($maintenance->start_time));

                        if ($isActive) {
                            $title = (string) ($maintenance->title ?: $defaultTitle);
                            $message = (string) ($maintenance->message ?: $defaultMessage);

                            return [true, $title, $message];
                        }
                    }
                }
            } catch (Throwable) {
                // Fallback below
            }
        }

        return [false, $defaultTitle, $defaultMessage];
    }

    /**
     * @return array<int, string>
     */
    private function resolveAllowedBypassRoles(?AppConfigSetting $config, string $product): array
    {
        $defaultRoles = ['superAdmin', 'super_admin'];

        if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'allowed_bypass_roles')) {
            $roles = $config?->allowed_bypass_roles;
            if (is_array($roles) && ! empty($roles)) {
                return array_values(array_unique(array_map('strval', $roles)));
            }
            if (is_string($roles) && trim($roles) !== '') {
                $decoded = json_decode($roles, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    return array_values(array_unique(array_map('strval', $decoded)));
                }
            }
        }

        return $defaultRoles;
    }
}
