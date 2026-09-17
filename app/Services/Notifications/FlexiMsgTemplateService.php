<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\WhatsappTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FlexiMsgTemplateService
{
    /**
     * Common Meta / WhatsApp CDN host patterns that provide signed/temporary media URLs.
     */
    private const META_CDN_DOMAINS = [
        'scontent.whatsapp.net',
        'fbcdn.net',
        'lookaside.fbsbx.com',
        'whatsapp.com',
    ];

    public function __construct(
        private readonly WhatsappNotificationService $whatsappService
    ) {}

    /**
     * Check if a URL belongs to a Meta / WhatsApp CDN domain.
     */
    public function isMetaCdnUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }

        $host = strtolower($host);

        foreach (self::META_CDN_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the media for a template update is unchanged from the existing configuration.
     *
     * @param  array<string, mixed>  $existingTemplateData
     * @param  array<string, mixed>  $incomingData
     */
    public function isMediaUnchanged(
        array $existingTemplateData,
        array $incomingData,
        ?UploadedFile $newMediaFile = null
    ): bool {
        // If a new physical file was uploaded, media is definitely changed
        if ($newMediaFile !== null) {
            return false;
        }

        $existingHeader = $this->extractHeaderComponent($existingTemplateData);
        $incomingHeader = $this->extractHeaderComponent($incomingData);

        // If existing template had no media header and incoming still has no media header, it's unchanged
        $existingFormat = strtoupper(trim((string) ($existingHeader['format'] ?? ($existingHeader['type'] ?? 'NONE'))));
        $incomingFormat = strtoupper(trim((string) ($incomingHeader['format'] ?? ($incomingHeader['type'] ?? $existingFormat))));

        if (! in_array($existingFormat, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && ! in_array($incomingFormat, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return true;
        }

        // If header format changed (e.g. from IMAGE to VIDEO or TEXT), media is not unchanged
        if ($existingFormat !== $incomingFormat) {
            return false;
        }

        // Check explicit flag to preserve existing media
        if (! empty($incomingData['keep_existing_media']) || ($incomingData['media_action'] ?? null) === 'keep') {
            return true;
        }

        $existingUrl = $this->extractMediaUrlFromHeader($existingHeader);
        $incomingUrl = $this->extractMediaUrlFromHeader($incomingHeader);

        // If no incoming media URL is specified, retain existing media
        if ($incomingUrl === null || trim($incomingUrl) === '') {
            return true;
        }

        // If incoming URL is identical to existing URL
        if ($existingUrl !== null && trim($incomingUrl) === trim($existingUrl)) {
            return true;
        }

        // If incoming URL is a Meta CDN URL (e.g. scontent.whatsapp.net) and we already have existing media configured
        if ($this->isMetaCdnUrl($incomingUrl)) {
            return true;
        }

        return false;
    }

    /**
     * Process header media for template creation or update.
     *
     * @param  array<string, mixed>  $existingTemplateData
     * @param  array<string, mixed>  $incomingData
     * @return array{preserved: bool, format: string, media_url: ?string, handle: ?string, local_path: ?string}
     */
    public function processHeaderMedia(
        array $existingTemplateData,
        array $incomingData,
        ?UploadedFile $newMediaFile = null
    ): array {
        $existingHeader = $this->extractHeaderComponent($existingTemplateData);
        $incomingHeader = $this->extractHeaderComponent($incomingData);

        $format = strtoupper(trim((string) ($incomingHeader['format'] ?? ($incomingHeader['type'] ?? ($existingHeader['format'] ?? 'NONE')))));

        if (! in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return [
                'preserved' => true,
                'format' => $format,
                'media_url' => null,
                'handle' => null,
                'local_path' => null,
            ];
        }

        $isUnchanged = $this->isMediaUnchanged($existingTemplateData, $incomingData, $newMediaFile);

        if ($isUnchanged) {
            $existingUrl = $this->extractMediaUrlFromHeader($existingHeader);
            $existingHandle = $existingHeader['example']['header_handle'][0] ?? ($existingHeader['header_handle'] ?? null);

            Log::info('[FlexiMsgTemplateService] Preserving existing template media without re-downloading Meta CDN URL.', [
                'format' => $format,
                'existing_url_domain' => $existingUrl ? parse_url($existingUrl, PHP_URL_HOST) : null,
                'has_handle' => ! empty($existingHandle),
            ]);

            return [
                'preserved' => true,
                'format' => $format,
                'media_url' => $existingUrl,
                'handle' => is_string($existingHandle) ? $existingHandle : null,
                'local_path' => null,
            ];
        }

        // Handle newly uploaded file
        if ($newMediaFile !== null) {
            $disk = config('filesystems.default', 'public');
            $path = $newMediaFile->store('uploads/whatsapp/templates', $disk);
            $storedUrl = Storage::disk($disk)->url($path);

            Log::info('[FlexiMsgTemplateService] Processed new uploaded file for template header.', [
                'format' => $format,
                'stored_path' => $path,
                'url' => $storedUrl,
            ]);

            return [
                'preserved' => false,
                'format' => $format,
                'media_url' => $storedUrl,
                'handle' => null,
                'local_path' => $path,
            ];
        }

        // Handle genuinely new remote media URL
        $newUrl = $this->extractMediaUrlFromHeader($incomingHeader);
        if ($newUrl !== null && trim($newUrl) !== '') {
            $downloadedPath = $this->downloadRemoteMedia(trim($newUrl));

            return [
                'preserved' => false,
                'format' => $format,
                'media_url' => trim($newUrl),
                'handle' => null,
                'local_path' => $downloadedPath,
            ];
        }

        return [
            'preserved' => true,
            'format' => $format,
            'media_url' => null,
            'handle' => null,
            'local_path' => null,
        ];
    }

    /**
     * Download and validate a remote media file.
     * Throws RuntimeException if downloading a new remote media URL fails.
     */
    public function downloadRemoteMedia(string $url): string
    {
        $trimmed = trim($url);

        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->get($trimmed);

            if (! $response->successful()) {
                throw new RuntimeException("Template update failed: Failed to download media file from: {$trimmed} (HTTP {$response->status()})");
            }

            $body = $response->body();
            if (empty($body)) {
                throw new RuntimeException("Template update failed: Failed to download media file from: {$trimmed} (Empty response)");
            }

            $tempDir = storage_path('app/tmp/template_media');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $ext = pathinfo((string) parse_url($trimmed, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($ext)) {
                $contentType = (string) $response->header('Content-Type');
                $ext = match (true) {
                    str_contains($contentType, 'image/png') => 'png',
                    str_contains($contentType, 'image/jpeg') => 'jpg',
                    str_contains($contentType, 'image/webp') => 'webp',
                    str_contains($contentType, 'video/mp4') => 'mp4',
                    str_contains($contentType, 'application/pdf') => 'pdf',
                    default => 'bin',
                };
            }

            $filePath = $tempDir.'/'.Str::uuid().'.'.$ext;
            file_put_contents($filePath, $body);

            return $filePath;
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException("Template update failed: Failed to download media file from: {$trimmed} ({$e->getMessage()})", 0, $e);
        }
    }

    /**
     * Construct the full FlexiMSG / Meta API template update payload.
     *
     * @param  array<string, mixed>  $existingTemplateData
     * @param  array<string, mixed>  $incomingData
     * @param  array<string, mixed>  $processedMedia
     * @return array<string, mixed>
     */
    public function buildFlexiMsgUpdatePayload(
        array $existingTemplateData,
        array $incomingData,
        array $processedMedia
    ): array {
        $components = [];

        // 1. HEADER COMPONENT
        $headerFormat = $processedMedia['format'] ?? 'NONE';
        if ($headerFormat !== 'NONE') {
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $headerFormat,
            ];

            if (in_array($headerFormat, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                $example = [];

                if (! empty($processedMedia['handle'])) {
                    $example['header_handle'] = [$processedMedia['handle']];
                } elseif (! empty($processedMedia['media_url'])) {
                    $example['header_url'] = [$processedMedia['media_url']];
                }

                if (! empty($example)) {
                    $headerComponent['example'] = $example;
                }
            } elseif ($headerFormat === 'TEXT') {
                $headerText = $incomingData['header_text'] ?? ($incomingData['header']['text'] ?? '');
                $headerComponent['text'] = $headerText;
            }

            $components[] = $headerComponent;
        }

        // 2. BODY COMPONENT
        $bodyText = $incomingData['body_text']
            ?? ($incomingData['body']['text'] ?? ($incomingData['body'] ?? ($existingTemplateData['body_text'] ?? ($existingTemplateData['body']['text'] ?? ''))));

        $sampleValues = $incomingData['sample_values']
            ?? ($incomingData['examples'] ?? ($incomingData['body']['example']['body_text'][0] ?? ($existingTemplateData['sample_values'] ?? [])));

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        if (is_array($sampleValues) && ! empty($sampleValues)) {
            $bodyComponent['example'] = [
                'body_text' => [array_values($sampleValues)],
            ];
        }

        $components[] = $bodyComponent;

        // 3. FOOTER COMPONENT
        $footerText = $incomingData['footer_text']
            ?? ($incomingData['footer']['text'] ?? ($incomingData['footer'] ?? ($existingTemplateData['footer_text'] ?? ($existingTemplateData['footer']['text'] ?? null))));

        if ($footerText !== null && trim((string) $footerText) !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => trim((string) $footerText),
            ];
        }

        // 4. BUTTONS COMPONENT
        $buttons = $incomingData['buttons']
            ?? ($incomingData['buttons']['buttons'] ?? ($existingTemplateData['buttons'] ?? null));

        if (is_array($buttons) && ! empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        $templateName = $incomingData['template_name']
            ?? ($incomingData['name'] ?? ($existingTemplateData['template_name'] ?? ($existingTemplateData['name'] ?? '')));

        $category = $incomingData['category']
            ?? ($existingTemplateData['category'] ?? 'MARKETING');

        $language = $incomingData['language']
            ?? ($existingTemplateData['language'] ?? 'en');

        return [
            'name' => $templateName,
            'category' => $category,
            'language' => $language,
            'components' => $components,
        ];
    }

    /**
     * Execute the full template update flow.
     *
     * @param  string|WhatsappTemplate|array<string, mixed>  $templateOrKey
     * @param  array<string, mixed>  $updateData
     * @return array{success: bool, message: string, payload: array<string, mixed>, response?: mixed}
     */
    public function updateTemplate(
        string|WhatsappTemplate|array $templateOrKey,
        array $updateData,
        ?UploadedFile $newMediaFile = null
    ): array {
        $existingTemplateData = $this->resolveTemplateData($templateOrKey);

        // 1. Process header media safely
        $processedMedia = $this->processHeaderMedia($existingTemplateData, $updateData, $newMediaFile);

        // 2. Build FlexiMSG payload
        $flexiMsgPayload = $this->buildFlexiMsgUpdatePayload($existingTemplateData, $updateData, $processedMedia);

        // 3. If template exists in database, update local record fields
        if ($templateOrKey instanceof WhatsappTemplate) {
            $templateOrKey->update([
                'template_name' => $flexiMsgPayload['name'] ?? $templateOrKey->template_name,
                'description' => $updateData['description'] ?? $templateOrKey->description,
            ]);
        } elseif (is_string($templateOrKey)) {
            $dbModel = WhatsappTemplate::where('template_key', $templateOrKey)->first();
            if ($dbModel) {
                $dbModel->update([
                    'template_name' => $flexiMsgPayload['name'] ?? $dbModel->template_name,
                    'description' => $updateData['description'] ?? $dbModel->description,
                ]);
            }
        }

        // 4. Send update request to FlexiMSG / Meta API endpoint if webhook URL configured
        $apiEndpoint = $updateData['api_endpoint']
            ?? ($existingTemplateData['api_endpoint'] ?? config('services.fleximsg.template_api_url'));

        $apiResponse = null;
        if (! empty($apiEndpoint) && is_string($apiEndpoint)) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.(config('services.fleximsg.api_token') ?? ''),
                ])
                    ->timeout(15)
                    ->put($apiEndpoint, $flexiMsgPayload);

                $apiResponse = $response->json() ?? ['status' => $response->status(), 'raw' => $response->body()];

                if (! $response->successful()) {
                    Log::error('[FlexiMsgTemplateService] FlexiMSG API template update failed.', [
                        'endpoint' => $apiEndpoint,
                        'status' => $response->status(),
                        'response' => $apiResponse,
                    ]);

                    return [
                        'success' => false,
                        'message' => 'FlexiMSG template update failed: '.($apiResponse['message'] ?? 'API error'),
                        'payload' => $flexiMsgPayload,
                        'response' => $apiResponse,
                    ];
                }
            } catch (Throwable $e) {
                Log::error('[FlexiMsgTemplateService] Exception sending template update to FlexiMSG.', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Template update exception: '.$e->getMessage(),
                    'payload' => $flexiMsgPayload,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Template updated successfully.',
            'payload' => $flexiMsgPayload,
            'response' => $apiResponse,
        ];
    }

    /**
     * Resolve existing template data into a unified array structure.
     *
     * @param  string|WhatsappTemplate|array<string, mixed>  $templateOrKey
     * @return array<string, mixed>
     */
    private function resolveTemplateData(string|WhatsappTemplate|array $templateOrKey): array
    {
        if (is_array($templateOrKey)) {
            return $templateOrKey;
        }

        if ($templateOrKey instanceof WhatsappTemplate) {
            return [
                'id' => $templateOrKey->id,
                'template_key' => $templateOrKey->template_key,
                'template_name' => $templateOrKey->template_name,
                'webhook_url' => $templateOrKey->webhook_url,
                'description' => $templateOrKey->description,
                'is_active' => $templateOrKey->is_active,
            ];
        }

        $db = WhatsappTemplate::where('template_key', $templateOrKey)->first();
        if ($db) {
            return [
                'id' => $db->id,
                'template_key' => $db->template_key,
                'template_name' => $db->template_name,
                'webhook_url' => $db->webhook_url,
                'description' => $db->description,
                'is_active' => $db->is_active,
            ];
        }

        return ['template_key' => $templateOrKey];
    }

    /**
     * Extract the header component from a template data structure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractHeaderComponent(array $data): array
    {
        if (isset($data['header']) && is_array($data['header'])) {
            return $data['header'];
        }

        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $component) {
                if (is_array($component) && strtoupper((string) ($component['type'] ?? '')) === 'HEADER') {
                    return $component;
                }
            }
        }

        if (isset($data['header_type']) || isset($data['header_format']) || isset($data['header_media_url']) || isset($data['header_image_url'])) {
            return [
                'type' => 'HEADER',
                'format' => $data['header_format'] ?? ($data['header_type'] ?? 'IMAGE'),
                'media_url' => $data['header_media_url'] ?? ($data['header_image_url'] ?? ($data['media_url'] ?? null)),
            ];
        }

        return [];
    }

    /**
     * Extract media URL from a header component structure.
     *
     * @param  array<string, mixed>  $header
     */
    private function extractMediaUrlFromHeader(array $header): ?string
    {
        $candidates = [
            $header['media_url'] ?? null,
            $header['url'] ?? null,
            $header['header_media_url'] ?? null,
            $header['header_image_url'] ?? null,
            $header['image_url'] ?? null,
            $header['example']['header_url'][0] ?? null,
            $header['example']['header_handle'][0] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
