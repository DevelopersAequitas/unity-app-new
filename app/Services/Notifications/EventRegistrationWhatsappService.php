<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\EventRegistration;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventRegistrationWhatsappService
{
    public const TEMPLATE_KEY = 'event_registration';

    public function __construct(
        private readonly WhatsappNotificationService $whatsappService
    ) {}

    /**
     * Trigger WhatsApp notification for an event registration safely.
     */
    public function sendNotification(EventRegistration $registration): bool
    {
        try {
            $registration = $registration->fresh(['user', 'event', 'occurrence']) ?? $registration;

            if ($registration->status !== 'registered') {
                Log::info('Event registration WhatsApp skipped: Registration status is not registered.', [
                    'registration_id' => (string) $registration->id,
                    'status' => $registration->status,
                ]);

                return false;
            }

            if ($this->isAlreadySent($registration)) {
                Log::info('Event registration WhatsApp skipped: Already sent for this registration.', [
                    'registration_id' => (string) $registration->id,
                ]);

                return false;
            }

            $phoneData = $this->resolvePhone($registration);
            $phone = $phoneData['phone'];
            $phoneSource = $phoneData['source'];

            if ($phone === '') {
                Log::warning('Event registration WhatsApp skipped: Missing phone number.', [
                    'registration_id' => (string) $registration->id,
                    'user_id' => (string) ($registration->user_id ?? ''),
                    'attendee_type' => $registration->user_id ? 'member' : 'visitor',
                    'reason' => 'No phone number available on visitor_phone or linked user record',
                ]);

                $this->updateRegistrationWhatsappStatus($registration, 'failed');
                $this->logDelivery($registration, '', 'skipped', 'Missing phone number');

                return false;
            }

            $recipientName = trim((string) ($registration->visitor_name ?: $registration->user?->display_name ?: $registration->user?->first_name ?: 'Valued Guest'));
            if ($recipientName === '') {
                $recipientName = 'Valued Guest';
            }

            $payload = [
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
                'occurrence_id' => (string) ($registration->occurrence_id ?? ''),
                'user_id' => (string) ($registration->user_id ?? ''),
                'name' => $recipientName,
                'recipient_name' => $recipientName,
                'visitor_name' => (string) ($registration->visitor_name ?? ''),
                'visitor_phone' => (string) ($registration->visitor_phone ?? ''),
                'phone_source' => $phoneSource,
                'qr_code_url' => (string) ($registration->qr_code_url ?? ''),
            ];

            Log::info('Dispatching event registration WhatsApp notification.', [
                'registration_id' => (string) $registration->id,
                'phone' => $phone,
                'phone_source' => $phoneSource,
                'template_key' => self::TEMPLATE_KEY,
            ]);

            $success = $this->whatsappService->send(self::TEMPLATE_KEY, $phone, $payload);

            if ($success) {
                $now = now();
                $this->updateRegistrationWhatsappStatus($registration, 'sent', $now->toDateTimeString());
                $this->logDelivery($registration, $phone, 'sent', null, $payload);

                Log::info('Event registration WhatsApp notification delivered successfully.', [
                    'registration_id' => (string) $registration->id,
                    'phone' => $phone,
                ]);

                return true;
            }

            $this->updateRegistrationWhatsappStatus($registration, 'failed');
            $this->logDelivery($registration, $phone, 'failed', 'WhatsApp service send check returned false or template inactive', $payload);

            Log::error('Event registration WhatsApp notification failed to send.', [
                'registration_id' => (string) $registration->id,
                'phone' => $phone,
            ]);

            return false;
        } catch (Throwable $exception) {
            $this->updateRegistrationWhatsappStatus($registration, 'failed');
            Log::error('Event registration WhatsApp notification threw an exception.', [
                'registration_id' => (string) ($registration->id ?? ''),
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);

            return false;
        }
    }

    /**
     * Resolve phone number following priority rules:
     * Priority 1 (Case 1 & 3): visitor_phone if available and non-empty.
     * Priority 2 (Case 2): user->phone if user_id exists.
     */
    public function resolvePhone(EventRegistration $registration): array
    {
        $visitorPhone = trim((string) ($registration->visitor_phone ?? ''));
        if ($visitorPhone !== '') {
            return [
                'phone' => $visitorPhone,
                'source' => 'visitor_phone',
            ];
        }

        if (! empty($registration->user_id)) {
            $user = $registration->user ?? User::query()->find($registration->user_id);
            $userPhone = trim((string) ($user?->phone ?? ''));
            if ($userPhone !== '') {
                return [
                    'phone' => $userPhone,
                    'source' => 'user_phone',
                ];
            }
        }

        return [
            'phone' => '',
            'source' => 'none',
        ];
    }

    /**
     * Check if WhatsApp message was already sent for this registration.
     */
    public function isAlreadySent(EventRegistration $registration): bool
    {
        if (($registration->whatsapp_status ?? null) === 'sent') {
            return true;
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('channel', 'whatsapp')
                ->where('provider', self::TEMPLATE_KEY)
                ->where('status', 'sent')
                ->where(function ($query) use ($registration): void {
                    $query->where('request_payload->registration_id', (string) $registration->id)
                        ->orWhere('provider_message_id', (string) $registration->id);
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Update whatsapp_status and whatsapp_sent_at on event_registrations table.
     */
    private function updateRegistrationWhatsappStatus(EventRegistration $registration, string $status, ?string $sentAt = null): void
    {
        try {
            $updates = [];
            if (Schema::hasColumn('event_registrations', 'whatsapp_status')) {
                $updates['whatsapp_status'] = $status;
            }
            if ($sentAt !== null && Schema::hasColumn('event_registrations', 'whatsapp_sent_at')) {
                $updates['whatsapp_sent_at'] = $sentAt;
            }
            if (! empty($updates)) {
                $registration->forceFill($updates)->save();
            }
        } catch (Throwable $e) {
            Log::error('Failed to update event_registration whatsapp_status.', [
                'registration_id' => (string) $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log delivery attempt in notification_delivery_logs table.
     */
    private function logDelivery(EventRegistration $registration, string $phone, string $status, ?string $errorMessage = null, array $payload = []): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        try {
            NotificationDeliveryLog::query()->create([
                'user_id' => (string) ($registration->user_id ?: null),
                'channel' => 'whatsapp',
                'provider' => self::TEMPLATE_KEY,
                'provider_message_id' => (string) $registration->id,
                'status' => $status,
                'request_payload' => array_merge([
                    'registration_id' => (string) $registration->id,
                    'template_key' => self::TEMPLATE_KEY,
                    'phone' => $phone,
                ], $payload),
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to log WhatsApp notification delivery in NotificationDeliveryLog.', [
                'registration_id' => (string) $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
