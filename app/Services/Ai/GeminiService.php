<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
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

        $selectedModel = $model ?: (string) config('services.gemini.model', 'gemini-1.5-flash');
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
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message') ?? 'Unknown Gemini API error';
            Log::warning('GeminiService.generateContent: request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            throw new RuntimeException("Gemini API call failed: {$errorMessage}");
        }

        $candidates = $response->json('candidates') ?? [];
        $text = $candidates[0]['content']['parts'][0]['text'] ?? null;

        if (blank($text)) {
            throw new RuntimeException('Gemini API returned an empty text response.');
        }

        return (string) $text;
    }
}
