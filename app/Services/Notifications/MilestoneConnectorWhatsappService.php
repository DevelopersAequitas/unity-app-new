<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

class MilestoneConnectorWhatsappService
{
    public const TEMPLATE_KEY = 'milestone_connector';

    /**
     * Generate deterministic UUID for milestone notification delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey = self::TEMPLATE_KEY, int $milestoneCount = 1): string
    {
        return Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "notification_delivery.{$templateKey}.{$userId}.{$milestoneCount}")->toString();
    }

    /**
     * Trigger WhatsApp notification for first member introduction milestone.
     */
    public function handleFirstIntroduction(User $user, ?string $imageUrl = null): void
    {
        try {
            $user->refresh();

            $introducedCount = (int) ($user->members_introduced_count ?? 0);

            // Trigger only when count indicates this is the member's first introduction
            if ($introducedCount !== 1) {
                Log::info('[MilestoneConnectorWhatsappService] Skipped: Not the first introduction.', [
                    'user_id' => $user->id,
                    'members_introduced_count' => $introducedCount,
                ]);

                return;
            }

            app(MilestoneWhatsappNotificationService::class)->handleMilestoneNotification($user, 1, $imageUrl);
        } catch (Throwable $e) {
            Log::error('[MilestoneConnectorWhatsappService] Exception in handleFirstIntroduction: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether milestone_connector WhatsApp has already been successfully sent to this user.
     */
    public function isMilestoneProcessed(string $userId): bool
    {
        return app(MilestoneWhatsappNotificationService::class)->alreadySent($userId, self::TEMPLATE_KEY);
    }

    /**
     * Check whether milestone_connector WhatsApp has already been sent to this user.
     */
    public function alreadySent(string $userId): bool
    {
        return $this->isMilestoneProcessed($userId);
    }
}
