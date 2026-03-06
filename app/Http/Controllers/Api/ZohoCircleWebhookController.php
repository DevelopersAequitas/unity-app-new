<?php

namespace App\Http\Controllers\Api;

use App\Services\Zoho\CircleWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZohoCircleWebhookController extends BaseApiController
{
    public function __construct(private readonly CircleWebhookService $service)
    {
    }

    public function handle(Request $request)
    {
        if (! $this->service->isValid($request)) {
            return $this->error('Unauthorized webhook request.', 401);
        }

        $payload = $request->all();

        Log::info('Zoho circle webhook received', [
            'event_type' => data_get($payload, 'event_type') ?? data_get($payload, 'event'),
            'raw' => $payload,
        ]);

        $this->service->handle($payload);

        return $this->success(['processed' => true], 'Webhook processed successfully.');
    }
}
