<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\EventRegistration;
use App\Services\Notifications\EventRegistrationWhatsappService;

class EventRegistrationObserver
{
    public function __construct(
        private readonly EventRegistrationWhatsappService $whatsappService
    ) {}

    /**
     * Handle the EventRegistration "created" event.
     */
    public function created(EventRegistration $registration): void
    {
        if ($registration->status === 'registered') {
            $this->whatsappService->sendNotification($registration);
        }
    }

    /**
     * Handle the EventRegistration "updated" event.
     */
    public function updated(EventRegistration $registration): void
    {
        if ($registration->status === 'registered' && $registration->wasChanged('status')) {
            $this->whatsappService->sendNotification($registration);
        }
    }
}
