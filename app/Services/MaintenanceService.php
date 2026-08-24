<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppMaintenance;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use App\Services\Notifications\FcmService as AppFcmService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MaintenanceService
{
    public function __construct(
        private readonly AppFcmService $appFcmService,
        private readonly FirebaseFcmService $firebaseFcmService,
    ) {}

    /**
     * Get the current maintenance status data payload for API.
     *
     * @return array<string, mixed>
     */
    public function getCurrentMaintenanceStatus(): array
    {
        $maintenance = AppMaintenance::query()
            ->whereIn('status', ['scheduled', 'active'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $maintenance) {
            return $this->normalOperationResponse();
        }

        $now = Carbon::now();

        // If end_time has passed, mark as completed
        if ($maintenance->end_time && $now->greaterThanOrEqualTo($maintenance->end_time)) {
            $maintenance->update(['status' => 'completed']);

            return $this->normalOperationResponse();
        }

        // Auto-transition to active if start_time has arrived
        if ($maintenance->status === 'scheduled' && $maintenance->start_time && $now->greaterThanOrEqualTo($maintenance->start_time)) {
            $maintenance->status = 'active';
            $maintenance->save();

            if (! $maintenance->fcm_sent_at) {
                $this->sendMaintenanceStartPushNotification($maintenance);
            }
        }

        $status = $maintenance->status;

        if ($status !== 'scheduled' && $status !== 'active') {
            return $this->normalOperationResponse();
        }

        $durationMinutes = $maintenance->duration_minutes;
        if ($durationMinutes === null && $maintenance->start_time && $maintenance->end_time) {
            $durationMinutes = (int) $maintenance->start_time->diffInMinutes($maintenance->end_time);
        }

        $defaultTitle = $status === 'active'
            ? 'We’re under maintenance'
            : 'Scheduled Maintenance';

        $defaultMessage = $status === 'active'
            ? 'We’re making a few improvements to the platform. The app will be back shortly. Thanks for waiting with us ❤️'
            : 'Scheduled maintenance is coming up. We\'ll be upgrading our servers for faster performance.';

        $tz = config('database.connections.pgsql.timezone') ?? config('database.connections.mysql.timezone') ?? 'Asia/Kolkata';

        return [
            'status' => $status,
            'title' => $maintenance->title ?: $defaultTitle,
            'message' => $maintenance->message ?: $defaultMessage,
            'start_time' => $maintenance->start_time?->setTimezone($tz)->toIso8601String(),
            'end_time' => $maintenance->end_time?->setTimezone($tz)->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'support_email' => $maintenance->support_email ?: 'support@peersunity.com',
        ];
    }

    /**
     * Process automated transitions (Cron / Scheduler every minute).
     */
    public function processMaintenanceTransitions(): void
    {
        $now = Carbon::now();

        // 1. Transition scheduled -> active
        $scheduledMaintenances = AppMaintenance::query()
            ->where('status', 'scheduled')
            ->whereNotNull('start_time')
            ->where('start_time', '<=', $now)
            ->get();

        foreach ($scheduledMaintenances as $maintenance) {
            $maintenance->update(['status' => 'active']);

            if (! $maintenance->fcm_sent_at) {
                $this->sendMaintenanceStartPushNotification($maintenance);
            }
        }

        // 2. Transition active -> completed
        $activeMaintenances = AppMaintenance::query()
            ->where('status', 'active')
            ->whereNotNull('end_time')
            ->where('end_time', '<=', $now)
            ->get();

        foreach ($activeMaintenances as $maintenance) {
            $maintenance->update(['status' => 'completed']);
        }
    }

    /**
     * Send high priority FCM broadcast notification when maintenance starts.
     */
    public function sendMaintenanceStartPushNotification(AppMaintenance $maintenance): void
    {
        try {
            $tokens = UserPushToken::query()
                ->where('is_active', true)
                ->pluck('token')
                ->unique()
                ->filter()
                ->values();

            if ($tokens->isEmpty()) {
                $maintenance->update(['fcm_sent_at' => Carbon::now()]);

                return;
            }

            $title = 'We\'re temporarily under maintenance';
            $body = 'The app is temporarily under maintenance. We’ll be back shortly. Thanks for your patience ❤️';
            $data = [
                'type' => 'maintenance_start',
                'status' => 'active',
                'end_time' => $maintenance->end_time?->toIso8601String() ?? '',
            ];

            foreach ($tokens as $token) {
                try {
                    $this->appFcmService->sendToToken((string) $token, $title, $body, $data);
                } catch (Throwable $e) {
                    Log::error("Failed sending maintenance push to token: {$token}", ['error' => $e->getMessage()]);
                }
            }

            $maintenance->update(['fcm_sent_at' => Carbon::now()]);
        } catch (Throwable $exception) {
            Log::error('Error sending maintenance start FCM push notifications: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Return default normal operation response structure.
     *
     * @return array<string, mixed>
     */
    private function normalOperationResponse(): array
    {
        return [
            'status' => 'none',
            'title' => '',
            'message' => '',
            'start_time' => null,
            'end_time' => null,
            'duration_minutes' => null,
        ];
    }
}
