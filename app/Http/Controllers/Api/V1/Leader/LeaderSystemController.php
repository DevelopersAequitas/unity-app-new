<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Services\SystemAppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderSystemController extends Controller
{
    public function __construct(
        private readonly SystemAppConfigService $systemAppConfigService
    ) {}

    /**
     * Get Leader App system configuration for force/optional update and maintenance mode.
     */
    public function appConfig(Request $request): JsonResponse
    {
        $platform = (string) ($request->query('platform') ?? $request->header('X-Platform') ?? 'android');
        $data = $this->systemAppConfigService->getSystemAppConfig('leader', $platform);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Leader app system configuration retrieved successfully.',
            'data' => $data,
use App\Models\AppConfigSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class LeaderSystemController extends Controller
{
    /**
     * Get Leader App system configuration for force/optional update and maintenance mode.
     */
    public function appConfig(): JsonResponse
    {
        $config = AppConfigSetting::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        $hasLeaderCols = Schema::hasColumn('app_config_settings', 'leader_min_required_version');

        $minVersion = '1.0.0';
        $latestVersion = '1.0.0';
        $isMaintenance = false;
        $maintenanceTitle = 'System Under Maintenance';
        $maintenanceMessage = 'We are currently performing essential infrastructure upgrades. Please check back shortly.';
        $forceUpdateTitle = 'App Update Required';
        $forceUpdateMessage = 'A critical new version of Leader App is required to access your circle data. Please update from the store.';
        $optionalUpdateTitle = 'New Update Available';
        $optionalUpdateMessage = 'A new version is available with enhanced analytics and performance improvements.';

        if ($config && $hasLeaderCols) {
            $minVersion = (string) ($config->leader_min_required_version ?? '1.0.0');
            $latestVersion = (string) ($config->leader_latest_version ?? '1.0.0');
            $isMaintenance = (bool) ($config->leader_is_maintenance_mode ?? false);
            $maintenanceTitle = (string) ($config->leader_maintenance_title ?? $maintenanceTitle);
            $maintenanceMessage = (string) ($config->leader_maintenance_message ?? $maintenanceMessage);
            $forceUpdateTitle = (string) ($config->leader_force_update_title ?? $forceUpdateTitle);
            $forceUpdateMessage = (string) ($config->leader_force_update_message ?? $forceUpdateMessage);
            $optionalUpdateTitle = (string) ($config->leader_optional_update_title ?? $optionalUpdateTitle);
            $optionalUpdateMessage = (string) ($config->leader_optional_update_message ?? $optionalUpdateMessage);
        }

        $storeAndroid = $config?->playstore_url ?? 'https://play.google.com/store/apps/details?id=com.peersunity.leaderapp';
        $storeIos = $config?->appstore_url ?? 'https://apps.apple.com/app/leader-app/id123456789';

        return response()->json([
            'success' => true,
            'data' => [
                'min_required_version' => $minVersion,
                'latest_version' => $latestVersion,
                'is_maintenance_mode' => $isMaintenance,
                'maintenance_title' => $maintenanceTitle,
                'maintenance_message' => $maintenanceMessage,
                'force_update_title' => $forceUpdateTitle,
                'force_update_message' => $forceUpdateMessage,
                'optional_update_title' => $optionalUpdateTitle,
                'optional_update_message' => $optionalUpdateMessage,
                'store_url_android' => $storeAndroid,
                'store_url_ios' => $storeIos,
                'allowed_bypass_roles' => ['superAdmin', 'super_admin'],
            ],
        ]);
    }
}
