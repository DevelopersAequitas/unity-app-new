<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Models\AppFeature;
use App\Models\AppTutorialStep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AppTutorialPopupService
{
    public const MASTER_CACHE_KEY = 'app_config:tutorials:master_toggle';

    public function __construct(
        private readonly AppConfigService $appConfigService
    ) {}

    /**
     * Get the master tutorials toggle status.
     */
    public function getMasterToggle(): bool
    {
        if (Cache::has(self::MASTER_CACHE_KEY)) {
            return (bool) Cache::get(self::MASTER_CACHE_KEY);
        }

        if (Schema::hasTable('app_features')) {
            try {
                $feature = AppFeature::query()
                    ->where('feature_key', 'tutorials')
                    ->first();

                if ($feature !== null) {
                    return (bool) $feature->is_enabled;
                }
            } catch (Throwable) {
                // Fallback below
            }
        }

        return true;
    }

    /**
     * Get all tutorial step definitions with their current toggles.
     *
     * @return array<int, array{
     *     step_key: string,
     *     step_name: string,
     *     description: string,
     *     is_enabled: bool,
     *     sort_order: int
     * }>
     */
    public function getStepsWithMeta(): array
    {
        $defaults = $this->defaultStepsMeta();
        $dbSteps = [];

        if (Schema::hasTable('app_tutorial_steps')) {
            try {
                $dbSteps = AppTutorialStep::query()
                    ->orderBy('sort_order')
                    ->get()
                    ->keyBy('step_key')
                    ->all();
            } catch (Throwable) {
                $dbSteps = [];
            }
        }

        $result = [];
        foreach ($defaults as $step) {
            $key = $step['step_key'];
            if (isset($dbSteps[$key])) {
                $step['is_enabled'] = (bool) $dbSteps[$key]->is_enabled;
                $step['sort_order'] = (int) $dbSteps[$key]->sort_order;
                if (! empty($dbSteps[$key]->step_name)) {
                    $step['step_name'] = (string) $dbSteps[$key]->step_name;
                }
                if (! empty($dbSteps[$key]->description)) {
                    $step['description'] = (string) $dbSteps[$key]->description;
                }
            }

            $result[] = $step;
        }

        usort($result, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $result;
    }

    /**
     * Update master toggle and individual tutorial steps.
     *
     * @param array<string, array{is_enabled?: bool|string|int, sort_order?: int|string}> $steps
     */
    public function saveSettings(bool $masterEnabled, array $steps): void
    {
        Cache::forever(self::MASTER_CACHE_KEY, $masterEnabled);

        $appInstanceId = null;
        try {
            $instance = $this->appConfigService->getGreenpreneurAppInstance();
            $appInstanceId = $instance->id;
        } catch (Throwable) {
            // Safe fallback
        }

        if (Schema::hasTable('app_features')) {
            try {
                $featureQuery = AppFeature::query()->where('feature_key', 'tutorials');
                if ($appInstanceId) {
                    $featureQuery->where('app_instance_id', $appInstanceId);
                }

                $feature = $featureQuery->first();
                if ($feature) {
                    $feature->update(['is_enabled' => $masterEnabled]);
                } else {
                    AppFeature::query()->create([
                        'id' => (string) Str::uuid(),
                        'app_instance_id' => $appInstanceId,
                        'feature_key' => 'tutorials',
                        'feature_name' => 'Tutorials',
                        'is_enabled' => $masterEnabled,
                        'sort_order' => 99,
                    ]);
                }
            } catch (Throwable) {
                // Ignore query error
            }
        }

        if (Schema::hasTable('app_tutorial_steps')) {
            $defaultsMeta = collect($this->defaultStepsMeta())->keyBy('step_key');

            foreach ($steps as $key => $data) {
                $isEnabled = isset($data['is_enabled']) && (bool) $data['is_enabled'];
                $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
                $defaultMeta = $defaultsMeta->get($key);

                try {
                    $stepRecord = AppTutorialStep::query()
                        ->where('step_key', $key)
                        ->when($appInstanceId, fn ($q) => $q->where('app_instance_id', $appInstanceId))
                        ->first();

                    if ($stepRecord) {
                        $stepRecord->update([
                            'is_enabled' => $isEnabled,
                            'sort_order' => $sortOrder,
                        ]);
                    } else {
                        AppTutorialStep::query()->create([
                            'id' => (string) Str::uuid(),
                            'app_instance_id' => $appInstanceId,
                            'step_key' => $key,
                            'step_name' => $defaultMeta['step_name'] ?? str($key)->replace('_', ' ')->title()->toString(),
                            'description' => $defaultMeta['description'] ?? null,
                            'is_enabled' => $isEnabled,
                            'sort_order' => $sortOrder,
                        ]);
                    }
                } catch (Throwable) {
                    // Ignore DB exception
                }
            }
        }

        AppConfigController::clearCache();
    }

    /**
     * Default steps metadata list.
     *
     * @return array<int, array{
     *     step_key: string,
     *     step_name: string,
     *     description: string,
     *     is_enabled: bool,
     *     sort_order: int
     * }>
     */
    public function defaultStepsMeta(): array
    {
        return [
            [
                'step_key' => 'peers',
                'step_name' => 'Peers',
                'description' => 'Walkthrough guide for connecting with peers and global network.',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'step_key' => 'circles',
                'step_name' => 'Circles',
                'description' => 'Tour highlight on Circles hub and group collaboration spaces.',
                'is_enabled' => true,
                'sort_order' => 2,
            ],
            [
                'step_key' => 'shorts',
                'step_name' => 'Shorts',
                'description' => 'Tour highlight on video shorts and video-first updates.',
                'is_enabled' => true,
                'sort_order' => 3,
            ],
            [
                'step_key' => 'impact',
                'step_name' => 'Impact',
                'description' => 'Tour highlight on Green Impact score and milestone tracker.',
                'is_enabled' => true,
                'sort_order' => 4,
            ],
            [
                'step_key' => 'directory',
                'step_name' => 'Directory',
                'description' => 'Walkthrough on member search, business categories & peer directory.',
                'is_enabled' => true,
                'sort_order' => 5,
            ],
            [
                'step_key' => 'ask_flows',
                'step_name' => 'Ask Flows',
                'description' => 'Step-by-step introduction for posting and fulfilling business asks.',
                'is_enabled' => true,
                'sort_order' => 6,
            ],
            [
                'step_key' => 'quick_actions',
                'step_name' => 'Quick Actions',
                'description' => 'Spotlight on the central quick-action button and common shortcuts.',
                'is_enabled' => true,
                'sort_order' => 7,
            ],
            [
                'step_key' => 'timeline',
                'step_name' => 'Timeline',
                'description' => 'Tour orientation for the live activity feed and community timeline.',
                'is_enabled' => true,
                'sort_order' => 8,
            ],
        ];
    }
}

