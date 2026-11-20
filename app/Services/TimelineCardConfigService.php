<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TimelineCardConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TimelineCardConfigService
{
    public const CACHE_KEY = 'app_config:timeline_cards:v1';

    public const CACHE_TTL_SECONDS = 300;

    /**
     * @return array{
     *     profile_completion_enabled: bool,
     *     you_can_help_enabled: bool,
     *     events_spotlight_enabled: bool,
     *     circle_vacancy_enabled: bool,
     *     suggested_peers_enabled: bool
     * }
     */
    public function getTimelineCardsConfig(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            return $this->resolveConfigFromDatabase();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     profile_completion_enabled: bool,
     *     you_can_help_enabled: bool,
     *     events_spotlight_enabled: bool,
     *     circle_vacancy_enabled: bool,
     *     suggested_peers_enabled: bool
     * }
     */
    public function updateTimelineCardsConfig(array $data, ?string $userId = null): array
    {
        $allowedKeys = [
            'profile_completion_enabled',
            'you_can_help_enabled',
            'events_spotlight_enabled',
            'circle_vacancy_enabled',
            'suggested_peers_enabled',
        ];

        $payload = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = (bool) $data[$key];
            }
        }

        if ($userId !== null && $userId !== '') {
            $payload['updated_by_user_id'] = $userId;
        }

        if (! Schema::hasTable('timeline_card_configs')) {
            Log::warning('[TimelineCardConfigService] Table timeline_card_configs does not exist.');

            return $this->defaultConfig();
        }

        try {
            $config = TimelineCardConfig::query()->find(1);

            if ($config) {
                $config->fill($payload)->save();
            } else {
                $initialPayload = array_merge($this->defaultConfig(), $payload, ['id' => 1]);
                $config = TimelineCardConfig::query()->create($initialPayload);
            }

            Cache::forget(self::CACHE_KEY);

            return [
                'profile_completion_enabled' => (bool) $config->profile_completion_enabled,
                'you_can_help_enabled' => (bool) $config->you_can_help_enabled,
                'events_spotlight_enabled' => (bool) $config->events_spotlight_enabled,
                'circle_vacancy_enabled' => (bool) $config->circle_vacancy_enabled,
                'suggested_peers_enabled' => (bool) $config->suggested_peers_enabled,
            ];
        } catch (Throwable $e) {
            Log::error('[TimelineCardConfigService] Failed to update timeline card configs', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw $e;
        }
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     profile_completion_enabled: bool,
     *     you_can_help_enabled: bool,
     *     events_spotlight_enabled: bool,
     *     circle_vacancy_enabled: bool,
     *     suggested_peers_enabled: bool
     * }
     */
    private function resolveConfigFromDatabase(): array
    {
        if (! Schema::hasTable('timeline_card_configs')) {
            return $this->defaultConfig();
        }

        try {
            $config = TimelineCardConfig::query()->find(1);

            if (! $config) {
                return $this->defaultConfig();
            }

            return [
                'profile_completion_enabled' => (bool) $config->profile_completion_enabled,
                'you_can_help_enabled' => (bool) $config->you_can_help_enabled,
                'events_spotlight_enabled' => (bool) $config->events_spotlight_enabled,
                'circle_vacancy_enabled' => (bool) $config->circle_vacancy_enabled,
                'suggested_peers_enabled' => (bool) $config->suggested_peers_enabled,
            ];
        } catch (Throwable $e) {
            Log::warning('[TimelineCardConfigService] Failed to read database config, using defaults', [
                'error' => $e->getMessage(),
            ]);

            return $this->defaultConfig();
        }
    }

    /**
     * @return array{
     *     profile_completion_enabled: bool,
     *     you_can_help_enabled: bool,
     *     events_spotlight_enabled: bool,
     *     circle_vacancy_enabled: bool,
     *     suggested_peers_enabled: bool
     * }
     */
    public function defaultConfig(): array
    {
        return [
            'profile_completion_enabled' => true,
            'you_can_help_enabled' => true,
            'events_spotlight_enabled' => true,
            'circle_vacancy_enabled' => true,
            'suggested_peers_enabled' => true,
        ];
    }
}
