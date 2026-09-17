<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneCatalystWhatsappJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

class MilestoneCatalystWhatsappService
{
    public const TEMPLATE_KEY = 'pgu_catalyst_3';

    public const MILESTONE_COUNT = 3;

    public const MILESTONE_KEY = 'CATALYST';

    /**
     * Generate deterministic UUID for milestone notification delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey = self::TEMPLATE_KEY, int $milestoneCount = self::MILESTONE_COUNT): string
    {
        return Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "notification_delivery.{$templateKey}.{$userId}.{$milestoneCount}")->toString();
    }

    /**
     * Trigger WhatsApp notification for CATALYST (3 member introductions) milestone.
     */
    public function handleCatalystMilestone(User $user, ?string $imageUrl = null): void
    {
        try {
            $user->refresh();

            $introducedCount = (int) ($user->members_introduced_count ?? 0);

            // Trigger only when count indicates this is the member's 3rd introduction (exact milestone)
            if ($introducedCount !== self::MILESTONE_COUNT) {
                Log::info('[MilestoneCatalystWhatsappService] Skipped: Referral count threshold not reached.', [
                    'user_id' => $user->id,
                    'members_introduced_count' => $introducedCount,
                    'required_threshold' => self::MILESTONE_COUNT,
                ]);

                return;
            }

            SendMilestoneCatalystWhatsappJob::dispatch((string) $user->id, $imageUrl);
        } catch (Throwable $e) {
            Log::error('[MilestoneCatalystWhatsappService] Exception in handleCatalystMilestone: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether pgu_catalyst_3 WhatsApp has already been sent to this user.
     */
    public function isMilestoneProcessed(string $userId): bool
    {
        return app(MilestoneWhatsappNotificationService::class)->alreadySent($userId, self::TEMPLATE_KEY);
    }

    /**
     * Check whether pgu_catalyst_3 WhatsApp has already been sent to this user.
     */
    public function alreadySent(string $userId): bool
    {
        return $this->isMilestoneProcessed($userId);
    }
}
