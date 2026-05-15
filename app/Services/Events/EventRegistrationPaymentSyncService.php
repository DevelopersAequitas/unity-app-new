<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EventRegistrationPaymentSyncService
{
    public function __construct(private readonly EventQrService $qr) {}

    public function syncFromZohoWebhook(array $payload): ?EventRegistration
    {
        $registration = $this->resolveRegistration($payload);
        if (! $registration) {
            return null;
        }

        $status = $this->resolvePaymentStatus($payload);
        $invoiceId = $this->firstValue($payload, ['invoice.invoice_id', 'data.invoice.invoice_id', 'invoice_id', 'data.invoice_id']);
        $invoiceNumber = $this->firstValue($payload, ['invoice.invoice_number', 'invoice.number', 'data.invoice.invoice_number', 'data.invoice.number', 'invoice_number']);

        return DB::transaction(function () use ($registration, $status, $invoiceId, $invoiceNumber, $payload): EventRegistration {
            $registration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $updates = [
                'payment_status' => $status,
                'zoho_invoice_id' => $invoiceId ?: $registration->zoho_invoice_id,
                'zoho_invoice_number' => $invoiceNumber ?: $registration->zoho_invoice_number,
                'metadata' => array_merge((array) ($registration->metadata ?? []), ['latest_zoho_payment_webhook' => $payload]),
            ];

            if ($status === 'paid') {
                $updates['status'] = 'registered';
                $updates['payment_completed_at'] = now();
            }

            if (in_array($status, ['failed', 'cancelled'], true)) {
                $updates['status'] = 'pending_payment';
            }

            $registration->forceFill($this->filterRegistrationColumns($updates))->save();

            if ($status === 'paid' && (empty($registration->qr_code_path) || empty($registration->qr_code_url))) {
                $this->qr->generateAndStore($registration);
            }

            Log::info('Event registration payment webhook synced.', [
                'event_registration_id' => (string) $registration->id,
                'payment_status' => $status,
            ]);

            return $registration->fresh(['event.circle', 'occurrence', 'user']);
        });
    }

    private function resolveRegistration(array $payload): ?EventRegistration
    {
        $registrationId = $this->extractRegistrationId($payload);
        if ($registrationId && Str::isUuid((string) $registrationId)) {
            $registration = EventRegistration::query()->find($registrationId);
            if ($registration) {
                return $registration;
            }
        }

        $hostedPageId = $this->firstValue($payload, ['hostedpage.hostedpage_id', 'hosted_page.hostedpage_id', 'hostedpage_id', 'hosted_page_id', 'data.hostedpage.hostedpage_id']);
        if ($hostedPageId && Schema::hasColumn('event_registrations', 'zoho_hosted_page_id')) {
            $registration = EventRegistration::query()->where('zoho_hosted_page_id', $hostedPageId)->first();
            if ($registration) {
                return $registration;
            }
        }

        $invoiceId = $this->firstValue($payload, ['invoice.invoice_id', 'data.invoice.invoice_id', 'invoice_id']);
        if ($invoiceId && Schema::hasColumn('event_registrations', 'zoho_invoice_id')) {
            return EventRegistration::query()->where('zoho_invoice_id', $invoiceId)->first();
        }

        return null;
    }

    private function extractRegistrationId(array $payload): ?string
    {
        $direct = $this->firstValue($payload, [
            'registration_id',
            'event_registration_id',
            'metadata.registration_id',
            'data.metadata.registration_id',
            'custom_fields.registration_id',
            'invoice.reference_number',
            'data.invoice.reference_number',
        ]);
        if ($direct) {
            return (string) $direct;
        }

        foreach (Arr::dot($payload) as $key => $value) {
            if (str_contains((string) $key, 'registration_id') && is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        foreach (Arr::dot($payload) as $value) {
            if (is_string($value) && preg_match('/event_registration[:=]([0-9a-fA-F-]{36})/', $value, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function resolvePaymentStatus(array $payload): string
    {
        $raw = strtolower((string) $this->firstValue($payload, [
            'payment.status', 'data.payment.status', 'invoice.status', 'data.invoice.status', 'status', 'event_type', 'event_type_formatted',
        ]));

        if (str_contains($raw, 'paid') || str_contains($raw, 'success') || str_contains($raw, 'payment_thankyou')) {
            return 'paid';
        }

        if (str_contains($raw, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($raw, 'fail') || str_contains($raw, 'declin') || str_contains($raw, 'void')) {
            return 'failed';
        }

        return 'pending';
    }

    private function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function filterRegistrationColumns(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
