<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\File;
use App\Models\FileModel;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\Notifications\WearTheBadgeWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendWearTheBadgeWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Set to 1 to prevent endless retry loops.
     */
    public int $tries = 1;

    public function __construct(
        public string $userId
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to generate Welcome Creative / Wear The Badge image, store URL in SQL, and send WhatsApp message.
     */
    public function handle(WhatsappNotificationService $whatsappService, WearTheBadgeImageGenerator $imageGenerator): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('SendWearTheBadgeWhatsappJob skipped: User record not found.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $deterministicLogId = WearTheBadgeWhatsappService::getDeterministicLogId($this->userId, WearTheBadgeWhatsappService::TEMPLATE_KEY);

        // Atomically acquire execution claim in DB
        $canProceed = true;
        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                $canProceed = DB::transaction(function () use ($deterministicLogId): bool {
                    $log = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                    if ($log) {
                        if ($log->status === 'sent' || $log->status === 'processing') {
                            return false;
                        }
                        $log->status = 'processing';
                        $log->save();

                        return true;
                    }

                    if ($this->alreadySent($this->userId)) {
                        return false;
                    }

                    NotificationDeliveryLog::create([
                        'id' => $deterministicLogId,
                        'user_id' => $this->userId,
                        'channel' => 'whatsapp',
                        'provider' => WearTheBadgeWhatsappService::TEMPLATE_KEY,
                        'status' => 'processing',
                        'attempted_at' => now(),
                    ]);

                    return true;
                });
            } catch (Throwable $lockEx) {
                Log::warning('[SendWearTheBadgeWhatsappJob] Could not acquire execution lock: '.$lockEx->getMessage());
                if ($this->alreadySent($this->userId)) {
                    $canProceed = false;
                }
            }
        } else {
            if ($this->alreadySent($this->userId)) {
                $canProceed = false;
            }
        }

        if (! $canProceed) {
            Log::info('SendWearTheBadgeWhatsappJob skipped: Already sent or currently processing.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;
        $firstName = trim((string) ($user->first_name ?? $user->display_name ?? 'Friend'));

        // Generate creative image app-side & save URL in SQL automatically
        $creativeUrl = null;
        try {
            $creativeUrl = $imageGenerator->generateOrGetUrl($user);
        } catch (Throwable $e) {
            Log::error('SendWearTheBadgeWhatsappJob: Failed generating creative image: '.$e->getMessage(), [
                'user_id' => $this->userId,
            ]);
        }

        // Verify physical file existence before sending the media URL
        $physicalFileExists = false;
        if ($creativeUrl) {
            $uuid = null;
            if (preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $creativeUrl, $matches)) {
                $uuid = $matches[1];
            }
            if ($uuid) {
                $fileRecord = FileModel::find($uuid) ?? File::find($uuid);
                $disk = config('filesystems.default', 'public');
                if ($fileRecord && (Storage::disk($disk)->exists($fileRecord->s3_key) || Storage::disk('public')->exists($fileRecord->s3_key))) {
                    $physicalFileExists = true;
                } else {
                    Log::warning('SendWearTheBadgeWhatsappJob: Physical file missing, attempting to regenerate.', [
                        'user_id' => $this->userId,
                        'file_uuid' => $uuid,
                    ]);
                    try {
                        $creativeUrl = $imageGenerator->generateOrGetUrl($user, true);
                        $fileRecord = FileModel::find($uuid) ?? File::find($uuid);
                        if ($fileRecord && (Storage::disk($disk)->exists($fileRecord->s3_key) || Storage::disk('public')->exists($fileRecord->s3_key))) {
                            $physicalFileExists = true;
                        }
                    } catch (Throwable $e) {
                        Log::error('SendWearTheBadgeWhatsappJob: Failed regenerating missing physical file: '.$e->getMessage());
                    }
                }
            }
        }

        if (! $physicalFileExists) {
            Log::error('SendWearTheBadgeWhatsappJob skipped: Real creative physical file does not exist.', [
                'user_id' => $this->userId,
                'creative_url' => $creativeUrl,
            ]);

            $this->updateDeliveryLog($deterministicLogId, $this->userId, (string) ($rawPhone ?? ''), $firstName, 'failed', 'Real creative physical file does not exist.', $creativeUrl);

            return;
        }

        if (blank($rawPhone)) {
            Log::warning('SendWearTheBadgeWhatsappJob skipped: User phone number is empty.', [
                'user_id' => $this->userId,
            ]);

            $this->updateDeliveryLog($deterministicLogId, $this->userId, '', $firstName, 'failed', 'User phone number is empty.', $creativeUrl);

            return;
        }

        $payload = array_filter([
            'first_name' => $firstName,
            // Primary field FlexiMSG uses to populate WhatsApp image header
            'header_media_url' => $creativeUrl,
            // Aliases used by FlexiMSG template variable mapper
            'image' => $creativeUrl,
            'image_url' => $creativeUrl,
            'header_url' => $creativeUrl,
            'header_image_url' => $creativeUrl,
            'media_url' => $creativeUrl,
            'welcome_creative_url' => $creativeUrl,
        ]);

        try {
            $success = $whatsappService->send(WearTheBadgeWhatsappService::TEMPLATE_KEY, (string) $rawPhone, $payload);
            if ($success) {
                $this->updateDeliveryLog($deterministicLogId, $this->userId, (string) $rawPhone, $firstName, 'sent', null, $creativeUrl);
            } else {
                $this->updateDeliveryLog($deterministicLogId, $this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook response check failed or template inactive', $creativeUrl);

                // Fallback to welcome template if wear_the_badge template not active
                $whatsappService->send('welcome', (string) $rawPhone, $payload);
            }

            Log::info('SendWearTheBadgeWhatsappJob executed successfully.', [
                'user_id' => $this->userId,
                'phone' => $rawPhone,
                'welcome_creative_url' => $creativeUrl,
            ]);
        } catch (Throwable $exception) {
            Log::error('SendWearTheBadgeWhatsappJob threw exception: '.$exception->getMessage(), [
                'user_id' => $this->userId,
                'phone' => $rawPhone,
            ]);

            $this->updateDeliveryLog($deterministicLogId, $this->userId, (string) $rawPhone, $firstName, 'failed', $exception->getMessage(), $creativeUrl);
        }
    }

    private function alreadySent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        $deterministicLogId = WearTheBadgeWhatsappService::getDeterministicLogId($userId, WearTheBadgeWhatsappService::TEMPLATE_KEY);

        try {
            return NotificationDeliveryLog::query()
                ->where(function ($q) use ($userId, $deterministicLogId): void {
                    $q->where('id', $deterministicLogId)
                        ->orWhere(function ($sub) use ($userId): void {
                            $sub->where('user_id', $userId)
                                ->where('channel', 'whatsapp')
                                ->where('provider', WearTheBadgeWhatsappService::TEMPLATE_KEY);
                        });
                })
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function updateDeliveryLog(
        string $deterministicLogId,
        string $userId,
        string $phone,
        string $firstName,
        string $status,
        ?string $errorMessage,
        ?string $creativeUrl = null
    ): void {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        try {
            NotificationDeliveryLog::updateOrCreate(
                ['id' => $deterministicLogId],
                [
                    'user_id' => $userId,
                    'channel' => 'whatsapp',
                    'provider' => WearTheBadgeWhatsappService::TEMPLATE_KEY,
                    'status' => $status,
                    'request_payload' => array_filter([
                        'phone' => $phone,
                        'first_name' => $firstName,
                        'welcome_creative_url' => $creativeUrl,
                    ]),
                    'error_message' => $errorMessage,
                    'attempted_at' => now(),
                    'delivered_at' => $status === 'sent' ? now() : null,
                ]
            );
        } catch (Throwable) {
            // Logging failure should not interrupt job execution
        }
    }
}
