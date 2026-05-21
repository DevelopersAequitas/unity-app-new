<?php

namespace App\Services\Zoho;

use App\Models\EventRegistration;
use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ZohoBillingPaymentLinkService
{
    public function __construct(private readonly ZohoBillingClient $client) {}

    public function createPaymentLink(EventRegistration $registration): EventRegistration
    {
        $registration->loadMissing(['event', 'user']);

        if (($registration->payment_status ?? null) === 'pending' && ! empty($registration->zoho_payment_link_url)) {
            return $registration;
        }

        $email = (string) ($registration->user?->email ?: $registration->visitor_email ?: '');
        $phone = (string) ($registration->user?->phone ?: $registration->visitor_phone ?: '');
        $eventTitle = (string) ($registration->event?->title ?? 'Event');
        $amount = (float) ($registration->payment_amount ?? $registration->amount ?? 0);

        $payload = [
            'amount' => $amount,
            'currency' => 'INR',
            'customer_id' => $registration->zoho_customer_id,
            'email' => $email,
            'phone' => $phone,
            'reference_id' => (string) $registration->id,
            'description' => 'Event Registration - '.$eventTitle,
            'return_url' => rtrim((string) config('app.url'), '/').'/api/v1/events/registrations/'.$registration->id.'/payment-return',
            'notify_customer' => [
                'email' => true,
                'sms' => false,
            ],
        ];

        Log::info('zoho_billing_payment_link_create_request', [
            'registration_id' => (string) $registration->id,
            'payload' => $payload,
        ]);
        Log::info('Zoho Billing request path=/paymentlinks', ['registration_id' => (string) $registration->id]);

        try {
            $response = $this->client->request('POST', '/paymentlinks', $payload);
            $link = data_get($response, 'payment_links') ?? data_get($response, 'payment_link') ?? $response;
            $url = data_get($link, 'url');

            if (empty($url)) {
                Log::error('zoho_billing_payment_link_error', [
                    'registration_id' => (string) $registration->id,
                    'response' => $response,
                    'error' => 'Zoho Billing payment link URL missing.',
                ]);

                throw new \RuntimeException('Zoho Billing payment link URL missing.');
            }

            $registration->forceFill($this->filter([
                'zoho_payment_link_id' => data_get($link, 'payment_link_id') ?? data_get($link, 'id') ?? null,
                'zoho_payment_link_url' => $url,
                'payment_url' => $url,
                'checkout_url' => $url,
                'zoho_payment_status' => data_get($link, 'status', 'active'),
                'payment_gateway' => 'zoho_billing_payment_link',
                'payment_status' => 'pending',
                'status' => 'pending_payment',
            ]))->save();

            Log::info('zoho_billing_payment_link_created', [
                'registration_id' => (string) $registration->id,
                'zoho_payment_link_id' => $registration->zoho_payment_link_id,
            ]);
        } catch (\Throwable $e) {
            $registration->forceFill($this->filter([
                'zoho_invoice_sync_error' => $e->getMessage(),
                'payment_status' => 'pending',
                'status' => 'pending_payment',
            ]))->save();
        }

        return $registration->fresh(['event', 'occurrence', 'user']);
    }

    private function filter(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
