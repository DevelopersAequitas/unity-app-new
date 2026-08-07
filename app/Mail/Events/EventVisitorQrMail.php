<?php

declare(strict_types=1);

namespace App\Mail\Events;

use App\Models\EventRegistration;
use App\Services\Events\EventRegistrationQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

#[\AllowDynamicProperties]
class EventVisitorQrMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $visitorName;

    public string $eventTitle;

    public string $eventDate;

    public string $eventTime;

    public string $eventLocation;

    public ?string $qrCodeUrl;

    public string $digitalEntryPassUrl;

    public function __construct(
        public EventRegistration $registration
    ) {
        $event = $registration->event;
        $occurrence = $registration->occurrence;

        $this->visitorName = trim((string) ($registration->visitor_name ?: $registration->user?->display_name ?: 'Valued Visitor'));
        $this->eventTitle = (string) ($event?->title ?? 'Event Registration');

        $startAt = $occurrence?->start_at ?? $event?->start_at;
        $endAt = $occurrence?->end_at ?? $event?->end_at;

        if ($startAt) {
            $this->eventDate = $startAt->format('F d, Y (l)');
            $this->eventTime = $startAt->format('h:i A').($endAt ? ' - '.$endAt->format('h:i A') : '');
        } else {
            $this->eventDate = 'TBA';
            $this->eventTime = 'TBA';
        }

        $this->eventLocation = (string) ($event?->location ?? $event?->venue ?? 'As communicated by organizer');
        $this->qrCodeUrl = app(EventRegistrationQrService::class)->qrCodeUrl($registration);
        $this->digitalEntryPassUrl = 'https://peersglobal.com/';
    }

    public function build(): self
    {
        $mail = $this->subject('Your Event QR Code Entry Pass - '.$this->eventTitle)
            ->view('emails.events.visitor_qr');

        if (! empty($this->registration->qr_code_path) && Storage::disk('public')->exists($this->registration->qr_code_path)) {
            $fullPath = Storage::disk('public')->path($this->registration->qr_code_path);
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';

            $mail->attach($fullPath, [
                'as' => 'Event-QR-Code.'.$ext,
                'mime' => $mime,
            ]);
        }

        return $mail;
    }
}
