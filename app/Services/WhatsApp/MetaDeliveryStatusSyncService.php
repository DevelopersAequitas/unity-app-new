<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\MemberAwardSend;
use App\Models\MemberJourneySend;
use App\Models\WhatsappMessage;
use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaDeliveryStatusSyncService
{
    /**
     * Cross-reference and sync delivery statuses for MemberAwardSend.
     */
    public function syncAwardDeliveries(?string $userId = null): int
    {
        $updated = 0;

        try {
            $query = MemberAwardSend::query()
                ->whereIn('status', ['sent', 'scheduled'])
                ->whereNotNull('provider_message_id')
                ->where('provider_message_id', '!=', '');

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $sends = $query->limit(200)->get();

            if ($sends->isEmpty()) {
                return 0;
            }

            $wamids = $sends->pluck('provider_message_id')->unique()->filter()->values()->all();

            $metaMessages = WhatsappMessage::whereIn('meta_message_id', $wamids)->get()->keyBy('meta_message_id');
            $messageLogs = WhatsAppMessageLog::whereIn('provider_message_id', $wamids)->get()->keyBy('provider_message_id');

            foreach ($sends as $send) {
                $wamid = $send->provider_message_id;
                $metaMsg = $metaMessages->get($wamid);
                $logMsg = $messageLogs->get($wamid);

                $resolvedStatus = null;
                $deliveredAt = null;
                $readAt = null;
                $errorMessage = null;

                if ($metaMsg) {
                    $raw = strtolower((string) $metaMsg->status);
                    if (in_array($raw, ['delivered', 'read', 'failed'])) {
                        $resolvedStatus = $raw;
                        $deliveredAt = $metaMsg->delivered_at;
                        $readAt = $metaMsg->read_at;
                        $errorMessage = $metaMsg->error_message;
                    }
                } elseif ($logMsg) {
                    $raw = strtolower((string) $logMsg->status);
                    if (in_array($raw, ['delivered', 'read', 'failed'])) {
                        $resolvedStatus = $raw;
                        $errorMessage = $logMsg->error_message;
                    }
                }

                if ($resolvedStatus && $resolvedStatus !== $send->status) {
                    $send->status = $resolvedStatus;
                    if ($deliveredAt) {
                        $send->delivered_at = $deliveredAt;
                    } elseif ($resolvedStatus === 'delivered' || $resolvedStatus === 'read') {
                        $send->delivered_at = $send->delivered_at ?? now();
                    }
                    if ($readAt) {
                        $send->read_at = $readAt;
                    } elseif ($resolvedStatus === 'read') {
                        $send->read_at = $send->read_at ?? now();
                    }
                    if ($errorMessage) {
                        $send->error_message = $errorMessage;
                    }
                    $send->save();
                    $updated++;
                }
            }
        } catch (Throwable $e) {
            Log::warning('[MetaDeliveryStatusSyncService] Error syncing award deliveries: ' . $e->getMessage());
        }

        return $updated;
    }

    /**
     * Cross-reference and sync delivery statuses for MemberJourneySend.
     */
    public function syncJourneyDeliveries(?string $userId = null): int
    {
        $updated = 0;

        try {
            $query = MemberJourneySend::query()
                ->where('status', 'sent')
                ->whereNotNull('provider_message_id')
                ->where('provider_message_id', '!=', '');

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $sends = $query->limit(200)->get();

            if ($sends->isEmpty()) {
                return 0;
            }

            $wamids = $sends->pluck('provider_message_id')->unique()->filter()->values()->all();

            $metaMessages = WhatsappMessage::whereIn('meta_message_id', $wamids)->get()->keyBy('meta_message_id');
            $messageLogs = WhatsAppMessageLog::whereIn('provider_message_id', $wamids)->get()->keyBy('provider_message_id');

            foreach ($sends as $send) {
                $wamid = $send->provider_message_id;
                $metaMsg = $metaMessages->get($wamid);
                $logMsg = $messageLogs->get($wamid);

                $resolvedStatus = null;
                $errorMessage = null;

                if ($metaMsg) {
                    $raw = strtolower((string) $metaMsg->status);
                    if (in_array($raw, ['delivered', 'read', 'failed'])) {
                        $resolvedStatus = $raw;
                        $errorMessage = $metaMsg->error_message;
                    }
                } elseif ($logMsg) {
                    $raw = strtolower((string) $logMsg->status);
                    if (in_array($raw, ['delivered', 'read', 'failed'])) {
                        $resolvedStatus = $raw;
                        $errorMessage = $logMsg->error_message;
                    }
                }

                if ($resolvedStatus && $resolvedStatus !== $send->status) {
                    $send->status = $resolvedStatus;
                    if ($errorMessage) {
                        $send->error_message = $errorMessage;
                    }
                    $send->save();
                    $updated++;
                }
            }
        } catch (Throwable $e) {
            Log::warning('[MetaDeliveryStatusSyncService] Error syncing journey deliveries: ' . $e->getMessage());
        }

        return $updated;
    }

    /**
     * Sync both awards and journey deliveries.
     */
    public function syncAll(?string $userId = null): array
    {
        $awards = $this->syncAwardDeliveries($userId);
        $journey = $this->syncJourneyDeliveries($userId);

        return [
            'updated_awards' => $awards,
            'updated_journeys' => $journey,
            'total_updated' => $awards + $journey,
        ];
    }
}
