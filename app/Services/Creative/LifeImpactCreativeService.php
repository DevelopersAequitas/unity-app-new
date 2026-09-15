<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\LifeImpactCreative;
use App\Models\LifeImpactRecognitionCreative;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LifeImpactCreativeService
{
    public function __construct(
        private readonly LifeImpactCreativeGenerator $creativeGenerator
    ) {}

    /**
     * Get highest active recognition matching or below given count.
     */
    public function getRecognitionForCount(int $lifeImpactedCount): ?LifeImpactRecognitionCreative
    {
        if (! Schema::hasTable('life_impact_recognition_creatives')) {
            return null;
        }

        return LifeImpactRecognitionCreative::query()
            ->where('is_active', true)
            ->where('threshold', '<=', $lifeImpactedCount)
            ->orderBy('threshold', 'desc')
            ->first();
    }

    /**
     * Determine if given count is an active recognition threshold.
     */
    public function isConfiguredMilestone(int $count): bool
    {
        if ($count <= 0) {
            return false;
        }

        if (Schema::hasTable('life_impact_recognition_creatives')) {
            $hasDbRecords = LifeImpactRecognitionCreative::query()
                ->where('is_active', true)
                ->exists();

            if ($hasDbRecords) {
                return LifeImpactRecognitionCreative::query()
                    ->where('is_active', true)
                    ->where('threshold', '<=', $count)
                    ->exists();
            }
        }

        $levels = $this->creativeGenerator->getAllRecognitionLevels();
        foreach ($levels as $threshold => $meta) {
            if ($count >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store and handle life impact creative for a user.
     */
    public function handleLifeImpactCreative(
        User $user,
        int $lifeImpactedCount,
        ?int $overrideThreshold = null
    ): ?LifeImpactCreative {
        if (! Schema::hasTable('life_impact_creatives')) {
            Log::warning('[LifeImpactCreativeService] Table life_impact_creatives does not exist.');

            return null;
        }

        $effectiveCount = $lifeImpactedCount > 0
            ? $lifeImpactedCount
            : (int) ($user->life_impacted_count ?? 0);

        $recognition = null;
        $threshold = $overrideThreshold && $overrideThreshold > 0
            ? $overrideThreshold
            : 0;

        if ($threshold <= 0) {
            $recognition = $this->getRecognitionForCount($effectiveCount);
            if ($recognition) {
                $threshold = (int) $recognition->threshold;
            } else {
                $meta = $this->creativeGenerator->getRecognitionMeta($effectiveCount);
                $threshold = (int) ($meta['required_count'] ?? 25);
            }
        } else {
            if (Schema::hasTable('life_impact_recognition_creatives')) {
                $recognition = LifeImpactRecognitionCreative::query()
                    ->where('is_active', true)
                    ->where('threshold', $threshold)
                    ->first();
            }
        }

        if ($threshold <= 0) {
            $threshold = 25;
        }

        try {
            // 1. Duplicate / Idempotency protection: check if creative was already recorded for (user_id, threshold)
            $existingCreative = LifeImpactCreative::query()
                ->where('user_id', $user->id)
                ->where('threshold', $threshold)
                ->first();

            if ($existingCreative && ! empty($existingCreative->image_url)) {
                $isRawTemplate = str_contains((string) $existingCreative->image_url, '/images/life_impact_badges/');
                $s3Key = preg_replace('~^https?://[^/]+/storage/~i', '', (string) $existingCreative->image_url);
                $s3Key = ltrim($s3Key, '/');

                $fileExists = ! $isRawTemplate && (
                    Storage::disk('public')->exists($s3Key)
                    || Storage::disk(config('filesystems.default', 'public'))->exists($s3Key)
                    || file_exists(storage_path('app/public/'.$s3Key))
                    || file_exists(public_path('storage/'.$s3Key))
                );

                if (! $fileExists || $isRawTemplate) {
                    try {
                        $newUrl = $this->creativeGenerator->generateOrGetUrl($user, $effectiveCount, $threshold, true);
                        $existingCreative->update([
                            'image_url' => $newUrl,
                            'recognition_id' => $recognition?->id ?? $existingCreative->recognition_id,
                        ]);
                        $existingCreative->image_url = $newUrl;
                    } catch (Throwable $regenEx) {
                        Log::warning('[LifeImpactCreativeService] Could not regenerate missing physical creative: '.$regenEx->getMessage());
                    }
                }

                Log::info('[LifeImpactCreativeService] Reusing existing creative record.', [
                    'creative_id' => $existingCreative->id,
                    'user_id' => $user->id,
                    'threshold' => $existingCreative->threshold,
                    'image_url' => $existingCreative->image_url,
                ]);

                return $existingCreative;
            }

            // 2. Generate personalized creative image and get canonical HTTPS URL
            $imageUrl = $this->creativeGenerator->generateOrGetUrl($user, $effectiveCount, $threshold);

            // 3. Atomically create or fetch inside DB transaction
            return DB::transaction(function () use ($user, $recognition, $threshold, $imageUrl): LifeImpactCreative {
                $lockedCreative = LifeImpactCreative::query()
                    ->where('user_id', $user->id)
                    ->where('threshold', $threshold)
                    ->lockForUpdate()
                    ->first();

                if ($lockedCreative) {
                    if ($lockedCreative->image_url !== $imageUrl && ! empty($imageUrl)) {
                        $lockedCreative->update(['image_url' => $imageUrl]);
                    }

                    return $lockedCreative;
                }

                $creative = LifeImpactCreative::create([
                    'user_id' => $user->id,
                    'recognition_id' => $recognition?->id,
                    'threshold' => $threshold,
                    'image_url' => $imageUrl,
                ]);

                Log::info('[LifeImpactCreativeService] Stored life impact creative successfully.', [
                    'creative_id' => $creative->id,
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                    'image_url' => $imageUrl,
                ]);

                return $creative;
            });
        } catch (Throwable $e) {
            Log::error('[LifeImpactCreativeService] Failed to generate/store life impact creative: '.$e->getMessage(), [
                'user_id' => $user->id,
                'life_impacted_count' => $lifeImpactedCount,
                'threshold' => $threshold,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Get or generate canonical public URL for life impact creative.
     */
    public function generateOrGetUrl(User $user, int $lifeImpactedCount = 0, ?int $overrideThreshold = null, bool $forceRegenerate = false): string
    {
        return $this->creativeGenerator->generateOrGetUrl($user, $lifeImpactedCount, $overrideThreshold, $forceRegenerate);
    }
}
