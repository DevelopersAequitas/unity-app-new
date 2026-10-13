<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\MetaWhatsAppException;
use App\Models\WhatsAppBulkCampaignRecipient;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppBulkRecipientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $recipientId
    ) {}

    public function handle(MetaWhatsAppService $metaWhatsAppService): void
    {
        @set_time_limit(120);

        $recipient = WhatsAppBulkCampaignRecipient::with('campaign')->find($this->recipientId);

        if (! $recipient || ! $recipient->campaign) {
            Log::error("[SendWhatsAppBulkRecipientJob] Recipient {$this->recipientId} or campaign not found.");

            return;
        }

        $campaign = $recipient->campaign;

        // Check if campaign was cancelled
        if ($campaign->status === 'cancelled') {
            $recipient->update(['status' => 'cancelled']);

            return;
        }

        // Check idempotency: if already sent with whatsapp_message_id, avoid duplicate send
        if (! empty($recipient->whatsapp_message_id) && in_array($recipient->status, ['sent', 'delivered', 'read'], true)) {
            Log::info("[SendWhatsAppBulkRecipientJob] Recipient {$recipient->id} already sent. Skipping.");

            return;
        }

        // Claim atomically
        $claimed = WhatsAppBulkCampaignRecipient::where('id', $recipient->id)
            ->whereIn('status', ['pending', 'queued'])
            ->update([
                'status' => 'processing',
                'attempt_count' => $recipient->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);

        if (! $claimed) {
            // Already claimed or in a non-eligible status
            return;
        }

        $recipient->refresh();

        $resolved = is_array($recipient->resolved_parameters) ? $recipient->resolved_parameters : [];
        $components = $resolved['components'] ?? [];

        // Check if pre-resolution had errors
        if (! empty($resolved['errors'])) {
            $errText = implode(' | ', $resolved['errors']);
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => 'PARAMETER_RESOLUTION_ERROR',
                'error_message' => $errText,
            ]);
            $campaign->increment('failed_count');

            return;
        }

        try {
            $templateName = $campaign->template_name_snapshot;
            $languageCode = $campaign->template_language ?? 'en_IN';

            $result = $metaWhatsAppService->sendTemplateMessage(
                recipientPhone: $recipient->phone,
                templateName: $templateName,
                languageCode: $languageCode,
                components: $components,
                userId: $campaign->created_by
            );

            $metaMsgId = $result['meta_message_id'] ?? null;

            $recipient->update([
                'status' => 'sent',
                'whatsapp_message_id' => $metaMsgId,
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $campaign->increment('sent_count');

            Log::info("[SendWhatsAppBulkRecipientJob] Successfully sent message to recipient {$recipient->id} ({$recipient->phone}).", [
                'campaign_id' => $campaign->id,
                'meta_message_id' => $metaMsgId,
            ]);
        } catch (MetaWhatsAppException $e) {
            Log::warning("[SendWhatsAppBulkRecipientJob] Meta error sending to {$recipient->phone}: {$e->getMessage()}", [
                'recipient_id' => $recipient->id,
                'error_code' => $e->getMetaErrorCode(),
                'error_type' => $e->getMetaErrorType(),
            ]);

            // Rate limit (130429, 429) or transient server error -> retry with backoff if attempts remain
            if ($e->getMetaErrorCode() === 130429 || $e->getCode() === 429) {
                if ($this->attempts() < $this->tries) {
                    $recipient->update(['status' => 'pending']);
                    $this->release(30 * $this->attempts());

                    return;
                }
            }

            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => (string) $e->getMetaErrorCode(),
                'error_message' => $e->getMessage(),
            ]);

            $campaign->increment('failed_count');
        } catch (Throwable $e) {
            Log::error("[SendWhatsAppBulkRecipientJob] Unexpected error sending to {$recipient->phone}: {$e->getMessage()}", [
                'recipient_id' => $recipient->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => 'UNEXPECTED_ERROR',
                'error_message' => Str::limit($e->getMessage(), 255),
            ]);

            $campaign->increment('failed_count');
        }
    }
}
