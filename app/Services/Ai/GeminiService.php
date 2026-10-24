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
        'gemini-1.5-flash' => 'gemini-3.1-flash-lite',
        'gemini-1.5-flash-latest' => 'gemini-3.1-flash-lite',
        'gemini-1.5-pro' => 'gemini-3.1-flash-lite',
        'gemini-1.5-pro-latest' => 'gemini-3.1-flash-lite',
        'gemini-2.5-flash' => 'gemini-3.1-flash-lite',
        'gemini-2.5-pro' => 'gemini-3.1-flash-lite',
        'gemini-pro' => 'gemini-3.1-flash-lite',
        'gemini-3.1-pro-preview' => 'gemini-3.1-flash-lite',
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

        $configuredDefault = (string) config('services.gemini.model', 'gemini-3.1-flash-lite');
        $rawModel = $model ?: $configuredDefault;
        $normalizedModel = str_starts_with($rawModel, 'models/') ? substr($rawModel, 7) : $rawModel;
        $primaryModel = self::LEGACY_MODEL_MAP[$normalizedModel] ?? $normalizedModel;

        // Models requested by user: primary gemini-3.1-flash-lite, secondary gemini-3-flash-preview
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-3.1-flash-lite',
            'gemini-3-flash-preview',
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
}
