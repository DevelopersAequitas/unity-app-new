<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappTemplate;
use InvalidArgumentException;

class WhatsAppTemplatePayloadBuilder
{
    /**
     * Mapping of template_key to Meta Cloud API template configuration.
     *
     * @var array<string, array{
     *     meta_name: string,
     *     fallback_meta_names?: list<string>,
     *     language: string,
     *     category: string,
     *     has_header_media: bool,
     *     header_media_type?: string,
     *     body_param_keys: list<string>,
     *     has_copy_code_button?: bool
     * }>
     */
    private const MIGRATED_TEMPLATES = [
        'otp_verification' => [
            'meta_name' => 'otp_verification',
            'language' => 'en_IN',
            'category' => 'AUTHENTICATION',
            'has_header_media' => false,
            'body_param_keys' => ['code'],
            'has_copy_code_button' => true,
        ],
    ];

    /**
     * Determine if a template key is configured for WhatsApp Cloud API direct delivery.
     */
    public static function isMigrated(string $templateKey): bool
    {
        return isset(self::MIGRATED_TEMPLATES[$templateKey]);
    }

    /**
     * Get list of all migrated template keys.
     *
     * @return list<string>
     */
    public static function getMigratedKeys(): array
    {
        return array_keys(self::MIGRATED_TEMPLATES);
    }

    /**
     * Normalize a phone number to standard E.164 digits without plus sign.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = preg_replace('/[^\d]/', '', $phone);
        if (empty($cleaned)) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Resolve Meta template definition including DB overrides if available.
     *
     * @return array{
     *     meta_name: string,
     *     language: string,
     *     category: string,
     *     has_header_media: bool,
     *     header_media_type?: string,
     *     body_param_keys: list<string>,
     *     has_copy_code_button: bool,
     *     db_template: ?WhatsappTemplate
     * }
     */
    public static function resolveTemplateDefinition(string $templateKey): array
    {
        if (! self::isMigrated($templateKey)) {
            throw new InvalidArgumentException("Template '{$templateKey}' is not configured for WhatsApp Cloud API migration.");
        }

        $config = self::MIGRATED_TEMPLATES[$templateKey];
        $candidateNames = array_merge([$config['meta_name']], $config['fallback_meta_names'] ?? []);

        // Find approved Meta template in database
        $dbTemplate = WhatsappTemplate::query()
            ->whereIn('name', $candidateNames)
            ->whereIn('status', ['APPROVED', 'ACTIVE'])
            ->first();

        if (! $dbTemplate) {
            $dbTemplate = WhatsappTemplate::query()
                ->whereIn('name', $candidateNames)
                ->first();
        }

        if (! $dbTemplate) {
            $dbTemplate = WhatsappTemplate::query()
                ->where('template_key', $templateKey)
                ->first();
        }

        $metaName = $dbTemplate?->name ?: $config['meta_name'];
        $language = $dbTemplate?->language ?: $config['language'];
        $category = $dbTemplate?->category ?: $config['category'];

        return [
            'meta_name' => $metaName,
            'language' => $language,
            'category' => $category,
            'has_header_media' => $config['has_header_media'] ?? false,
            'header_media_type' => $config['header_media_type'] ?? null,
            'body_param_keys' => $config['body_param_keys'],
            'has_copy_code_button' => (bool) ($config['has_copy_code_button'] ?? false),
            'db_template' => $dbTemplate,
        ];
    }

