<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    /**
     * Legacy to latest model mapping to prevent 404 deprecation errors.
     *
     * @var array<string, string>
     */
    private const LEGACY_MODEL_MAP = [
        'gemini-1.5-flash' => 'gemini-2.5-flash',
        'gemini-1.5-flash-latest' => 'gemini-2.5-flash',
        'gemini-1.5-pro' => 'gemini-2.5-pro',
        'gemini-1.5-pro-latest' => 'gemini-2.5-pro',
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
        $primaryModel = self::LEGACY_MODEL_MAP[$rawModel] ?? $rawModel;

        // Candidate models to attempt in order if a 404/not supported error is encountered
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-pro',
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

            // Only retry with next model if this model was not found / not supported for generateContent
            $isNotFound = $statusCode === 404 || str_contains($errorMessage, 'is not found') || str_contains($errorMessage, 'not supported');
            if (! $isNotFound) {
                break;
            }
        }

        throw new RuntimeException("Gemini API call failed: {$lastError}");
    }
}
