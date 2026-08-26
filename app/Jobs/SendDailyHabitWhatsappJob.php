<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notifications\DailyHabitSend;
use App\Services\Notifications\DailyHabitLoopService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDailyHabitWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $sendId
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(
        WhatsappNotificationService $whatsappService,
        DailyHabitLoopService $habitLoopService
    ): void {
        // Fast pre-flight check without locking
        $sendRecord = DailyHabitSend::find($this->sendId);

        if (! $sendRecord) {
            Log::warning('Daily Habit Loop Job skipped: send record not found.', [
                'send_id' => $this->sendId,
            ]);

            return;
        }

        if ($sendRecord->status === 'sent') {
            Log::info('Daily Habit Loop Job skipped: already sent.', [
                'send_id' => $this->sendId,
                'day_number' => $sendRecord->day_number,
            ]);

            return;
        }

        // Double-send protection: check if there's already a successful sent record for this day number and user
        $alreadySent = DailyHabitSend::where('user_id', $sendRecord->user_id)
            ->where('day_number', $sendRecord->day_number)
            ->where('status', 'sent')
            ->where('id', '!=', $sendRecord->id)
            ->exists();

        if ($alreadySent) {
            Log::info("Daily Habit Loop Job skipped: Day {$sendRecord->day_number} already sent in another record.", [
                'user_id' => $sendRecord->user_id,
                'send_id' => $this->sendId,
            ]);

            return;
        }

        $user = $sendRecord->user;
        if (! $user) {
            $this->markAsFailed($sendRecord, 'User record not found in database.');

            return;
        }

        if (in_array(strtolower((string) $user->status), ['deleted', 'blocked', 'suspended', 'inactive'], true)) {
            $this->markAsFailed($sendRecord, "User status is restricted: {$user->status}");

            return;
        }

        $rawPhone = $user->phone ?? $user->secondary_mobile;
        if (blank($rawPhone)) {
            $this->markAsFailed($sendRecord, 'User phone number is empty.');

            return;
        }

        $normalizedPhone = WhatsappNotificationService::normalizePhone((string) $rawPhone);
        if ($normalizedPhone === '') {
            $this->markAsFailed($sendRecord, 'User phone number normalization failed.');

            return;
        }

        // Dynamically resolve template from database for the day
        $template = $habitLoopService->resolveTemplateForDay($sendRecord->day_number);
        if (! $template) {
            $this->markAsFailed($sendRecord, "Active template not found for day {$sendRecord->day_number}.");

            return;
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $firstName = trim((string) ($user->display_name ?? ''));
        }
        if ($firstName === '') {
            $firstName = 'Friend';
        }

        // Build generic dynamic variables payload
        $payload = [
            'FirstName' => $firstName,
            'first_name' => $firstName,
            'phone' => $normalizedPhone,
            'mobile' => $normalizedPhone,
            'day_number' => (string) $sendRecord->day_number,
        ];

        // Day 2 specific dynamic variables
        if ($sendRecord->day_number === 2) {
            $payload['TimelineLink'] = rtrim((string) config('app.url'), '/').'/timeline';
            $payload['timeline_link'] = $payload['TimelineLink'];
        }

        $success = false;
        $errorMessage = null;

        try {
            $success = $whatsappService->send($template->template_key, (string) $rawPhone, $payload);
            if (! $success) {
                $errorMessage = WhatsappNotificationService::$lastError ?: 'FlexiMsg notification service failed to send.';
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        // Update status using a lock to prevent concurrent success races
        $shouldScheduleNext = false;
        DB::transaction(function () use ($sendRecord, $success, $errorMessage, &$shouldScheduleNext): void {
            $lockedRecord = DailyHabitSend::where('id', $sendRecord->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRecord || $lockedRecord->status === 'sent') {
                return;
            }

            if ($success) {
                $lockedRecord->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
                $shouldScheduleNext = true;
            } else {
                $lockedRecord->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }
        });

        if ($shouldScheduleNext) {
            try {
                $habitLoopService->scheduleNextDay($user, $sendRecord->day_number, now());
            } catch (Throwable $e) {
                Log::error('Daily Habit Loop next day scheduling failed.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $success) {
            throw new \Exception("Daily Habit Loop Send failed: {$errorMessage}");
        }
    }

    /**
     * Mark the send record as permanently failed (due to validation or configuration).
     */
    private function markAsFailed(DailyHabitSend $sendRecord, string $reason): void
    {
        $sendRecord->update([
            'status' => 'failed',
            'error_message' => $reason,
        ]);

        Log::warning("Daily Habit Loop Job failed permanently: {$reason}", [
            'send_id' => $sendRecord->id,
            'user_id' => $sendRecord->user_id,
        ]);
    }
}
