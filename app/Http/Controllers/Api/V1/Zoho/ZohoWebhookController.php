<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessZohoWebhookJob;
use App\Models\ZohoWebhookLog;
use App\Services\Zoho\ZohoWebhookAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoWebhookController extends Controller
{
    public function __construct(private readonly ZohoWebhookAuthService $zohoWebhookAuthService) {}

    public function handle(Request $request)
    {
        if (! $this->zohoWebhookAuthService->isAuthorized($request)) {
            Log::warning('Zoho webhook unauthorized', [
                'path' => $request->path(),
                'headers' => $request->headers->all(),
                'has_payload' => ! empty($request->all()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized webhook request.',
            ], 401);
        }

        $raw = $request->getContent();
        $event = $request->all();

        if ($event === [] && $raw !== '') {
            $decoded = json_decode($raw, true);
            $event = is_array($decoded) ? $decoded : $event;
        }

        if (! is_array($event) || $event === []) {
            Log::error('Zoho webhook invalid payload, skipping', [
                'ip' => $request->ip(),
                'raw_preview' => mb_substr((string) $raw, 0, 1000),
            ]);

            return response()->json([
                'success' => true,
                'handled' => false,
            ], 200);
        }

        Log::info('Zoho webhook authorized and received', [
            'path' => $request->path(),
            'headers' => $request->headers->all(),
            'payload' => $event,
        ]);

        try {
            $webhookLog = ZohoWebhookLog::query()->create([
                'event_type' => $event['event_type'] ?? ($event['eventType'] ?? null),
                'module' => data_get($event, 'module'),
                'zoho_record_id' => data_get($event, 'id') ?? data_get($event, 'entity_id'),
                'subscription_id' => data_get($event, 'subscription.subscription_id') ?? data_get($event, 'subscription_id'),
                'hostedpage_id' => data_get($event, 'hostedpage.hostedpage_id') ?? data_get($event, 'hostedpage_id'),
                'invoice_id' => data_get($event, 'invoice.invoice_id') ?? data_get($event, 'invoice_id'),
                'payment_id' => data_get($event, 'payment.payment_id') ?? data_get($event, 'payment_id'),
                'payload' => $event,
                'status' => 'pending',
            ]);

            ProcessZohoWebhookJob::dispatch($webhookLog->id)->afterResponse();
        } catch (Throwable $throwable) {
            Log::error('Zoho webhook enqueue failed', [
                'event_type' => $event['event_type'] ?? ($event['eventType'] ?? null),
                'message' => $throwable->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
        ], 200);
    }
}
