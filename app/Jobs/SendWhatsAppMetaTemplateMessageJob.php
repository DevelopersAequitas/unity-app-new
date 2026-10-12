<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\MetaWhatsAppException;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMetaTemplateMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  list<array<string, mixed>>  $components
     */
    public function __construct(
        public readonly string $recipientPhone,
        public readonly string $templateName,
        public readonly string $languageCode = 'en_US',
        public readonly array $components = [],
        public readonly ?string $userId = null
    ) {}

    public function handle(MetaWhatsAppService $service): void
    {
        Log::info('[SendWhatsAppMetaTemplateMessageJob] Processing queued WhatsApp send.', [
            'recipient' => $this->recipientPhone,
            'template' => $this->templateName,
            'attempt' => $this->attempts(),
        ]);

        try {
            $service->sendTemplateMessage(
                $this->recipientPhone,
                $this->templateName,
                $this->languageCode,
                $this->components,
                $this->userId
            );
        } catch (MetaWhatsAppException $e) {
            $code = $e->getMetaErrorCode();

            // Permanent errors: do not retry
            $permanentCodes = [100, 190, 131026, 131045, 131051, 132000, 132001, 133010];
            if (in_array($code, $permanentCodes, true)) {
                Log::error('[SendWhatsAppMetaTemplateMessageJob] Permanent error encountered. Aborting retries.', [
                    'meta_error_code' => $code,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            // Transient error: release back to queue if attempts remaining
            if ($this->attempts() < $this->tries) {
                Log::warning('[SendWhatsAppMetaTemplateMessageJob] Transient error, releasing for retry.', [
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);
                $this->release($this->backoff * $this->attempts());

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            Log::error('[SendWhatsAppMetaTemplateMessageJob] Uncaught error: '.$e->getMessage());
            throw $e;
        }
    }
}
