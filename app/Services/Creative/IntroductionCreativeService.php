<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\IntroductionCreative;
use App\Models\MilestoneBadge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
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
     * Store introduction creative for a successful introduction event.
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

        $deterministicId = Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "intro_creative.{$introducer->id}.{$introducedUser->id}")->toString();

        try {
            // 1. Duplicate / Idempotency protection: check if creative was already recorded for this specific introducer & introduced peer
            $existingCreative = IntroductionCreative::query()
                ->where('introducer_id', $introducer->id)
                ->where(function ($query) use ($introducedUser, $introductionRequestId, $deterministicId): void {
                    $query->where('id', $deterministicId)
                        ->orWhere('requester_id', $introducedUser->id);

                    if ($introductionRequestId !== null) {
                        $query->orWhere('introduction_request_id', $introductionRequestId);
                    }
                })
                ->first();

            if ($existingCreative && ! empty($existingCreative->image_url)) {
                // Ensure physical file exists on disk; if missing, regenerate it
                $s3Key = preg_replace('~^https?://[^/]+/storage/~i', '', (string) $existingCreative->image_url);
                $fileExists = Storage::disk('public')->exists($s3Key)
                    || file_exists(storage_path('app/public/'.$s3Key))
                    || file_exists(public_path('storage/'.$s3Key));

                if (! $fileExists) {
                    try {
                        $newUrl = $this->creativeGenerator->generateOrGetUrl($introducer, $introducedCount);
                        $existingCreative->update(['image_url' => $newUrl]);
                        $existingCreative->image_url = $newUrl;
                    } catch (Throwable $regenEx) {
                        Log::warning('[IntroductionCreativeService] Could not regenerate missing physical creative: '.$regenEx->getMessage());
                    }
                }

                Log::info('[IntroductionCreativeService] Reusing existing creative record.', [
                    'creative_id' => $existingCreative->id,
                    'introducer_id' => $introducer->id,
                    'requester_id' => $introducedUser->id,
                    'introduced_count' => $existingCreative->introduced_count,
                    'image_url' => $existingCreative->image_url,
                ]);

                return $existingCreative;
            }

            // 2. Generate personalized creative image and get its public HTTPS URL
            $imageUrl = $this->creativeGenerator->generateOrGetUrl($introducer, $introducedCount);

            // 3. Atomically create or fetch inside DB transaction
            return DB::transaction(function () use ($deterministicId, $introducer, $introducedUser, $introducedCount, $introductionRequestId, $imageUrl): IntroductionCreative {
                $lockedCreative = IntroductionCreative::where('id', $deterministicId)
                    ->orWhere(function ($q) use ($introducer, $introducedUser): void {
                        $q->where('introducer_id', $introducer->id)
                            ->where('requester_id', $introducedUser->id);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($lockedCreative) {
                    return $lockedCreative;
                }

                $creative = IntroductionCreative::create([
                    'id' => $deterministicId,
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
            });
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
