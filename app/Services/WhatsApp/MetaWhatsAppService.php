<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Exceptions\MetaWhatsAppException;
use App\Models\WhatsappMessage;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppService
{
    private string $baseUrl;

    private string $apiVersion;

    private ?string $accessToken;

    private string $wabaId;

    private string $phoneNumberId;

    private string $businessId;

    private string $appId;

    private string $testRecipient;

    /** @var list<string> */
    private array $allowedRecipients;

    private bool $restrictToTestRecipient;

    public function __construct()
    {
        $this->baseUrl = (string) Config::get('whatsapp.meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = (string) Config::get('whatsapp.meta.api_version', 'v22.0');
        $this->accessToken = Config::get('whatsapp.meta.access_token') ?: null;
        $this->wabaId = (string) Config::get('whatsapp.meta.business_account_id', '1475250733815899');
        $this->phoneNumberId = (string) Config::get('whatsapp.meta.phone_number_id', '1148211571702139');
        $this->businessId = (string) Config::get('whatsapp.meta.business_id', '5418283234857525');
        $this->appId = (string) Config::get('whatsapp.meta.app_id', '757348670325961');
        $this->testRecipient = (string) Config::get('whatsapp.testing.recipient', '+916353025164');
        $this->allowedRecipients = (array) Config::get('whatsapp.testing.allowed_recipients', ['+916353025164', '+917041151714']);
        if (empty($this->allowedRecipients)) {
            $this->allowedRecipients = [$this->testRecipient];
        }
        $this->restrictToTestRecipient = (bool) Config::get('whatsapp.testing.restrict_to_test_recipient', false);
    }

    /**
     * Get configured HTTP client with auth headers and timeout.
     */
    protected function client(): PendingRequest
    {
        if (empty($this->accessToken)) {
            throw new MetaWhatsAppException(
                'Meta WhatsApp access token is not configured. Please set WHATSAPP_META_ACCESS_TOKEN in your environment.',
                401,
                190,
                'ConfigurationError'
            );
        }

        $timeout = (int) Config::get('whatsapp.meta.timeout', 20);
        $retryTimes = (int) Config::get('whatsapp.meta.retry_times', 2);
        $retrySleepMs = (int) Config::get('whatsapp.meta.retry_sleep_ms', 500);

        return Http::withToken($this->accessToken)
            ->baseUrl("{$this->baseUrl}/{$this->apiVersion}")
            ->timeout($timeout)
            ->retry($retryTimes, $retrySleepMs, function ($exception, $request) {
                // Retry only network or 5xx server errors, never 4xx client errors
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response?->serverError());
            }, throw: false);
    }

    /**
     * Check Meta connection and validate configuration (WABA, Phone Number, Permissions).
     *
     * @return array{
     *     connected: bool,
     *     token_status: string,
     *     token_type: string,
     *     app_id: string,
     *     waba_access: string,
     *     phone_number_access: string,
     *     template_management: string,
     *     messaging: string,
     *     webhook_configured: string,
     *     details: array<string, mixed>,
     *     error: ?string
     * }
     */
    public function validateConfiguration(): array
    {
        $hasToken = ! empty($this->accessToken);
        $webhookVerifyToken = Config::get('whatsapp.meta.webhook_verify_token');
        $hasWebhookToken = ! empty($webhookVerifyToken);

        $result = [
            'connected' => false,
            'token_status' => $hasToken ? 'VALID' : 'MISSING',
            'token_type' => 'UNKNOWN',
            'app_id' => $this->appId,
            'waba_access' => 'NOT AVAILABLE',
            'phone_number_access' => 'NOT AVAILABLE',
            'template_management' => 'NOT AVAILABLE',
            'messaging' => 'NOT AVAILABLE',
            'webhook_configured' => $hasWebhookToken ? 'CONFIGURED' : 'NOT CONFIGURED',
            'details' => [],
            'error' => null,
        ];

        if (! $hasToken) {
            $result['token_status'] = 'INVALID';
            $result['error'] = 'Access token is missing. Please set WHATSAPP_META_ACCESS_TOKEN in .env';

            return $result;
        }

        try {
            // 1. Validate Phone Number Access
            $phoneResponse = $this->client()->get("/{$this->phoneNumberId}");
            if ($phoneResponse->successful()) {
                $phoneData = $phoneResponse->json();
                $result['phone_number_access'] = 'AVAILABLE';
                $result['messaging'] = 'AVAILABLE';
                $result['details']['phone'] = [
                    'id' => $phoneData['id'] ?? $this->phoneNumberId,
                    'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                    'verified_name' => $phoneData['verified_name'] ?? null,
                    'quality_rating' => $phoneData['quality_rating'] ?? null,
                    'code_verification_status' => $phoneData['code_verification_status'] ?? null,
                ];
            } else {
                $this->handleErrorResponse($phoneResponse, 'validateConfiguration:phone');
            }

            // 2. Validate WABA Access and Template Management
            $wabaResponse = $this->client()->get("/{$this->wabaId}", [
                'fields' => 'id,name,currency,timezone_id,message_template_namespace',
            ]);

            if ($wabaResponse->successful()) {
                $wabaData = $wabaResponse->json();
                $result['waba_access'] = 'AVAILABLE';
                $result['template_management'] = 'AVAILABLE';
                $result['details']['waba'] = [
                    'id' => $wabaData['id'] ?? $this->wabaId,
                    'name' => $wabaData['name'] ?? null,
                    'timezone_id' => $wabaData['timezone_id'] ?? null,
                    'currency' => $wabaData['currency'] ?? null,
                ];
            }

            // 3. Inspect Debug Token if available
            try {
                $debugResponse = $this->client()->get('/debug_token', [
                    'input_token' => $this->accessToken,
                ]);

                if ($debugResponse->successful()) {
                    $tokenData = $debugResponse->json('data') ?? [];
                    $result['token_type'] = $tokenData['type'] ?? 'SYSTEM_USER';
                    $result['details']['scopes'] = $tokenData['scopes'] ?? [];
                    $result['details']['is_valid'] = $tokenData['is_valid'] ?? true;
                    $result['details']['expires_at'] = isset($tokenData['data_access_expires_at'])
                        ? date('Y-m-d H:i:s', (int) $tokenData['data_access_expires_at'])
                        : 'Never / System User';
                }
            } catch (Throwable) {
                // debug_token may require app secret_proof or app access token; fallback gracefully
                $result['token_type'] = 'SYSTEM_USER';
            }

            $result['connected'] = ($result['phone_number_access'] === 'AVAILABLE' && $result['waba_access'] === 'AVAILABLE');
            $result['token_status'] = 'VALID';
        } catch (MetaWhatsAppException $e) {
            $result['connected'] = false;
            $result['token_status'] = 'INVALID';
            $result['error'] = $e->getUserFriendlyMessage();
        } catch (Throwable $e) {
            $result['connected'] = false;
            $result['token_status'] = 'INVALID';
            $result['error'] = 'Connection test failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Get WhatsApp Business Account metadata.
     *
     * @return array<string, mixed>
     */
    public function getBusinessAccount(): array
    {
        $response = $this->client()->get("/{$this->wabaId}", [
            'fields' => 'id,name,timezone_id,currency,message_template_namespace',
        ]);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'getBusinessAccount');
        }

        return (array) $response->json();
    }

    /**
     * Get Phone Numbers associated with the WABA.
     *
     * @return array<string, mixed>
     */
    public function getPhoneNumbers(): array
    {
        $response = $this->client()->get("/{$this->wabaId}/phone_numbers");

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'getPhoneNumbers');
        }

        return (array) $response->json();
    }

    /**
     * Get sending Phone Number metadata.
     *
     * @return array<string, mixed>
     */
    public function getPhoneNumberMetadata(): array
    {
        $response = $this->client()->get("/{$this->phoneNumberId}", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,status',
        ]);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'getPhoneNumberMetadata');
        }

        return (array) $response->json();
    }

    /**
     * Fetch all Message Templates from Meta for this WABA.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getTemplates(array $params = []): array
    {
        $queryParams = array_merge([
            'limit' => 100,
            'fields' => 'id,name,status,category,language,components,rejected_reason,quality_score',
        ], $params);

        $response = $this->client()->get("/{$this->wabaId}/message_templates", $queryParams);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'getTemplates');
        }

        return (array) $response->json();
    }

    /**
     * Fetch a single template by its Meta ID.
     *
     * @return array<string, mixed>
     */
    public function getTemplate(string $templateId): array
    {
        $response = $this->client()->get("/{$templateId}", [
            'fields' => 'id,name,status,category,language,components,rejected_reason,quality_score',
        ]);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, "getTemplate:{$templateId}");
        }

        return (array) $response->json();
    }

    /**
     * Create a new Message Template on Meta.
     *
     * @param  array{
     *     name: string,
     *     category: string,
     *     language: string,
     *     components: list<array<string, mixed>>,
     *     parameter_format?: string
     * }  $payload
     * @return array<string, mixed>
     */
    public function createTemplate(array $payload): array
    {
        // Meta template naming validation: lowercase, numbers, underscores only
        $name = strtolower(trim($payload['name']));
        if (! preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new MetaWhatsAppException(
                'Invalid template name. Template name must only contain lowercase alphanumeric characters and underscores.',
                422,
                100,
                'ValidationError'
            );
        }

        $payload['name'] = $name;

        // Allowed categories: MARKETING, UTILITY, AUTHENTICATION
        $category = strtoupper(trim($payload['category']));
        if (! in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
            throw new MetaWhatsAppException(
                "Invalid template category '{$category}'. Allowed categories are MARKETING, UTILITY, AUTHENTICATION.",
                422,
                100,
                'ValidationError'
            );
        }
        $payload['category'] = $category;

        Log::info('[MetaWhatsAppService] Creating Meta template request.', [
            'name' => $payload['name'],
            'language' => $payload['language'],
            'category' => $payload['category'],
            'component_types' => array_column($payload['components'] ?? [], 'type'),
        ]);

        $response = $this->client()->post("/{$this->wabaId}/message_templates", $payload);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, "createTemplate:{$name}");
        }

        $responseData = (array) $response->json();
        $metaTemplateId = (string) ($responseData['id'] ?? '');
        $status = strtoupper((string) ($responseData['status'] ?? 'IN_REVIEW'));

        // If Meta immediately marked the template as REJECTED, fetch the exact rejected_reason
        if ($status === 'REJECTED' && $metaTemplateId !== '') {
            try {
                $detailResponse = $this->client()->get("/{$this->wabaId}/message_templates", [
                    'fields' => 'id,name,status,rejected_reason',
                    'limit' => 10,
                ]);
                foreach ($detailResponse->json()['data'] ?? [] as $item) {
                    if (($item['id'] ?? '') === $metaTemplateId && ! empty($item['rejected_reason'])) {
                        $responseData['rejected_reason'] = $item['rejected_reason'];
                        break;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('[MetaWhatsAppService] Could not fetch rejection details immediately: '.$e->getMessage());
            }
        }

        // Persist/update local template record
        $localTemplate = WhatsappTemplate::updateOrCreate(
            ['name' => $name, 'language' => $payload['language']],
            [
                'meta_template_id' => $metaTemplateId ?: null,
                'category' => $category,
                'status' => $status,
                'parameter_format' => $payload['parameter_format'] ?? 'positional',
                'components' => $payload['components'],
                'meta_response' => $responseData,
                'last_synced_at' => now(),
            ]
        );

        Log::info('[MetaWhatsAppService] Meta template created successfully.', [
            'meta_template_id' => $metaTemplateId,
            'local_id' => $localTemplate->id,
            'status' => $localTemplate->status,
        ]);

        return array_merge($responseData, [
            'local_template_id' => (string) $localTemplate->id,
        ]);
    }

    /**
     * Upload sample media for template approval via Meta Resumable Upload API.
     *
     * @return string Meta file handle ("h:...")
     */
    public function uploadTemplateMediaSample(UploadedFile $file): string
    {
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $fileContents = file_get_contents($file->getRealPath());

        if ($fileContents === false || $fileSize === 0) {
            throw new MetaWhatsAppException(
                'Failed to read media sample file contents for template approval.',
                422,
                100,
                'FileReadError'
            );
        }

        // Step 1: Create upload session on Meta App
        $sessionUrl = "{$this->baseUrl}/{$this->apiVersion}/{$this->appId}/uploads";
        $sessionResponse = Http::withToken($this->accessToken)
            ->post($sessionUrl, [
                'file_length' => $fileSize,
                'file_type' => $mimeType,
            ]);

        if (! $sessionResponse->successful()) {
            $this->handleErrorResponse($sessionResponse, 'createMediaUploadSession');
        }

        $uploadSessionId = (string) ($sessionResponse->json()['id'] ?? '');
        if ($uploadSessionId === '') {
            throw new MetaWhatsAppException(
                'Meta did not return an upload session ID for template sample media.',
                500,
                500,
                'UploadSessionError'
            );
        }

        // Step 2: Upload raw file bytes to session
        $uploadUrl = "{$this->baseUrl}/{$this->apiVersion}/{$uploadSessionId}";
        $uploadResponse = Http::withHeaders([
            'Authorization' => "OAuth {$this->accessToken}",
            'file_offset' => 0,
        ])->withBody($fileContents, $mimeType)
            ->post($uploadUrl);

        if (! $uploadResponse->successful()) {
            $this->handleErrorResponse($uploadResponse, 'uploadMediaSampleBytes');
        }

        $handle = (string) ($uploadResponse->json()['h'] ?? '');
        if ($handle === '') {
            throw new MetaWhatsAppException(
                'Meta did not return a media handle for template sample.',
                500,
                500,
                'MediaHandleError'
            );
        }

        Log::info('[MetaWhatsAppService] Successfully uploaded template sample media.', [
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'handle' => substr($handle, 0, 15).'...',
        ]);

        return $handle;
    }

    /**
     * Upload media file directly to Meta Cloud API for messaging.
     * Endpoint: POST /{phone_number_id}/media
     *
     * @return string Meta Media ID
     */
    public function uploadMedia(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $fileContents = file_get_contents($file->getRealPath());

        if ($fileContents === false || $file->getSize() === 0) {
            throw new MetaWhatsAppException(
                'Failed to read media file for WhatsApp message.',
                422,
                100,
                'FileReadError'
            );
        }

        $response = Http::withToken($this->accessToken)
            ->attach('file', $fileContents, $file->getClientOriginalName(), ['Content-Type' => $mimeType])
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'uploadMedia');
        }

        $mediaId = (string) ($response->json()['id'] ?? '');
        if ($mediaId === '') {
            throw new MetaWhatsAppException('Meta did not return a media ID.', 500, 500, 'MediaUploadError');
        }

        Log::info('[MetaWhatsAppService] Media uploaded successfully to Meta.', [
            'media_id' => $mediaId,
            'mime_type' => $mimeType,
            'file_name' => $file->getClientOriginalName(),
        ]);

        return $mediaId;
    }

    /**
     * Delete a template from Meta by name.
     */
    public function deleteTemplate(string $templateName): bool
    {
        $response = $this->client()->delete("/{$this->wabaId}/message_templates", [
            'name' => $templateName,
        ]);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, "deleteTemplate:{$templateName}");
        }

        // Update local status or remove
        WhatsappTemplate::where('name', $templateName)->update([
            'status' => 'DELETED',
            'is_active' => false,
        ]);

        Log::info('[MetaWhatsAppService] WhatsApp template deleted from Meta.', [
            'template_name' => $templateName,
        ]);

        return true;
    }

    /**
     * Update an existing WhatsApp template on Meta via Graph API.
     * Endpoint: POST /{template_id}
     *
     * @param  array{components: list<array<string, mixed>>, category?: string}  $payload
     * @return array<string, mixed>
     */
    public function updateTemplate(string $metaTemplateId, array $payload): array
    {
        $components = $payload['components'] ?? [];

        Log::info('[MetaWhatsAppService] Updating Meta template request.', [
            'meta_template_id' => $metaTemplateId,
            'component_types' => array_column($components, 'type'),
        ]);

        $postData = [
            'components' => $components,
        ];

        if (! empty($payload['category'])) {
            $postData['category'] = strtoupper((string) $payload['category']);
        }

        $response = $this->client()->post("/{$metaTemplateId}", $postData);

        if (! $response->successful()) {
            $this->handleErrorResponse($response, "updateTemplate:{$metaTemplateId}");
        }

        $responseData = (array) $response->json();

        // Update local template record
        $localTemplate = WhatsappTemplate::where('meta_template_id', $metaTemplateId)->first();
        if ($localTemplate) {
            $localTemplate->update([
                'components' => $components,
                'status' => 'IN_REVIEW',
                'meta_response' => array_merge($localTemplate->meta_response ?? [], $responseData),
                'last_synced_at' => now(),
            ]);
        }

        Log::info('[MetaWhatsAppService] Meta template updated successfully.', [
            'meta_template_id' => $metaTemplateId,
            'response' => $responseData,
        ]);

        return $responseData;
    }

    /**
     * Synchronize all Message Templates from Meta into the local database idempotently.
     *
     * @return array{total_synced: int, created: int, updated: int}
     */
    public function syncTemplates(): array
    {
        $response = $this->getTemplates(['limit' => 200]);
        $templates = $response['data'] ?? [];

        $created = 0;
        $updated = 0;

        foreach ($templates as $metaItem) {
            $metaId = (string) ($metaItem['id'] ?? '');
            $name = (string) ($metaItem['name'] ?? '');
            $language = (string) ($metaItem['language'] ?? 'en_US');
            $status = strtoupper((string) ($metaItem['status'] ?? 'PENDING'));
            $category = strtoupper((string) ($metaItem['category'] ?? 'UTILITY'));
            $quality = isset($metaItem['quality_score']['score']) ? (string) $metaItem['quality_score']['score'] : null;
            $parameterFormat = (string) ($metaItem['parameter_format'] ?? 'positional');
            $components = (array) ($metaItem['components'] ?? []);

            $local = WhatsappTemplate::query()
                ->where('meta_template_id', $metaId)
                ->orWhere(function ($query) use ($name, $language) {
                    $query->where('name', $name)->where('language', $language);
                })
                ->first();

            $attributes = [
                'meta_template_id' => $metaId,
                'name' => $name,
                'template_key' => $name,
                'template_name' => $name,
                'language' => $language,
                'category' => $category,
                'status' => $status,
                'quality' => $quality,
                'parameter_format' => $parameterFormat,
                'components' => $components,
                'meta_response' => $metaItem,
                'last_synced_at' => now(),
            ];

            if ($local) {
                $local->update($attributes);
                $updated++;
            } else {
                WhatsappTemplate::create($attributes);
                $created++;
            }
        }

        Log::info('[MetaWhatsAppService] Synced WhatsApp templates from Meta.', [
            'count' => count($templates),
            'created' => $created,
            'updated' => $updated,
        ]);

        return [
            'total_synced' => count($templates),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Send a template message to a recipient phone number via Meta WhatsApp Cloud API.
     *
     * @param  list<array<string, mixed>>  $components
     * @return array{success: bool, meta_message_id: ?string, whatsapp_message_id: string, status: string}
     */
    public function sendTemplateMessage(
        string $recipientPhone,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
        ?string $userId = null
    ): array {
        $normalizedRecipient = $this->normalizePhone($recipientPhone);

        // Security check for development testing: Restrict to configured test recipients
        if ($this->restrictToTestRecipient) {
            $normalizedAllowed = array_map([self::class, 'normalizePhone'], $this->allowedRecipients);
            if (! in_array($normalizedRecipient, $normalizedAllowed, true)) {
                $allowedList = implode(', ', $this->allowedRecipients);
                $errorMsg = "Local WhatsApp testing is restricted to the configured test recipients: {$allowedList}. Attempted recipient: {$recipientPhone}";
                Log::warning('[MetaWhatsAppService] Blocked send to unauthorized recipient in test mode.', [
                    'recipient' => $recipientPhone,
                    'allowed' => $this->allowedRecipients,
                ]);

                throw new MetaWhatsAppException($errorMsg, 403, 131026, 'RecipientRestrictionError');
            }
        }

        // Verify local template exists and is APPROVED
        $template = WhatsappTemplate::query()
            ->where('name', $templateName)
            ->where('language', $languageCode)
            ->first();

        if ($template && ! $template->isSendable()) {
            $msg = "WhatsApp template '{$templateName}' cannot be sent because its current status is {$template->status}. Only APPROVED templates can be sent.";
            Log::warning('[MetaWhatsAppService] Attempted to send unapproved template.', [
                'template' => $templateName,
                'status' => $template->status,
            ]);

            throw new MetaWhatsAppException($msg, 422, 132001, 'UnapprovedTemplateError');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedRecipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        // Create initial PENDING message log
        $messageRecord = WhatsappMessage::create([
            'user_id' => $userId,
            'recipient_phone' => $normalizedRecipient,
            'whatsapp_template_id' => $template?->id,
            'meta_template_id' => $template?->meta_template_id,
            'template_name' => $templateName,
            'template_language' => $languageCode,
            'message_type' => 'template',
            'status' => 'PENDING',
            'request_payload' => $payload,
        ]);

        try {
            Log::info('[MetaWhatsAppService] Dispatching WhatsApp message to Meta Cloud API.', [
                'message_record_id' => $messageRecord->id,
                'recipient' => $normalizedRecipient,
                'template' => $templateName,
            ]);

            $response = $this->client()->post("/{$this->phoneNumberId}/messages", $payload);

            if (! $response->successful()) {
                $this->handleErrorResponse($response, "sendTemplateMessage:{$templateName}");
            }

            $responseData = (array) $response->json();
            $metaMessageId = (string) ($responseData['messages'][0]['id'] ?? '');

            $messageRecord->update([
                'status' => 'SENT',
                'meta_message_id' => $metaMessageId ?: null,
                'response_payload' => $responseData,
                'sent_at' => now(),
            ]);

            try {
                WhatsAppMessageLog::create([
                    'whatsapp_template_id' => $template?->id,
                    'template_key' => $templateName,
                    'template_name' => $templateName,
                    'event_key' => 'meta_whatsapp_dispatch',
                    'user_id' => $userId,
                    'recipient_phone' => $normalizedRecipient,
                    'requested_recipient_phone' => $recipientPhone,
                    'actual_recipient_phone' => $normalizedRecipient,
                    'payload' => $payload,
                    'request_payload' => $payload,
                    'provider_response' => $responseData,
                    'provider_message_id' => $metaMessageId ?: null,
                    'status' => WhatsAppMessageLog::STATUS_SENT,
                    'source' => 'meta_cloud_api',
                    'environment' => app()->environment(),
                    'is_test' => true,
                    'sent_at' => now(),
                ]);
            } catch (Throwable $logEx) {
                Log::warning('[MetaWhatsAppService] Could not write to WhatsAppMessageLog: '.$logEx->getMessage());
            }

            Log::info('[MetaWhatsAppService] WhatsApp message accepted by Meta Cloud API.', [
                'whatsapp_message_id' => $messageRecord->id,
                'meta_message_id' => $metaMessageId,
                'status' => 'SENT',
            ]);

            return [
                'success' => true,
                'meta_message_id' => $metaMessageId,
                'whatsapp_message_id' => (string) $messageRecord->id,
                'status' => 'SENT',
            ];
        } catch (MetaWhatsAppException $e) {
            $messageRecord->update([
                'status' => 'FAILED',
                'error_code' => (string) ($e->getMetaErrorCode() ?? $e->getHttpStatus()),
                'error_message' => $e->getUserFriendlyMessage(),
                'response_payload' => $e->getRawError(),
                'failed_at' => now(),
            ]);

            try {
                WhatsAppMessageLog::create([
                    'whatsapp_template_id' => $template?->id,
                    'template_key' => $templateName,
                    'template_name' => $templateName,
                    'event_key' => 'meta_whatsapp_dispatch',
                    'user_id' => $userId,
                    'recipient_phone' => $normalizedRecipient,
                    'requested_recipient_phone' => $recipientPhone,
                    'actual_recipient_phone' => $normalizedRecipient,
                    'payload' => $payload,
                    'request_payload' => $payload,
                    'status' => WhatsAppMessageLog::STATUS_FAILED,
                    'error_code' => (string) ($e->getMetaErrorCode() ?? $e->getHttpStatus()),
                    'error_message' => $e->getUserFriendlyMessage(),
                    'provider_response' => $e->getRawError(),
                    'source' => 'meta_cloud_api',
                    'environment' => app()->environment(),
                    'is_test' => true,
                    'failed_at' => now(),
                ]);
            } catch (Throwable $logEx) {
                Log::warning('[MetaWhatsAppService] Could not write failed log to WhatsAppMessageLog: '.$logEx->getMessage());
            }

            Log::error('[MetaWhatsAppService] Failed to send WhatsApp message.', [
                'whatsapp_message_id' => $messageRecord->id,
                'error_code' => $e->getMetaErrorCode(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $messageRecord->update([
                'status' => 'FAILED',
                'error_code' => '500',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            try {
                WhatsAppMessageLog::create([
                    'whatsapp_template_id' => $template?->id,
                    'template_key' => $templateName,
                    'template_name' => $templateName,
                    'event_key' => 'meta_whatsapp_dispatch',
                    'user_id' => $userId,
                    'recipient_phone' => $normalizedRecipient,
                    'requested_recipient_phone' => $recipientPhone,
                    'actual_recipient_phone' => $normalizedRecipient,
                    'payload' => $payload,
                    'request_payload' => $payload,
                    'status' => WhatsAppMessageLog::STATUS_FAILED,
                    'error_code' => '500',
                    'error_message' => $e->getMessage(),
                    'source' => 'meta_cloud_api',
                    'environment' => app()->environment(),
                    'is_test' => true,
                    'failed_at' => now(),
                ]);
            } catch (Throwable $logEx) {
                Log::warning('[MetaWhatsAppService] Could not write generic error to WhatsAppMessageLog: '.$logEx->getMessage());
            }

            Log::error('[MetaWhatsAppService] Unexpected exception sending WhatsApp message: '.$e->getMessage());

            throw new MetaWhatsAppException('Message sending failed: '.$e->getMessage(), 500, 500, 'SystemError', null, null, [], [], $e);
        }
    }

    /**
     * Subscribe app to WABA webhooks.
     *
     * @return array<string, mixed>
     */
    public function subscribeWebhook(): array
    {
        $response = $this->client()->post("/{$this->wabaId}/subscribed_apps");

        if (! $response->successful()) {
            $this->handleErrorResponse($response, 'subscribeWebhook');
        }

        return (array) $response->json();
    }

    /**
     * Normalize international phone numbers to standard format (e.g. 916353025164).
     */
    public static function normalizePhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $trimmed = trim($phone);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return $digits;
        }

        if (str_starts_with($trimmed, '00') && strlen($digits) > 2) {
            return substr($digits, 2);
        }

        // Indian 10-digit mobile
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Handle HTTP error response from Meta Graph API.
     *
     * @throws MetaWhatsAppException
     */
    private function handleErrorResponse(Response $response, string $operation): never
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $error = $body['error'] ?? [];

        $message = (string) ($error['message'] ?? $response->body() ?: 'Meta Graph API error');
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $type = isset($error['type']) ? (string) $error['type'] : null;
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
        $fbtraceId = isset($error['fbtrace_id']) ? (string) $error['fbtrace_id'] : null;

        Log::error("[MetaWhatsAppService] Meta API error during {$operation}.", [
            'http_status' => $status,
            'meta_code' => $code,
            'meta_type' => $type,
            'meta_subcode' => $subcode,
            'meta_message' => $message,
            'fbtrace_id' => $fbtraceId,
        ]);

        throw new MetaWhatsAppException(
            $message,
            $status,
            $code,
            $type,
            $subcode,
            $fbtraceId,
            $error,
            ['operation' => $operation]
        );
    }
}
