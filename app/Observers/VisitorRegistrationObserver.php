<?php

namespace App\Observers;

use App\Models\VisitorRegistration;
use App\Services\LifeImpact\LifeImpactService;

class VisitorRegistrationObserver
{
    public function updated(VisitorRegistration $registration): void
    {
        if (! $registration->wasChanged('status')) {
            return;
        }

        $service = app(LifeImpactService::class);
        $status = strtolower((string) $registration->status);

        if (in_array($status, ['rejected', 'cancelled', 'inactive'], true)) {
            $service->removeImpactBySource((string) $registration->user_id, 'visitor_registration', (string) $registration->id);

            return;
        }

        $service->addImpact(
            (string) $registration->user_id,
            (string) $registration->user_id,
            'visitor_registration',
            (string) $registration->id,
            1,
            'Brought a quality visitor to the meeting',
            'Life impact restored for visitor registration activity.',
            [
                'event_type' => $registration->event_type,
                'event_name' => $registration->event_name,
                'event_date' => $registration->event_date,
                'visitor_full_name' => $registration->visitor_full_name,
                'visitor_mobile' => $registration->visitor_mobile,
                'visitor_email' => $registration->visitor_email,
                'visitor_city' => $registration->visitor_city,
                'visitor_business' => $registration->visitor_business,
                'how_known' => $registration->how_known,
                'note' => $registration->note,
                'status' => $registration->status,
            ],
        );
    }

    public function deleted(VisitorRegistration $registration): void
    {
        app(LifeImpactService::class)->removeImpactBySource(
            (string) $registration->user_id,
            'visitor_registration',
            (string) $registration->id,
        );
    }
}
