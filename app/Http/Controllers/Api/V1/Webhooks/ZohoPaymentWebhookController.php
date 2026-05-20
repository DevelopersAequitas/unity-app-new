<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventRegistration;
use App\Services\EventPaymentService;
use App\Services\Zoho\ZohoPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZohoPaymentWebhookController extends BaseApiController
{
    public function __construct(private readonly EventPaymentService $payments, private readonly ZohoPaymentService $zohoPaymentService) {}

    public function handle(Request $request)
    {
        $raw = $request->getContent();
        $payload = $request->all();
        Log::info('webhook received', ['payload' => $payload]);

        DB::table('zoho_payment_webhook_logs')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => data_get($payload, 'event_type', 'unknown'),
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secret = (string) config('services.zoho_payments.webhook_secret');
        $signature = (string) $request->header('X-Zoho-Signature');
        if ($secret !== '' && $signature !== '' && ! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            Log::error('webhook errors', ['reason' => 'invalid signature']);
            return response()->json(['ok' => true], 200);
        }

        $event = (string) data_get($payload, 'event_type');
        $registration = $this->resolveRegistration($payload);
        if (! $registration) {
            return response()->json(['ok' => true], 200);
        }

        if (in_array($event, ['payment.succeeded', 'payment_link.paid'], true)) {
            $this->payments->markZohoPaymentPaid($registration, $payload);
        } elseif ($event === 'payment.failed') {
            $sessionId = (string) ($registration->zoho_payment_session_id ?? '');
            $latest = $sessionId !== '' ? $this->zohoPaymentService->verifyPaymentSession($sessionId) : [];
            $latestStatus = strtolower((string) (data_get($latest, 'payment_session.status') ?? data_get($latest, 'status', '')));
            if (in_array($latestStatus, ['paid', 'succeeded', 'success'], true)) {
                $this->payments->markZohoPaymentPaid($registration, $payload);
            } else {
                $this->payments->markZohoPaymentFailed($registration, $payload);
            }
        } elseif (in_array($event, ['payment_link.expired', 'payment_link.canceled'], true)) {
            $this->payments->markZohoPaymentFailed($registration, $payload);
        }

        return response()->json(['ok' => true], 200);
    }

    private function resolveRegistration(array $payload): ?EventRegistration
    {
        $referenceId = data_get($payload, 'data.payment.reference_id') ?? data_get($payload, 'reference_id') ?? data_get($payload, 'registration_id');
        $sessionId = data_get($payload, 'data.payment.session_id') ?? data_get($payload, 'session_id');
        $linkId = data_get($payload, 'data.payment_link.payment_link_id') ?? data_get($payload, 'payment_link_id');

        return EventRegistration::query()
            ->when($referenceId, fn ($q) => $q->orWhere('id', (string) $referenceId))
            ->when($sessionId, fn ($q) => $q->orWhere('zoho_payment_session_id', (string) $sessionId))
            ->when($linkId, fn ($q) => $q->orWhere('zoho_payment_link_id', (string) $linkId))
            ->first();
    }
}
