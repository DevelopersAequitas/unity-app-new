<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

        // Generate creative image app-side & save URL in SQL automatically
        $creativeUrl = null;
        try {
            $creativeUrl = $imageGenerator->generateOrGetUrl($user);
        } catch (Throwable $e) {
            Log::error('SendWearTheBadgeWhatsappJob: Failed generating creative image: '.$e->getMessage(), [
                'user_id' => $this->userId,
            ]);
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
            'welcome_creative_url' => $creativeUrl,
            'header_image_url' => $creativeUrl,
            'media_url' => $creativeUrl,
        ]);

        try {
            $success = $whatsappService->send('wear_the_badge', (string) $rawPhone, $payload);
            if (! $success) {
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
        }
    }
}
