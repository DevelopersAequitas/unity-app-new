<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    /**
     * Legacy to latest model mapping to prevent 404/deprecation errors.
     *
     * @var array<string, string>
     */
    private const LEGACY_MODEL_MAP = [
        'gemini-1.5-flash' => 'gemini-2.5-flash',
        'gemini-1.5-flash-latest' => 'gemini-2.5-flash',
        'gemini-1.5-pro' => 'gemini-2.5-flash',
        'gemini-1.5-pro-latest' => 'gemini-2.5-flash',
        'gemini-pro' => 'gemini-2.5-flash',
    ];

    /**
     * Generate content using the Google Gemini REST API.
     *
     * @throws RuntimeException
     */
    public function generateContent(
        string $prompt,
        ?string $systemInstruction = null,
        ?string $model = null,
        float $temperature = 0.2,
        bool $jsonMode = true
    ): string {
        $apiKey = (string) config('services.gemini.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.');
        }

        $configuredDefault = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $rawModel = $model ?: $configuredDefault;
        $normalizedModel = str_starts_with($rawModel, 'models/') ? substr($rawModel, 7) : $rawModel;
        $primaryModel = self::LEGACY_MODEL_MAP[$normalizedModel] ?? $normalizedModel;

        // Fallback chain as per Gemini 2.5 architecture
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-pro',
            'gemini-3.1-flash-lite',
        ]));

        $lastError = null;

        foreach ($modelsToTry as $selectedModel) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$selectedModel}:generateContent?key={$apiKey}";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                ],
            ];

            if ($jsonMode) {
                $payload['generationConfig']['responseMimeType'] = 'application/json';
            }

            if (filled($systemInstruction)) {
                $payload['systemInstruction'] = [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ];
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $candidates = $response->json('candidates') ?? [];
                $text = $candidates[0]['content']['parts'][0]['text'] ?? null;

                if (blank($text)) {
                    throw new RuntimeException('Gemini API returned an empty text response.');
                }

                $cleanText = trim((string) $text);
                if (str_starts_with($cleanText, '```json')) {
                    $cleanText = substr($cleanText, 7);
                } elseif (str_starts_with($cleanText, '```')) {
                    $cleanText = substr($cleanText, 3);
                }
                if (str_ends_with($cleanText, '```')) {
                    $cleanText = substr($cleanText, 0, -3);
                }

                return trim($cleanText);
            }

            $errorMessage = $response->json('error.message') ?? 'Unknown Gemini API error';
            $statusCode = $response->status();

            Log::warning('GeminiService.generateContent: model attempt failed', [
                'model' => $selectedModel,
                'status' => $statusCode,
                'error' => $errorMessage,
            ]);

            $lastError = $errorMessage;

            // Only retry with next model if this model was not found / not supported / deprecated for generateContent
            $isRetryable = $statusCode === 404
                || str_contains($errorMessage, 'is not found')
                || str_contains($errorMessage, 'not supported')
                || str_contains($errorMessage, 'no longer available');
            if (! $isRetryable) {
                break;
            }
        }

        throw new RuntimeException("Gemini API call failed: {$lastError}");
    }

    /**
     * Generate 768-float embedding vector using Gemini text-embedding-004.
     *
     * @return array<int, float>|null
     */
    public function embedText(string $text): ?array
    {
        $apiKey = (string) config('services.gemini.api_key');
        if (blank($apiKey) || blank(trim($text))) {
            return null;
        }

        try {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key={$apiKey}";
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($endpoint, [
                    'model' => 'models/text-embedding-004',
                    'content' => [
                        'parts' => [
                            ['text' => mb_substr($text, 0, 8000)],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $values = $response->json('embedding.values');
                if (is_array($values) && $values !== []) {
                    return array_map('floatval', $values);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GeminiService.embedText failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Execute multi-turn chat with Gemini Function Calling (Live DB Tools) and return structured JSON.
     *
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>  $functionDeclarations
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $toolExecutor
     * @return array<string, mixed>
     */
    public function generateChatWithTools(
        array $contents,
        ?string $systemInstruction,
        ?string $model,
        float $temperature,
        array $functionDeclarations,
        callable $toolExecutor
    ): array {
        $apiKey = (string) config('services.gemini.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $configuredDefault = (string) config('services.gemini.model', 'gemini-2.0-flash');
        $rawModel = $model ?: $configuredDefault;
        $normalizedModel = str_starts_with($rawModel, 'models/') ? substr($rawModel, 7) : $rawModel;
        $primaryModel = self::LEGACY_MODEL_MAP[$normalizedModel] ?? $normalizedModel;

        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-2.0-flash',
            'gemini-2.5-flash',
        ]));

        $lastError = 'Unknown Gemini API error';

        foreach ($modelsToTry as $selectedModel) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$selectedModel}:generateContent?key={$apiKey}";
            $conversation = $contents;
            $executedResultsSummary = [];

            for ($step = 0; $step < 3; $step++) {
                $payload = [
                    'contents' => $conversation,
                    'generationConfig' => [
                        'temperature' => $temperature,
                    ],
                ];

                if (filled($systemInstruction)) {
                    $payload['systemInstruction'] = [
                        'parts' => [
                            ['text' => $systemInstruction],
                        ],
                    ];
                }

                if ($functionDeclarations !== [] && $step < 2) {
                    $payload['tools'] = [
                        ['functionDeclarations' => $functionDeclarations],
                    ];
                } else {
                    $payload['generationConfig']['responseMimeType'] = 'application/json';
                }

                Log::info('GeminiService.generateChatWithTools: sending request', [
                    'model' => $selectedModel,
                    'step' => $step,
                    'tools_count' => count($functionDeclarations),
                ]);

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    $lastError = (string) ($response->json('error.message') ?? 'Gemini API request failed');
                    $statusCode = $response->status();

                    Log::warning('GeminiService.generateChatWithTools: attempt failed', [
                        'model' => $selectedModel,
                        'step' => $step,
                        'status' => $statusCode,
                        'error' => $lastError,
                    ]);

                    // If a 400 schema/functionCall error occurs, retry cleanly with tool results injected as text context
                    if ($statusCode === 400) {
                        unset($payload['tools']);
                        $payload['generationConfig']['responseMimeType'] = 'application/json';
                        if ($executedResultsSummary !== []) {
                            $fallbackConversation = $contents;
                            $fallbackConversation[] = [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => "LIVE DATABASE RESULTS:\n".json_encode($executedResultsSummary, JSON_UNESCAPED_UNICODE)],
                                ],
                            ];
                            $payload['contents'] = $fallbackConversation;
                        }
                        $response = Http::timeout(30)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'x-goog-api-key' => $apiKey,
                            ])
                            ->post($endpoint, $payload);
                    }

                    if (! $response->successful()) {
                        $lastError = (string) ($response->json('error.message') ?? 'Gemini API request failed');
                        $statusCode = $response->status();
                        $isRetryable = $statusCode === 404
                            || $statusCode === 429
                            || $statusCode >= 500
                            || str_contains($lastError, 'is not found')
                            || str_contains($lastError, 'not supported')
                            || str_contains($lastError, 'quota');
                        if ($isRetryable) {
                            continue 2;
                        }
                        throw new RuntimeException("Gemini API call failed: {$lastError}");
                    }
                }

                $parts = $response->json('candidates.0.content.parts') ?? [];
                $functionCalls = [];
                $textParts = [];
                $normalizedModelParts = [];

                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCalls[] = $part['functionCall'];
                        $part['functionCall']['args'] = (object) (is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : []);
                        $normalizedModelParts[] = $part;
                    } elseif (isset($part['text'])) {
                        $textParts[] = (string) $part['text'];
                        $normalizedModelParts[] = $part;
                    }
                }

                if ($functionCalls !== []) {
                    $conversation[] = [
                        'role' => 'model',
                        'parts' => $normalizedModelParts,
                    ];

                    $responseParts = [];
                    $executedResultsSummary = [];
                    foreach ($functionCalls as $call) {
                        $fnName = (string) ($call['name'] ?? '');
                        $fnArgs = is_array($call['args'] ?? null) ? $call['args'] : [];
                        try {
                            $toolResult = $toolExecutor($fnName, $fnArgs);
                        } catch (\Throwable $toolErr) {
                            Log::error('GeminiService.generateChatWithTools: tool execution error', [
                                'tool' => $fnName,
                                'error' => $toolErr->getMessage(),
                            ]);
                            $toolResult = ['error' => $toolErr->getMessage()];
                        }

                        $executedResultsSummary[$fnName] = $toolResult;
                        $responseParts[] = [
                            'functionResponse' => [
                                'name' => $fnName,
                                'response' => [
                                    'name' => $fnName,
                                    'content' => (object) $toolResult,
                                ],
                            ],
                        ];
                    }

                    $conversation[] = [
                        'role' => 'function',
                        'parts' => $responseParts,
                    ];

                    continue;
                }

                $rawText = trim(implode("\n", $textParts));

                return $this->parseStructuredReply($rawText);
            }
        }

        throw new RuntimeException("Gemini API call failed: {$lastError}");
    }

    /**
     * Parse raw text or JSON from Gemini into the structured Chatbot reply format.
     *
     * @return array{reply_markdown: string, attachments: array<int, mixed>, suggested_follow_ups: array<int, string>}
     */
    private function parseStructuredReply(string $rawText): array
    {
        $clean = trim($rawText);
        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        } elseif (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }
        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (! is_array($decoded) && preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (is_array($decoded) && isset($decoded['reply_markdown'])) {
            return [
                'reply_markdown' => (string) $decoded['reply_markdown'],
                'attachments' => is_array($decoded['attachments'] ?? null) ? $decoded['attachments'] : [],
                'suggested_follow_ups' => is_array($decoded['suggested_follow_ups'] ?? null)
                    ? array_values(array_map('strval', $decoded['suggested_follow_ups']))
                    : [],
            ];
        }

        return [
            'reply_markdown' => $clean !== '' ? $clean : 'I am here to help! Could you please rephrase your question?',
            'attachments' => [],
            'suggested_follow_ups' => [],
        ];
    }

    /**
     * Inspect a profile photo using Gemini Vision to verify if it is an actual photograph
     * of a person rather than a default letter/initials avatar, graphic, or placeholder.
     *
     * @return array{is_real_photo: bool, is_initials_or_placeholder: bool, reason: string}
     */
    public function inspectProfilePhoto(string $imageUrl): array
    {
        $cacheKey = 'avatar_inspection_'.md5($imageUrl);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($imageUrl) {
            $apiKey = (string) config('services.gemini.api_key');
            if (blank($apiKey)) {
                return [
                    'is_real_photo' => true,
                    'is_initials_or_placeholder' => false,
                    'reason' => 'Gemini API key not configured; skipped AI inspection.',
                ];
            }

            try {
                $imageContent = null;
                $mimeType = 'image/jpeg';

                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imgRes = Http::timeout(10)->get($imageUrl);
                    if ($imgRes->successful()) {
                        $imageContent = $imgRes->body();
                        $mimeType = $imgRes->header('Content-Type') ?: 'image/jpeg';
                    }
                } elseif (file_exists($imageUrl)) {
                    $imageContent = file_get_contents($imageUrl);
                    $mimeType = mime_content_type($imageUrl) ?: 'image/jpeg';
                }

                if (! $imageContent) {
                    return [
                        'is_real_photo' => false,
                        'is_initials_or_placeholder' => true,
                        'reason' => 'Unable to download or access image.',
                    ];
                }

                if (str_contains($mimeType, ';')) {
                    $mimeType = trim(explode(';', $mimeType)[0]);
                }

                $base64Data = base64_encode($imageContent);

                $selectedModel = 'gemini-2.5-flash';
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$selectedModel}:generateContent?key={$apiKey}";

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Analyze this profile picture. Determine if it is a real photograph of an actual human person (face/headshot/body). Specifically check if it is just a default placeholder, single or double letter initials on a solid or colored background (like Google or Microsoft default avatar), an illustration, cartoon, icon, or blank placeholder graphic. Respond strictly in valid JSON format: {"is_real_photo": boolean, "is_initials_or_placeholder": boolean, "reason": string}',
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Data,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ];

                $response = Http::timeout(20)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $candidates = $response->json('candidates') ?? [];
                    $text = $candidates[0]['content']['parts'][0]['text'] ?? '';
                    $clean = trim((string) $text);
                    if (str_starts_with($clean, '```json')) {
                        $clean = substr($clean, 7);
                    } elseif (str_starts_with($clean, '```')) {
                        $clean = substr($clean, 3);
                    }
                    if (str_ends_with($clean, '```')) {
                        $clean = substr($clean, 0, -3);
                    }
                    $clean = trim($clean);

                    $decoded = json_decode($clean, true);
                    if (is_array($decoded) && isset($decoded['is_real_photo'])) {
                        return [
                            'is_real_photo' => (bool) $decoded['is_real_photo'],
                            'is_initials_or_placeholder' => (bool) ($decoded['is_initials_or_placeholder'] ?? (! $decoded['is_real_photo'])),
                            'reason' => (string) ($decoded['reason'] ?? 'Gemini evaluated image.'),
                        ];
                    }
                } else {
                    Log::warning('GeminiService.inspectProfilePhoto: API call failed', [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('GeminiService.inspectProfilePhoto error: '.$e->getMessage());
            }

            return [
                'is_real_photo' => true,
                'is_initials_or_placeholder' => false,
                'reason' => 'Inspection could not complete; allowed with fallback.',
            ];
        });
    }
}
