<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\IntroductionCreative;
use App\Models\MilestoneBadge;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class IntroductionCreativeService
{
    public function __construct(
        private readonly IntroducedPeerCreativeGenerator $creativeGenerator
    ) {}

    /**
     * Determine whether the given count matches any configured milestone's required_count.
     */
    public function isConfiguredMilestone(int $count): bool
    {
        if ($count <= 0) {
            return false;
        }

        if (Schema::hasTable('milestone_badges')) {
            $hasDbBadges = MilestoneBadge::query()
                ->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)
                ->where('is_active', true)
                ->exists();

            if ($hasDbBadges) {
                return MilestoneBadge::query()
                    ->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)
                    ->where('is_active', true)
                    ->where('required_count', $count)
                    ->exists();
            }
        }

        // Fallback to generator honour definitions
        $honours = $this->creativeGenerator->getAllHonours();
        foreach ($honours as $honour) {
            if ((int) ($honour['required_count'] ?? 0) === $count) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store introduction creative for a successful introduction event if a milestone is reached.
     */
    public function handleIntroductionCreative(
        User $introducer,
        User $introducedUser,
        int $introducedCount,
        ?string $introductionRequestId = null
    ): ?IntroductionCreative {
        if (! Schema::hasTable('introduction_creatives')) {
            Log::warning('[IntroductionCreativeService] Table introduction_creatives does not exist.');

            return null;
        }

        // 1. Condition check: ONLY generate if current count matches a configured milestone's required_count
        if (! $this->isConfiguredMilestone($introducedCount)) {
            Log::info('[IntroductionCreativeService] Skipped: Count does not match any configured milestone required_count.', [
                'introducer_id' => $introducer->id,
                'requester_id' => $introducedUser->id,
                'introduced_count' => $introducedCount,
            ]);

            return null;
        }

        try {
            // 2. Duplicate / Idempotency protection: check if creative was already recorded for this introducer & count or introduction
            $existingCreative = IntroductionCreative::query()
                ->where('introducer_id', $introducer->id)
                ->where(function ($query) use ($introducedUser, $introducedCount, $introductionRequestId): void {
                    $query->where('requester_id', $introducedUser->id)
                        ->orWhere('introduced_count', $introducedCount);

                    if ($introductionRequestId !== null) {
                        $query->orWhere('introduction_request_id', $introductionRequestId);
                    }
                })
                ->first();

            if ($existingCreative && ! empty($existingCreative->image_url)) {
                Log::info('[IntroductionCreativeService] Reusing existing creative record.', [
                    'creative_id' => $existingCreative->id,
                    'introducer_id' => $introducer->id,
                    'requester_id' => $introducedUser->id,
                    'introduced_count' => $introducedCount,
                    'image_url' => $existingCreative->image_url,
                ]);

                return $existingCreative;
            }

            // 3. Generate personalized creative image and get its public HTTPS URL
            $imageUrl = $this->creativeGenerator->generateOrGetUrl($introducer, $introducedCount);

            // 4. Save to introduction_creatives
            $creative = IntroductionCreative::create([
                'id' => (string) Str::uuid(),
                'introduction_request_id' => $introductionRequestId,
                'introducer_id' => $introducer->id,
                'requester_id' => $introducedUser->id,
                'introduced_count' => $introducedCount,
                'image_url' => $imageUrl,
            ]);

            Log::info('[IntroductionCreativeService] Stored introduction creative successfully.', [
                'creative_id' => $creative->id,
                'introducer_id' => $introducer->id,
                'requester_id' => $introducedUser->id,
                'introduced_count' => $introducedCount,
                'image_url' => $imageUrl,
            ]);

            return $creative;
        } catch (Throwable $e) {
            Log::error('[IntroductionCreativeService] Failed to generate/store introduction creative: '.$e->getMessage(), [
                'introducer_id' => $introducer->id,
                'requester_id' => $introducedUser->id,
                'introduced_count' => $introducedCount,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
