<?php

namespace App\Services\Zoho;

use App\Models\EventRegistration;
use App\Models\WebhookEvent;
use App\Services\Events\EventPaymentSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ZohoPaymentWebhookService
{
    public function __construct(private readonly EventPaymentSyncService $paymentSync) {}

    public function handle(Request $request): array
    {
        $payload = $request->all();
        $normalized = $this->normalizeZohoPaymentWebhookPayload($payload);
        $normalized['external_event_id'] = $normalized['external_event_id'] ?: $request->header('X-Zoho-Webhook-Id');
        $event = null;

        Log::info('zoho_payment_webhook_received_raw', $this->context(null, $normalized));
        Log::info('zoho_payment_webhook_payload_normalized', $this->context(null, $normalized) + ['normalized' => $normalized]);

        try {
            $event = $this->storeEvent($request, $payload, $normalized);
            if (in_array($event->status, ['processed', 'ignored'], true) && $event->processed_at) {
                Log::info('zoho_payment_webhook_duplicate_ignored', $this->context($event, $normalized));
                return ['message' => 'Webhook already processed.', 'normalized' => $normalized, 'webhook_event_id' => $event->id];
            }

            $event->forceFill(['status' => 'processing', 'error' => null])->save();
            Log::info('zoho_payment_webhook_lookup_started', $this->context($event, $normalized));
            $registration = $this->findRegistration($payload, $normalized);
            if (! $registration) {
                $event->forceFill(['status' => 'ignored', 'processed_at' => now(), 'error' => 'Registration not found for payment webhook.'])->save();
                Log::warning('zoho_payment_webhook_registration_not_found', $this->context($event, $normalized));
                Log::warning('zoho_payment_webhook_ignored_registration_not_found', $this->context($event, $normalized));
                return ['message' => 'Webhook received but registration not found.', 'normalized' => $normalized, 'webhook_event_id' => $event->id, 'registration_found' => false];
            }

            $event->forceFill([
                'registration_id' => $registration->id,
                'payment_link_id' => $event->payment_link_id ?: ($normalized['payment_link_id'] ?: $registration->zoho_payment_link_id),
                'payment_id' => $normalized['payment_id'] ?: $event->payment_id,
            ])->save();
            $normalized['registration_id'] = (string) $registration->id;
            Log::info('zoho_payment_webhook_registration_found', $this->context($event, $normalized));
            Log::info('zoho_payment_webhook_registration_found_final', $this->context($event, $normalized));

            $status = strtolower((string) ($normalized['status'] ?? ''));
            $type = strtolower((string) ($normalized['event_type'] ?? ''));
            if (str_contains($type, 'cancel') || str_contains($type, 'expired') || in_array($status, ['cancelled', 'canceled', 'expired'], true)) {
                $this->markCancelledOrExpired($registration, $payload, str_contains($type.$status, 'expired') ? 'expired' : 'cancelled');
                $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
                Log::info('zoho_payment_webhook_cancelled_or_expired', $this->context($event, $normalized));
                Log::info('zoho_payment_webhook_processed', $this->context($event, $normalized));
                return ['message' => 'Webhook received.', 'normalized' => $normalized, 'webhook_event_id' => $event->id, 'registration_found' => true];
            }

            if ($this->isAlreadyFullySynced($registration)) {
                $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
                Log::info('zoho_payment_webhook_processed', $this->context($event, $normalized));
                return ['message' => 'Webhook already processed.', 'normalized' => $normalized, 'webhook_event_id' => $event->id, 'registration_found' => true, 'registration_id' => (string) $registration->id];
            }

            if ($this->isPaidWebhook($normalized)) {
                Log::info('zoho_payment_webhook_sync_started', $this->context($event, $normalized));
                $this->primePaidFields($registration, $payload, $normalized);
                $this->paymentSync->syncRegistrationPayment($registration->fresh(['event', 'occurrence', 'user']), [
                    'source' => 'zoho_webhook',
                    'webhook_event_id' => $event->id,
                    'payload' => $payload,
                    'payment_id' => $normalized['payment_id'] ?? null,
                ]);
                $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
                Log::info('zoho_payment_webhook_sync_success', $this->context($event, $normalized));
                Log::info('zoho_payment_webhook_processed', $this->context($event, $normalized));
            } else {
                $event->forceFill(['status' => 'ignored', 'processed_at' => now(), 'error' => 'Unsupported webhook event/status.'])->save();
            }

            return ['message' => 'Webhook received.', 'normalized' => $normalized, 'webhook_event_id' => $event->id, 'registration_found' => true, 'registration_id' => $normalized['registration_id'] ?? null];
        } catch (\Throwable $e) {
            if ($event instanceof WebhookEvent) {
                $event->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            } else {
                $event = $this->storeFailedEventSafely($request, $payload, $normalized, $e);
            }

            Log::error('zoho_payment_webhook_sync_failed', $this->context($event, $normalized) + [
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Log::error('zoho_payment_webhook_unhandled_exception', $this->context($event, $normalized) + [
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['message' => 'Webhook received but processing failed. It can be retried.', 'normalized' => $normalized, 'webhook_event_id' => $event?->id, 'error' => $e->getMessage()];
        }
    }

    public function verify(Request $request): bool
    {
        $secret = (string) env('ZOHO_PAYMENT_WEBHOOK_SECRET', '');
        $verifySignature = filter_var(env('ZOHO_PAYMENT_WEBHOOK_VERIFY_SIGNATURE', false), FILTER_VALIDATE_BOOL);
        if ($verifySignature) {
            $signature = $request->header('X-Zoho-Webhook-Signature') ?: $request->header('X-Zoho-Signature') ?: $request->header('X-Zoho-Payments-Signature');
            return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $signature);
        }
        if ($secret === '') {
            if (app()->environment('local')) {
                Log::warning('Zoho payment webhook secret is empty; allowing local webhook request.');
                return true;
            }
            return false;
        }
        return hash_equals($secret, (string) $request->query('secret', '')) || hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''));
    }

    public function processStored(WebhookEvent $event): void
    {
        $fake = Request::create('/internal', 'POST', [], [], [], [], json_encode($event->payload));
        $fake->headers->set('Content-Type', 'application/json');
        $this->handle($fake);
    }

    public function extract(array $payload): array
    {
        return $this->normalizeZohoPaymentWebhookPayload($payload);
    }

    public function normalizeZohoPaymentWebhookPayload(array $payload): array
    {
        $payment = (array) data_get($payload, 'payment', []);
        $dataPayment = (array) data_get($payload, 'data.payment', []);
        $description = $payment['description'] ?? data_get($payload, 'description') ?? data_get($payload, 'data.description') ?? ($dataPayment['description'] ?? null);
        $parsed = $this->parseDescriptionIdentifiers((string) $description);

        return [
            'event_type' => data_get($payload, 'event') ?? data_get($payload, 'event_type') ?? data_get($payload, 'type') ?? data_get($payload, 'event_name') ?? 'customer_payment',
            'external_event_id' => data_get($payload, 'event_id') ?? data_get($payload, 'id') ?? data_get($payload, 'webhook_id'),
            'payment_id' => $payment['payment_id'] ?? data_get($payload, 'payment_id') ?? data_get($payload, 'data.payment_id') ?? ($dataPayment['payment_id'] ?? null) ?? data_get($payload, 'payment.id') ?? data_get($payload, 'customer_payments.0.payment_id') ?? data_get($payload, 'payment_link.customer_payments.0.payment_id'),
            'payment_link_id' => $this->blankToNull($payment['payment_link_id'] ?? data_get($payload, 'payment_link.payment_link_id') ?? data_get($payload, 'payment_link_id') ?? data_get($payload, 'data.payment_link_id') ?? data_get($payload, 'data.payment_link.payment_link_id') ?? data_get($payload, 'payment_link.id') ?? ($parsed['payment_link_id'] ?? null)),
            'reference_number' => $payment['reference_number'] ?? data_get($payload, 'reference_number') ?? data_get($payload, 'data.reference_number') ?? ($dataPayment['reference_number'] ?? null),
            'online_transaction_id' => $payment['online_transaction_id'] ?? data_get($payload, 'online_transaction_id') ?? data_get($payload, 'data.online_transaction_id') ?? ($dataPayment['online_transaction_id'] ?? null),
            'description' => $description,
            'customer_id' => $payment['customer_id'] ?? data_get($payload, 'customer_id') ?? data_get($payload, 'data.customer_id') ?? ($dataPayment['customer_id'] ?? null),
            'amount' => $payment['amount'] ?? data_get($payload, 'amount') ?? data_get($payload, 'data.amount') ?? ($dataPayment['amount'] ?? null),
            'payment_date' => $payment['date'] ?? $payment['payment_date'] ?? data_get($payload, 'payment_date') ?? data_get($payload, 'date') ?? data_get($payload, 'data.date') ?? ($dataPayment['date'] ?? null),
            'status' => $payment['payment_status'] ?? $payment['status'] ?? data_get($payload, 'status') ?? data_get($payload, 'payment_link.status') ?? data_get($payload, 'data.status') ?? ($dataPayment['payment_status'] ?? null) ?? ($dataPayment['status'] ?? null),
            'parsed_registration_id' => $parsed['registration_id'] ?? null,
            'parsed_payment_link_id' => $parsed['payment_link_id'] ?? null,
            'parsed_original_payment_id' => $parsed['original_payment_id'] ?? null,
        ];
    }

    private function storeEvent(Request $request, array $payload, array $info): WebhookEvent
    {
        $query = WebhookEvent::query()->where('provider', 'zoho');
        if (! empty($info['external_event_id'])) {
            $query->where('external_event_id', $info['external_event_id']);
        } elseif (! empty($info['payment_link_id']) || ! empty($info['payment_id'])) {
            $query->where('event_type', $info['event_type'])->where('payment_link_id', $info['payment_link_id'])->where('payment_id', $info['payment_id']);
        } else {
            $query->whereRaw('1 = 0');
        }
        $existing = $query->first();
        if ($existing) return $existing;

        $event = WebhookEvent::query()->create([
            'provider' => 'zoho',
            'event_type' => $info['event_type'],
            'external_event_id' => $info['external_event_id'],
            'payment_link_id' => $info['payment_link_id'],
            'payment_id' => $info['payment_id'],
            'status' => 'received',
            'payload' => $payload,
            'headers' => $this->safeHeaders($request),
        ]);
        Log::info('zoho_payment_webhook_event_stored', $this->context($event, $info));
        return $event;
    }

    private function findRegistration(array $payload, array $info): ?EventRegistration
    {
        Log::info('zoho_payment_webhook_registration_lookup_start', $this->context(null, $info));

        if (! empty($info['parsed_registration_id'])) {
            Log::info('zoho_payment_webhook_parsed_registration_id', $this->context(null, $info) + ['parsed_registration_id' => $info['parsed_registration_id']]);
            Log::info('zoho_payment_webhook_lookup_by_registration_id', $this->context(null, $info) + ['parsed_registration_id' => $info['parsed_registration_id']]);
            $r = EventRegistration::query()->where('id', $info['parsed_registration_id'])->first();
            if ($r) return $r;
        }

        $paymentLinkId = $info['payment_link_id'] ?: ($info['parsed_payment_link_id'] ?? null);
        if (! empty($paymentLinkId)) {
            $r = EventRegistration::query()->where('zoho_payment_link_id', $paymentLinkId)->latest('created_at')->first();
            if ($r) return $r;
        }

        if (! empty($info['parsed_original_payment_id'])) {
            $r = EventRegistration::query()->where('zoho_payment_id', $info['parsed_original_payment_id'])->latest('created_at')->first();
            if ($r) return $r;
        }

        if (! empty($info['payment_id'])) {
            $r = EventRegistration::query()->where('zoho_payment_id', $info['payment_id'])->latest('created_at')->first();
            if ($r) return $r;
        }

        if (! empty($info['reference_number'])) {
            $r = EventRegistration::query()
                ->where('zoho_payment_id', $info['reference_number'])
                ->orWhere('razorpay_payment_id', $info['reference_number'])
                ->latest('created_at')
                ->first();
            if ($r) return $r;
        }

        $url = data_get($payload, 'payment_link.url') ?? data_get($payload, 'url') ?? data_get($payload, 'data.url');
        if ($url) {
            $r = EventRegistration::query()->where('zoho_payment_link_url', $url)->orWhere('payment_url', $url)->latest('created_at')->first();
            if ($r) return $r;
        }

        foreach ([data_get($payload, 'registration_id'), data_get($payload, 'reference_number'), data_get($payload, 'payment.reference_number'), data_get($payload, 'data.reference_number')] as $id) {
            if ($id && Str::isUuid((string) $id)) {
                $r = EventRegistration::query()->find($id);
                if ($r) return $r;
            }
        }

        if (! empty($info['customer_id']) && $info['amount'] !== null) {
            $amount = (float) $info['amount'];
            Log::info('zoho_payment_webhook_lookup_by_customer_amount_start', $this->context(null, $info) + ['amount' => $amount]);
            $query = EventRegistration::query()
                ->where('zoho_customer_id', $info['customer_id'])
                ->where('payment_required', true)
                ->whereIn('payment_status', ['pending', 'processing', 'paid'])
                ->where('created_at', '>=', now()->subDays(3))
                ->where(function ($q) use ($amount): void {
                    $q->whereBetween('amount', [$amount - 0.01, $amount + 0.01]);
                    if (Schema::hasColumn('event_registrations', 'payment_amount')) {
                        $q->orWhereBetween('payment_amount', [$amount - 0.01, $amount + 0.01]);
                    }
                });
            $r = $query->latest('created_at')->first();
            if ($r) {
                Log::info('zoho_payment_webhook_lookup_by_customer_amount_found', $this->context(null, $info) + ['registration_id' => (string) $r->id, 'amount' => $amount]);
                return $r;
            }
            Log::warning('zoho_payment_webhook_lookup_by_customer_amount_failed', $this->context(null, $info) + ['amount' => $amount]);
        }

        return null;
    }

    private function primePaidFields(EventRegistration $registration, array $payload, array $info): void
    {
        $registration->forceFill($this->filter([
            'zoho_payment_id' => $registration->zoho_payment_id ?: $info['payment_id'],
            'zoho_payment_status' => 'paid',
            'payment_status' => 'paid',
            'payment_completed_at' => $registration->payment_completed_at ?: ($info['payment_date'] ? now()->parse((string) $info['payment_date']) : now()),
            'zoho_payment_webhook_payload' => $payload,
            'webhook_payload' => $payload,
            'metadata' => array_merge((array) ($registration->metadata ?? []), [
                'zoho_webhook_payment_id' => $info['payment_id'] ?? null,
                'zoho_webhook_reference_number' => $info['reference_number'] ?? null,
            ]),
        ]))->save();
    }

    private function markCancelledOrExpired(EventRegistration $registration, array $payload, string $status): void
    {
        if (($registration->payment_status ?? null) === 'paid') return;
        $registration->forceFill($this->filter([
            'zoho_payment_status' => $status,
            'payment_status' => $status === 'expired' ? 'expired' : 'failed',
            'payment_failed_reason' => 'Zoho payment link '.$status,
            'zoho_payment_webhook_payload' => $payload,
            'webhook_payload' => $payload,
        ]))->save();
    }

    private function isPaidWebhook(array $info): bool
    {
        $type = strtolower((string) ($info['event_type'] ?? ''));
        $status = strtolower((string) ($info['status'] ?? ''));
        return $type === 'customer_payment'
            || str_contains($type, 'paid')
            || str_contains($type, 'success')
            || in_array($status, ['paid', 'success', 'succeeded'], true)
            || ! empty($info['payment_id']);
    }

    private function isAlreadyFullySynced(EventRegistration $registration): bool
    {
        return ($registration->payment_status ?? null) === 'paid'
            && in_array(strtolower((string) ($registration->zoho_invoice_status ?? '')), ['paid', 'closed'], true)
            && ! empty($registration->qr_code_url)
            && empty($registration->zoho_invoice_sync_error);
    }

    private function storeFailedEventSafely(Request $request, array $payload, array $info, \Throwable $e): ?WebhookEvent
    {
        try {
            return WebhookEvent::query()->create([
                'provider' => 'zoho',
                'event_type' => $info['event_type'] ?? 'customer_payment',
                'external_event_id' => $info['external_event_id'] ?? null,
                'payment_link_id' => $info['payment_link_id'] ?? null,
                'payment_id' => $info['payment_id'] ?? null,
                'status' => 'failed',
                'payload' => $payload ?: ['raw' => $request->getContent()],
                'headers' => $this->safeHeaders($request),
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeHeaders(Request $request): array
    {
        return collect($request->headers->all())->except(['authorization', 'cookie', 'x-webhook-secret'])->all();
    }

    private function context(?WebhookEvent $event, array $info): array
    {
        return [
            'webhook_event_id' => $event?->id,
            'event_type' => $info['event_type'] ?? $event?->event_type,
            'payment_link_id' => $info['payment_link_id'] ?? $event?->payment_link_id,
            'payment_id' => $info['payment_id'] ?? $event?->payment_id,
            'reference_number' => $info['reference_number'] ?? null,
            'description' => $info['description'] ?? null,
            'customer_id' => $info['customer_id'] ?? null,
            'registration_id' => $info['registration_id'] ?? $event?->registration_id,
            'status' => $info['status'] ?? $event?->status,
        ];
    }


    private function parseDescriptionIdentifiers(string $description): array
    {
        $parsed = [];
        if ($description === '') {
            return $parsed;
        }
        if (preg_match('/registration_id=([0-9a-fA-F-]{36})/', $description, $m)) {
            $parsed['registration_id'] = $m[1];
        }
        if (preg_match('/payment_link_id=([0-9]+)/', $description, $m) || preg_match('/Zoho Payment Link\s+([0-9]+)/i', $description, $m)) {
            $parsed['payment_link_id'] = $m[1];
        }
        if (preg_match('/original_payment_id=([0-9]+)/', $description, $m) || preg_match('/original payment\s+([0-9]+)/i', $description, $m)) {
            $parsed['original_payment_id'] = $m[1];
        }
        return $parsed;
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function filter(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
