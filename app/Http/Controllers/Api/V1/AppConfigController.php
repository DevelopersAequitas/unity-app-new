<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppConfigSetting;
use App\Models\AppDashboardWidget;
use App\Models\AppFeature;
use App\Models\AppIconAsset;
use App\Models\AppInstance;
use App\Models\AppLabel;
use App\Models\AppMembershipLabel;
use App\Models\AppSocialLink;
use App\Services\AppConfigService;
use App\Support\GreenpreneurIconCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppConfigController extends Controller
{
    public const CACHE_KEY = 'app_config:peers:v2';

    public function publicConfig(Request $request, AppConfigService $appConfigService): JsonResponse
    {
        try {
            $product = strtolower((string) ($request->query('product') ?? $request->header('X-Product') ?? 'peers'));
            $cacheKey = "app_config:{$product}:v2";

            $data = Cache::remember(
                $cacheKey,
                now()->addSeconds(300),
                function () use ($appConfigService) {
                    try {
                        $appInstance = $appConfigService->getGreenpreneurAppInstance();
                        if ($appInstance && $appInstance->is_active) {
                            return self::buildPublicConfig($appInstance);
                        }
                    } catch (Throwable) {
                        // Fallback to default Peers configuration
                    }

                    return self::defaultPublicConfig();
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'App configuration loaded successfully.',
                'data' => $data,
            ])->header('Cache-Control', 'public, max-age=300');
        } catch (Throwable $exception) {
            Log::error('Failed to fetch app configuration.', [
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'App configuration loaded successfully.',
                'data' => self::defaultPublicConfig(),
            ])->header('Cache-Control', 'public, max-age=300');
        }
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('app_config:peers:v2');
        Cache::forget('app_config:greenpreneur:v2');
        Cache::forget('app_config:peers');
    }

    public static function buildPublicConfig(AppInstance $appInstance): array
    {
        $appInstanceId = $appInstance->id;
        $branding = AppConfigSetting::query()
            ->where('app_instance_id', $appInstanceId)
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        $features = AppFeature::query()
            ->where('app_instance_id', $appInstanceId)
            ->orderBy('sort_order')
            ->pluck('is_enabled', 'feature_key')
            ->map(fn ($value) => (bool) $value)
            ->all() ?: self::defaultFeatures();

        $enabledFeatureKeys = AppFeature::query()
            ->where('app_instance_id', $appInstanceId)
            ->where('is_enabled', true)
            ->pluck('feature_key')
            ->toArray() ?: array_keys(array_filter($features));

        $icons = self::icons($appInstanceId);

        return [
            'app_info' => self::appInfo($branding),
            'colors' => self::colors($branding),
            'icons' => $icons,
            'drawer_menu' => $icons['drawer_menu'] ?? [],
            'features' => $features,
            'labels' => AppLabel::query()
                ->where('app_instance_id', $appInstanceId)
                ->where('is_active', true)
                ->pluck('label_value', 'label_key')
                ->all() ?: self::defaultLabels(),
            'navigation_menu' => self::navigationMenu($appInstanceId, $enabledFeatureKeys),
            'dashboard_widgets' => AppDashboardWidget::query()
                ->where('app_instance_id', $appInstanceId)
                ->orderBy('sort_order')
                ->pluck('is_enabled', 'widget_key')
                ->map(fn ($value) => (bool) $value)
                ->all() ?: self::defaultDashboardWidgets(),
            'membership_labels' => self::membershipLabels(),
            'social_links' => AppSocialLink::query()
                ->where('app_instance_id', $appInstanceId)
                ->orderBy('sort_order')
                ->pluck('url', 'platform')
                ->all() ?: self::defaultSocialLinks(),
        ];
    }

    public static function defaultPublicConfig(): array
    {
        return [
            'app_info' => self::defaultAppInfo(),
            'colors' => self::defaultColors(),
            'icons' => self::defaultIcons(),
            'drawer_menu' => [],
            'features' => self::defaultFeatures(),
            'labels' => self::defaultLabels(),
            'navigation_menu' => [],
            'dashboard_widgets' => self::defaultDashboardWidgets(),
            'membership_labels' => self::defaultMembershipLabels(),
            'social_links' => self::defaultSocialLinks(),
        ];
    }

    private static function appInfo(?AppConfigSetting $branding): array
    {
        $defaults = self::defaultAppInfo();
        $light = $branding?->logo_url_light ?: $branding?->app_logo_url ?: $defaults['logo_url_light'];
        $splash = $branding?->logo_url_splash ?: $branding?->splash_logo_url ?: $defaults['logo_url_splash'];

        return [
            'app_name' => $branding?->app_name ?: $defaults['app_name'],
            'logo_url_light' => $light,
            'logo_url_dark' => $branding?->logo_url_dark ?: $light,
            'logo_url_splash' => $splash,
            'app_logo_url' => $light,
            'splash_logo_url' => $splash,
            'playstore_url' => $branding?->playstore_url ?: $defaults['playstore_url'],
            'appstore_url' => $branding?->appstore_url ?: $defaults['appstore_url'],
        ];
    }

    private static function colors(?AppConfigSetting $branding): array
    {
        $defaults = self::defaultColors();
        $colors = [];
        foreach ($defaults as $key => $default) {
            $value = $branding?->{$key};
            $colors[$key] = is_string($value) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value) ? $value : $default;
        }

        return $colors;
    }

    private static function icons(string $appInstanceId): array
    {
        if (! Schema::hasTable('app_icon_assets')) {
            return self::defaultIcons();
        }

        $icons = AppIconAsset::query()
            ->where('app_instance_id', $appInstanceId)
            ->orderBy('icon_group')
            ->orderBy('sort_order')
            ->get();

        if ($icons->isEmpty()) {
            return self::defaultIcons();
        }

        $drawerNavigationState = self::drawerNavigationState($appInstanceId);
        $grouped = collect(GreenpreneurIconCatalog::GROUPS)
            ->mapWithKeys(fn ($label, $group) => [$group => []])
            ->all();

        foreach ($icons as $icon) {
            $group = $icon->icon_group ?: 'custom_assets';
            if (! array_key_exists($group, $grouped)) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = self::formatIcon($icon, $drawerNavigationState);
        }

        $byKey = $icons->keyBy('icon_key');
        $flat = collect(GreenpreneurIconCatalog::FLAT_MAP)
            ->mapWithKeys(fn ($iconKey, $flatKey) => [$flatKey => $byKey->get($iconKey)?->icon_url])
            ->all();
        $grouped['flat'] = $flat;

        return array_merge($grouped, $flat);
    }

    private static function formatIcon(AppIconAsset $icon, array $drawerNavigationState = []): array
    {
        $isActive = (bool) $icon->is_active;
        if (($icon->icon_group ?: null) === 'drawer_menu') {
            foreach ([$icon->menu_key, $icon->feature_key, $icon->icon_key] as $key) {
                if ($key !== null && array_key_exists((string) $key, $drawerNavigationState)) {
                    $isActive = $drawerNavigationState[(string) $key];
                    break;
                }
            }
        }

        return [
            'id' => $icon->id,
            'icon_key' => $icon->icon_key,
            'title' => $icon->title,
            'subtitle' => $icon->subtitle,
            'icon_url' => $icon->icon_url,
            'icon_group' => $icon->icon_group,
            'menu_key' => $icon->menu_key,
            'target_screen' => $icon->target_screen,
            'feature_key' => $icon->feature_key,
            'badge_text' => $icon->badge_text,
            'is_active' => $isActive,
            'sort_order' => $icon->sort_order,
        ];
    }

    private static function drawerNavigationState(string $appInstanceId): array
    {
        $navigationItems = Schema::hasTable('app_navigation_items')
            ? AppNavigationItem::query()
                ->where('app_instance_id', $appInstanceId)
                ->pluck('is_enabled', 'nav_key')
                ->all()
            : [];

        $features = Schema::hasTable('app_features')
            ? AppFeature::query()
                ->where('app_instance_id', $appInstanceId)
                ->pluck('is_enabled', 'feature_key')
                ->all()
            : [];

        return array_merge($navigationItems, $features);
    }

    private static function navigationMenu(string $appInstanceId, array $enabledFeatureKeys = []): array
    {
        if (! Schema::hasTable('app_navigation_items')) {
            return [];
        }

        return AppNavigationItem::query()
            ->where('app_instance_id', $appInstanceId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (AppNavigationItem $item) => empty($item->feature_key) || in_array($item->feature_key, $enabledFeatureKeys, true))
            ->map(fn (AppNavigationItem $item) => [
                'id' => $item->id,
                'nav_key' => $item->nav_key,
                'title' => $item->title,
                'target_screen' => $item->target_screen,
                'icon_name' => $item->icon_name,
                'icon_url' => $item->icon_url,
                'sort_order' => $item->sort_order,
            ])
            ->values()
            ->all();
    }

    private static function defaultIcons(): array
    {
        return [
            'drawer_menu' => [],
            'dashboard_shortcuts' => [],
            'custom_assets' => [],
            'flat' => [],
        ];
    }

    private static function defaultFeatures(): array
    {
        return [
            'events' => true,
            'referrals' => true,
            'business_deals' => true,
            'p2p_meetings' => true,
            'testimonials' => true,
            'requirements' => true,
            'collaborations' => true,
            'collaboration_ask' => true,
            'visitor_registration' => true,
            'add_impact' => true,
            'claim_coins' => true,
            'coins_wallet' => true,
            'leaderboard' => true,
            'impact_score' => true,
            'badges' => true,
            'gratitude_score' => false,
            'circles' => true,
            'chat_messaging' => true,
            'geo_nearby' => false,
            'circulars' => true,
            'gallery' => true,
            'videos' => true,
            'meeting_schedule' => true,
            'invoices' => true,
            'blocked_users' => true,
            'welcome_creative' => true,
            'feedback' => true,
            'qr_scan' => true,
            'community_feed' => true,
            'leadership_form' => true,
            'recommend_peer' => true,
            'peers' => true,
        ];
    }

    private static function defaultDashboardWidgets(): array
    {
        return [
            'banner_carousel' => true,
            'leaderboard_preview' => true,
            'impact_tracker' => true,
            'upcoming_events' => true,
            'hot_deals' => true,
            'membership_banner' => true,
            'feed_composer' => true,
            'circle_preview' => true,
            'community_feed' => true,
        ];
    }

    private static function defaultSocialLinks(): array
    {
        return [
            'linkedin' => 'https://linkedin.com/company/peersunity',
            'instagram' => 'https://instagram.com/peersunity',
            'facebook' => 'https://facebook.com/peersunity',
            'youtube' => null,
            'website' => 'https://peersunity.com',
        ];
    }

    private static function defaultAppInfo(): array
    {
        return [
            'app_name' => 'Peers Global Unity',
            'logo_url_light' => 'https://peersunity.com/assets/brand/logo_light.png',
            'logo_url_dark' => 'https://peersunity.com/assets/brand/logo_dark.png',
            'logo_url_splash' => 'https://peersunity.com/assets/brand/logo_splash.png',
            'app_logo_url' => 'https://peersunity.com/assets/brand/logo_light.png',
            'splash_logo_url' => 'https://peersunity.com/assets/brand/logo_splash.png',
            'playstore_url' => 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share',
            'appstore_url' => 'https://apps.apple.com/in/app/peers-global-unity/id6739198477',
        ];
    }

    private static function defaultColors(): array
    {
        return [
            'primary_color' => '#2563EB',
            'secondary_color' => '#1D4ED8',
            'accent_color' => '#3B82F6',
            'background_color' => '#FFFFFF',
            'surface_color' => '#F8FAFC',
            'text_primary_color' => '#0F172A',
            'text_secondary_color' => '#475569',
            'error_color' => '#EF4444',
            'success_color' => '#10B981',
            'warning_color' => '#F59E0B',
        ];
    }

    private static function defaultLabels(): array
    {
        return [
            'app_name' => 'Peers Global Unity',
            'peer' => 'Peer',
            'peers' => 'Peers',
            'my_peers' => 'My Peers',
            'circle' => 'Circle',
            'circles' => 'Circles',
            'event' => 'Event',
            'events' => 'Events',
            'coin' => 'Coin',
            'coins' => 'Coins',
            'impact' => 'Impact',
            'lives_impacted' => 'Lives Impacted',
            'referral' => 'Referral',
            'business_deal' => 'Business Deal',
            'p2p_meeting' => 'Peer Meeting',
            'requirement' => 'Requirement',
            'post_an_ask' => 'Post a Need',
            'visitor' => 'Visitor',
            'register_visitor' => 'Register Visitor',
            'circular' => 'Circular',
            'circulars' => 'Circulars',
            'chat' => 'Messages',
            'leaderboard' => 'Leaderboard',
            'badge' => 'Badge',
            'welcome_title' => 'Welcome to Peers Global Unity',
            'welcome_subtitle' => 'Connect, collaborate, and grow with global peers',
            'register_button' => 'Join Now',
            'login_button' => 'Login',
            'activities_section_title' => 'ACTIVITIES',
            'impact_section_title' => 'IMPACT DASHBOARD',
            'share_message' => 'Join Peers Global Unity to connect, collaborate and grow with global peers.',
        ];
    }

    private static function membershipLabels(): array
    {
        if (! Schema::hasTable('app_membership_labels')) {
            return self::defaultMembershipLabels();
        }

        $labels = AppMembershipLabel::query()
            ->where('is_enabled', true)
            ->pluck('display_label', 'membership_key')
            ->all();

        return $labels ?: self::defaultMembershipLabels();
    }

    private static function defaultMembershipLabels(): array
    {
        return [
            'free_peer' => 'Free Member',
            'unity_peer' => 'Unity Peer',
            'only_unity_peer' => 'Global Peer',
            'chartered_peer' => 'Chartered Peer',
            'charter_investor' => 'Charter Investor',
        ];
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 500);
    }
}