    /**
     * Build the standard Meta Cloud API request payload for a template message.
     *
     * @param  string|array<string, mixed>  $phoneOrPayload
     * @param  array<string, mixed>  $rawPayload
     * @return array{
     *     messaging_product: string,
     *     recipient_type: string,
     *     to: string,
     *     type: string,
     *     template: array{
     *         name: string,
     *         language: array{code: string},
     *         components: list<array<string, mixed>>
     *     },
     *     extracted_variables: array<string, mixed>,
     *     sanitized_variables: array<string, mixed>
     * }
     */
    public static function build(string $templateKey, string|array $phoneOrPayload, array $rawPayload = []): array
    {
        $normalizedPhone = '';
        if (is_array($phoneOrPayload)) {
            $rawPayload = $phoneOrPayload;
            $phone = (string) ($rawPayload['phone'] ?? $rawPayload['mobile'] ?? '');
            $normalizedPhone = self::normalizePhone($phone) ?: '';
        } else {
            $normalizedPhone = (string) $phoneOrPayload;
        }

        $def = self::resolveTemplateDefinition($templateKey);
        $extracted = self::extractVariables($def, $rawPayload);
        $components = [];

        // 1. HEADER COMPONENT (Media: image or video)
        if ($def['has_header_media']) {
            $mediaType = strtolower((string) ($def['header_media_type'] ?? 'image'));
            $mediaUrl = $extracted['header_media_url'] ?? $extracted['media_url'] ?? null;

            if (is_string($mediaUrl) && trim($mediaUrl) !== '') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => $mediaType,
                            $mediaType => [
                                'link' => trim($mediaUrl),
                            ],
                        ],
                    ],
                ];
            }
        }

        // 2. BODY COMPONENT
        $bodyParameters = [];
        foreach ($def['body_param_keys'] as $paramKey) {
            $val = (string) ($extracted[$paramKey] ?? '');
            $bodyParameters[] = [
                'type' => 'text',
                'text' => $val,
            ];
        }

        if (! empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        // 3. BUTTON COMPONENT (e.g. Copy Code for OTP authentication)
        if ($def['has_copy_code_button']) {
            $codeVal = (string) ($extracted['code'] ?? $extracted['otp'] ?? '');
            if ($codeVal !== '') {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $codeVal,
                        ],
                    ],
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'template',
            'template' => [
                'name' => $def['meta_name'],
                'language' => [
                    'code' => $def['language'],
                ],
                'components' => $components,
            ],
        ];

        $sanitized = self::sanitizeVariables($extracted);

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'template',
            'template' => $payload['template'],
            'extracted_variables' => $extracted,
            'sanitized_variables' => $sanitized,
        ];
    }

    /**
     * Extract dynamic variables from incoming payload based on template definition.
     *
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    public static function extractVariables(array $def, array $rawPayload): array
    {
        $normalized = [];
        foreach ($rawPayload as $k => $v) {
            $cleanKey = ltrim((string) $k, '@');
            $normalized[$cleanKey] = $v;
            $normalized[(string) $k] = $v;
        }

        $result = [];

        // 1. First Name resolution
        if (in_array('first_name', $def['body_param_keys'], true)) {
            $firstName = $normalized['first_name']
                ?? $normalized['FirstName']
                ?? $normalized['participant_name']
                ?? $normalized['name']
                ?? $normalized['Name']
                ?? $normalized['1']
                ?? '';

            if (is_string($firstName)) {
                $parts = explode(' ', trim($firstName));
                $firstName = $parts[0] ?? $firstName;
            }
            $result['first_name'] = trim((string) $firstName);
        }

        // 2. OTP Code resolution
        if (in_array('code', $def['body_param_keys'], true) || ($def['has_copy_code_button'] ?? false)) {
            $code = $normalized['code']
                ?? $normalized['otp']
                ?? $normalized['verification_code']
                ?? $normalized['1']
                ?? '';
            $result['code'] = trim((string) $code);
        }

        // 3. Profile Completion Percentage resolution
        if (in_array('profile_percent', $def['body_param_keys'], true)) {
            $percent = $normalized['profile_percent']
                ?? $normalized['profile_completion']
                ?? $normalized['profile_completion_percentage']
                ?? $normalized['completion_percentage']
                ?? $normalized['2']
                ?? '0';
            $result['profile_percent'] = (string) $percent;
        }

        // 4. Circle Name resolution
        if (in_array('circle_name', $def['body_param_keys'], true)) {
            $circle = $normalized['circle_name']
                ?? $normalized['circle']
                ?? $normalized['2']
                ?? 'General';
            $result['circle_name'] = trim((string) $circle);
        }

        // 5. Event Registration fields resolution
        if (in_array('event_name', $def['body_param_keys'], true)) {
            $result['event_name'] = trim((string) ($normalized['event_name'] ?? 'Peers Global Event'));
            $result['registration_id'] = trim((string) ($normalized['registration_id'] ?? ''));
            $result['participant_name'] = trim((string) ($normalized['participant_name'] ?? $normalized['name'] ?? 'Valued Guest'));
            $result['event_date'] = trim((string) ($normalized['event_date'] ?? 'TBA'));
            $result['event_time'] = trim((string) ($normalized['event_time'] ?? 'TBA'));
            $result['venue'] = trim((string) ($normalized['venue'] ?? 'As announced'));
            $result['entry_pass_url'] = trim((string) (
                $normalized['entry_pass_url']
                ?? $normalized['digital_entry_pass_url']
                ?? $normalized['qr_code_url']
                ?? 'https://peersglobal.com'
            ));
        }

        // 6. Header Media URL resolution (Image or Video)
        if ($def['has_header_media']) {
            $mediaUrl = $normalized['header_media_url']
                ?? $normalized['media_url']
                ?? $normalized['creative_url']
                ?? $normalized['welcome_creative_url']
                ?? $normalized['badge_image_url']
                ?? $normalized['qr_code_url']
                ?? $normalized['image_url']
                ?? $normalized['image']
                ?? null;

            if (is_string($mediaUrl) && trim($mediaUrl) !== '') {
                $result['header_media_url'] = trim($mediaUrl);
                $result['media_url'] = trim($mediaUrl);
            } elseif (! empty($def['db_template']?->components) && is_array($def['db_template']->components)) {
                foreach ($def['db_template']->components as $comp) {
                    if (strtoupper((string) ($comp['type'] ?? '')) === 'HEADER') {
                        $exampleUrl = $comp['example']['header_handle'][0] ?? null;
                        if (is_string($exampleUrl) && trim($exampleUrl) !== '') {
                            $result['header_media_url'] = trim($exampleUrl);
                            $result['media_url'] = trim($exampleUrl);
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Mask sensitive variables such as OTP codes for safe logging.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public static function sanitizeVariables(array $variables): array
    {
        $sanitized = [];
        $sensitiveKeys = ['code', 'otp', 'password', 'token', 'secret'];

        foreach ($variables as $k => $v) {
            $lower = strtolower((string) $k);
            if (in_array($lower, $sensitiveKeys, true) || str_contains($lower, 'otp') || str_contains($lower, 'secret')) {
                $sanitized[$k] = '******';
            } elseif (is_array($v)) {
                $sanitized[$k] = self::sanitizeVariables($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize Meta request payload by masking sensitive parameters in body and buttons.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizePayload(array $payload, string $templateKey): array
    {
        $sanitized = $payload;

        if (isset($sanitized['template']['components']) && is_array($sanitized['template']['components'])) {
            $isOtp = str_contains(strtolower($templateKey), 'otp');
            foreach ($sanitized['template']['components'] as $idx => $component) {
                if ($isOtp && isset($component['parameters']) && is_array($component['parameters'])) {
                    foreach ($component['parameters'] as $pIdx => $param) {
                        if (($param['type'] ?? '') === 'text' && ! empty($param['text'])) {
                            $sanitized['template']['components'][$idx]['parameters'][$pIdx]['text'] = '******';
                        }
                    }
                }
            }
        }

        return $sanitized;
    }
}
