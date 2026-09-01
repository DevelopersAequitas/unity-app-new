<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\File;
use App\Models\FileModel;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        // Duplicate protection check
        if ($this->alreadySent($this->userId)) {
            Log::info('SendWearTheBadgeWhatsappJob skipped: Already sent to user.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

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

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;

        if (blank($rawPhone)) {
            Log::warning('SendWearTheBadgeWhatsappJob skipped: User phone number is empty.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $firstName = trim((string) ($user->first_name ?? $user->display_name ?? 'Friend'));

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
            $success = $whatsappService->send('wear_the_badge', (string) $rawPhone, $payload);
            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'sent', null);
            } else {
                $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', 'Webhook response check failed or template inactive');

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

            $this->logDelivery($this->userId, (string) $rawPhone, $firstName, 'failed', $exception->getMessage());
        }
    }

    private function alreadySent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', 'wear_the_badge')
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function logDelivery(string $userId, string $phone, string $firstName, string $status, ?string $errorMessage): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        try {
            NotificationDeliveryLog::create([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => 'wear_the_badge',
                'status' => $status,
                'request_payload' => [
                    'phone' => $phone,
                    'first_name' => $firstName,
                ],
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable) {
            // Logging failure should not interrupt job execution
        }
    }
}
