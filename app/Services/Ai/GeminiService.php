<?php

declare(strict_types=1);

namespace App\Services\Ai;

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

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    $lastError = (string) ($response->json('error.message') ?? 'Gemini API request failed');
                    $statusCode = $response->status();
                    $isRetryable = $statusCode === 404
                        || str_contains($lastError, 'is not found')
                        || str_contains($lastError, 'not supported');
                    if ($isRetryable) {
                        continue 2;
                    }
                    throw new RuntimeException("Gemini API call failed: {$lastError}");
                }

                $parts = $response->json('candidates.0.content.parts') ?? [];
                $functionCalls = [];
                $textParts = [];

                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCalls[] = $part['functionCall'];
                    } elseif (isset($part['text'])) {
                        $textParts[] = (string) $part['text'];
                    }
                }

                if ($functionCalls !== []) {
                    $conversation[] = [
                        'role' => 'model',
                        'parts' => $parts,
                    ];

                    $responseParts = [];
                    foreach ($functionCalls as $call) {
                        $fnName = (string) ($call['name'] ?? '');
                        $fnArgs = is_array($call['args'] ?? null) ? $call['args'] : [];
                        $toolResult = $toolExecutor($fnName, $fnArgs);

                        $responseParts[] = [
                            'functionResponse' => [
                                'name' => $fnName,
                                'response' => $toolResult,
                            ],
                        ];
                    }

                    $conversation[] = [
                        'role' => 'user',
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
}
