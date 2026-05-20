<?php

namespace App\Services;

use App\Models\EventRegistration;
use App\Services\Events\EventQrService;
use App\Services\Zoho\ZohoBooksService;
use App\Services\Zoho\ZohoPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventPaymentService
{
    public function __construct(
        private readonly ZohoPaymentService $zohoPayments,
        private readonly ZohoBooksService $zohoBooks,
        private readonly EventQrService $qr,
    ) {}

    public function startZohoPayment(EventRegistration $registration): array
    {
        $result = $this->zohoPayments->createPaymentForEventRegistration($registration);
        $registration->forceFill([
            'payment_gateway' => 'zoho',
            'payment_url' => $result['payment_url'] ?? null,
            'zoho_payment_link_id' => $result['payment_link_id'] ?? null,
            'zoho_payment_session_id' => $result['session_id'] ?? null,
        ])->save();
        Log::info('Zoho payment created', ['registration_id' => $registration->id]);

        return $result;
    }

    public function markZohoPaymentPaid(EventRegistration $registration, array $payload): EventRegistration
    {
        return DB::transaction(function () use ($registration, $payload) {
            $registration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ($registration->payment_status === 'paid') {
                return $registration;
            }

            $registration->forceFill([
                'payment_status' => 'paid',
                'status' => 'registered',
                'payment_completed_at' => now(),
                'webhook_payload' => $payload,
                'zoho_payment_id' => data_get($payload, 'data.payment.payment_id') ?? data_get($payload, 'payment_id'),
            ])->save();

            if (! $registration->qr_generated_at) {
                $qr = $this->qr->generateAndStore($registration);
                $registration->forceFill(['qr_generated_at' => now(), 'qr_code_path' => $qr['path'] ?? $registration->qr_code_path, 'qr_code_url' => $qr['url'] ?? $registration->qr_code_url])->save();
                Log::info('QR generated', ['registration_id' => $registration->id]);
            }

            $this->zohoBooks->createOrUpdateCustomer($registration);
            $this->zohoBooks->createPaidInvoiceForEventRegistration($registration);
            Log::info('payment marked paid', ['registration_id' => $registration->id]);

            return $registration->fresh();
        });
    }

    public function markZohoPaymentFailed(EventRegistration $registration, array $payload): EventRegistration
    {
        $registration->forceFill([
            'payment_status' => 'failed',
            'payment_failed_reason' => data_get($payload, 'failure_reason') ?? data_get($payload, 'data.payment.failure_reason'),
            'webhook_payload' => $payload,
        ])->save();
        Log::info('payment failed', ['registration_id' => $registration->id]);

        return $registration;
    }
}
