<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneConnectorWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MilestoneConnectorWhatsappService
{
    public const TEMPLATE_KEY = 'milestone_connector';

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

            // Check duplicate/idempotency before dispatching
            if ($this->alreadySent($user->id)) {
                Log::info('[MilestoneConnectorWhatsappService] Skipped: Already sent for this member.', [
                    'user_id' => $user->id,
                ]);

                return;
            }

            // Dispatch job with afterResponse() to ensure execution immediately after response flush
            SendMilestoneConnectorWhatsappJob::dispatch((string) $user->id, $imageUrl)->afterResponse();

            Log::info('[MilestoneConnectorWhatsappService] Dispatched SendMilestoneConnectorWhatsappJob.', [
                'user_id' => $user->id,
            ]);
        } catch (Throwable $e) {
            // Main flow must never fail because of WhatsApp handling
            Log::error('[MilestoneConnectorWhatsappService] Exception in handleFirstIntroduction: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether milestone_connector WhatsApp has already been sent to this user.
     */
    public function alreadySent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', self::TEMPLATE_KEY)
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
