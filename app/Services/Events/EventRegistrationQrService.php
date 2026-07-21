<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Mail\Events\EventVisitorQrMail;
use App\Models\EventRegistration;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventRegistrationQrService
{
    public function __construct(private readonly EventQrService $qr) {}

    public function ensureQrGenerated(EventRegistration $registration): EventRegistration
    {
        $registration = $registration->fresh() ?? $registration;

        if (! $this->canGenerate($registration)) {
            Log::info('event_registration_qr_generation_skipped_until_payment_complete', [
                'registration_id' => (string) $registration->id,
                'payment_required' => (bool) ($registration->payment_required ?? false),
                'payment_status' => $registration->payment_status,
            ]);

            return $registration;
        }

        $wasQrMissing = ! $this->hasUsableQr($registration);

        if (! $wasQrMissing) {
            $updates = [];
            if (! empty($registration->qr_code_path) && empty($registration->qr_code_url)) {
                $updates['qr_code_url'] = $this->qr->url($registration->qr_code_path);
            }
            if (empty($registration->qr_generated_at)) {
                $updates['qr_generated_at'] = now();
            }
            if (! empty($updates)) {
                $registration->forceFill($this->filter($updates))->save();
                $registration->refresh();
            }

            Log::info('event_registration_qr_already_exists', [
                'registration_id' => (string) $registration->id,
                'qr_code_path' => $registration->qr_code_path,
                'has_qr_code_url' => ! empty($registration->qr_code_url),
                'has_qr_code_svg' => ! empty($registration->qr_code_svg),
            ]);
        } else {
            Log::info('event_registration_qr_generation_start', [
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
            ]);

            try {
                if (empty($registration->qr_token)) {
                    $registration->forceFill($this->filter(['qr_token' => $this->uniqueToken()]))->save();
                    $registration->refresh();
                }

                $this->qr->generateAndStore($registration);
                $registration = $registration->fresh() ?? $registration;

                Log::info('event_registration_qr_generated_successfully', [
                    'registration_id' => (string) $registration->id,
                    'qr_code_path' => $registration->qr_code_path,
                    'qr_code_url' => $registration->qr_code_url,
                ]);
            } catch (Throwable $exception) {
                Log::error('event_registration_qr_generation_failed', [
                    'registration_id' => (string) $registration->id,
                    'event_id' => (string) $registration->event_id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $registration = $registration->fresh() ?? $registration;

        if ($wasQrMissing && $this->hasUsableQr($registration)) {
            $this->sendVisitorQrEmailSafely($registration);
        }

        return $registration;
    }

    public function sendVisitorQrEmailSafely(EventRegistration $registration): void
    {
        $registration = $registration->fresh(['event', 'occurrence', 'user']) ?? $registration;
        $recipientEmail = (string) ($registration->visitor_email ?: $registration->user?->email);

        if (blank($recipientEmail)) {
            Log::info('event_visitor_qr_email_skipped_no_recipient', [
                'registration_id' => (string) $registration->id,
            ]);

            return;
        }

        $recipientName = trim((string) ($registration->visitor_name ?: $registration->user?->display_name ?: 'Valued Visitor'));
        $mailable = new EventVisitorQrMail($registration);

        try {
            Mail::to($recipientEmail)->send($mailable);

            app(EmailLogService::class)->logMailableSent($mailable, [
                'user_id' => (string) ($registration->user_id ?: ''),
                'to_email' => $recipientEmail,
                'to_name' => $recipientName,
                'template_key' => 'event_visitor_qr',
                'source_module' => 'Events',
                'related_type' => EventRegistration::class,
                'related_id' => (string) $registration->id,
                'payload' => [
                    'registration_id' => (string) $registration->id,
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'qr_code_url' => $this->qrCodeUrl($registration),
                ],
            ]);

            Log::info('event_visitor_qr_email_sent_successfully', [
                'registration_id' => (string) $registration->id,
                'recipient_email' => $recipientEmail,
            ]);
        } catch (Throwable $exception) {
            app(EmailLogService::class)->logMailableFailed($mailable, [
                'user_id' => (string) ($registration->user_id ?: ''),
                'to_email' => $recipientEmail,
                'to_name' => $recipientName,
                'template_key' => 'event_visitor_qr',
                'source_module' => 'Events',
                'related_type' => EventRegistration::class,
                'related_id' => (string) $registration->id,
                'payload' => [
                    'registration_id' => (string) $registration->id,
                    'event_id' => (string) $registration->event_id,
                ],
            ], $exception);

            Log::error('event_visitor_qr_email_send_failed', [
                'registration_id' => (string) $registration->id,
                'recipient_email' => $recipientEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function qrCodeUrl(EventRegistration $registration): ?string
    {
        if (! empty($registration->qr_code_path)) {
            return $this->qr->url($registration->qr_code_path);
        }

        return $registration->qr_code_url ?: null;
    }

    private function canGenerate(EventRegistration $registration): bool
    {
        if (in_array(strtolower((string) ($registration->status ?? '')), ['cancelled', 'canceled', 'payment_failed'], true)) {
            return false;
        }

        if (! (bool) ($registration->payment_required ?? false)) {
            return true;
        }

        return in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);
    }

    private function hasUsableQr(EventRegistration $registration): bool
    {
        return ! empty($registration->qr_token)
            && (! empty($registration->qr_code_url) || ! empty($registration->qr_code_svg) || ! empty($registration->qr_code_path));
    }

    private function uniqueToken(): string
    {
        do {
            $token = $this->qr->generateToken();
        } while (EventRegistration::query()->where('qr_token', $token)->exists());

        return $token;
    }

    private function filter(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
