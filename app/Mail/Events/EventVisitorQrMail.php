<?php

declare(strict_types=1);

namespace App\Mail\Events;

use App\Models\EventRegistration;
use App\Services\Events\EventRegistrationQrService;

#[\AllowDynamicProperties]
class EventVisitorQrMail extends \Illuminate\Mail\Mailable
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Queue\SerializesModels;

    public string $visitorName;

    public string $eventTitle;

    public string $eventDate;

    public string $eventTime;

    public string $eventLocation;

    public ?string $qrCodeUrl;

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
    }

    public function build(): self
    {
        $mail = $this->subject('Your Event QR Code Entry Pass - '.$this->eventTitle)
            ->view('emails.events.visitor_qr');

        if (! empty($this->registration->qr_code_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->registration->qr_code_path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($this->registration->qr_code_path);
            $mail->attach($fullPath, [
                'as' => 'Event-QR-Code.png',
                'mime' => 'image/png',
            ]);
        }

        return $mail;
    }
}
