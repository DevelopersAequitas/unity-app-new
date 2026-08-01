<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppChangelog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AppReleaseService
{
    /**
     * Create and persist a new App Release / Changelog.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRelease(array $data): AppChangelog
    {
        $platform = $this->normalizePlatform($data['platform'] ?? []);
        $features = $this->normalizeFeatures($data['features'] ?? null);

        $isReleased = filter_var($data['is_released'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $releasedAt = null;
        if (! empty($data['released_at'])) {
            $releasedAt = Carbon::parse($data['released_at']);
        } elseif ($isReleased) {
            $releasedAt = now();
        }

        return AppChangelog::create([
            'id' => (string) Str::uuid(),
            'version' => trim((string) ($data['version'] ?? '')),
            'platform' => $platform,
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'features' => $features,
            'is_released' => $isReleased,
            'released_at' => $releasedAt,
        ]);
    }

    /**
     * Normalize platform input into a clean array of platform strings.
     *
     * @return array<int, string>
     */
    private function normalizePlatform(mixed $platformInput): array
    {
        if (is_array($platformInput)) {
            $platforms = array_map(fn ($p) => trim((string) $p), $platformInput);

            return array_values(array_filter($platforms, fn ($p) => $p !== ''));
        }

        if (is_string($platformInput) && trim($platformInput) !== '') {
            $parts = explode(',', $platformInput);
            $platforms = array_map(fn ($p) => trim($p), $parts);

            return array_values(array_filter($platforms, fn ($p) => $p !== ''));
        }

        return [];
    }

    /**
     * Normalize features input (array or multiline string) into a list of feature strings.
     *
     * @return array<int, string>
     */
    private function normalizeFeatures(mixed $featuresInput): array
    {
        if (is_array($featuresInput)) {
            $features = array_map(fn ($f) => trim((string) $f), $featuresInput);

            return array_values(array_filter($features, fn ($f) => $f !== ''));
        }

        if (is_string($featuresInput) && trim($featuresInput) !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $featuresInput);
            if (is_array($lines)) {
                $features = array_map(fn ($l) => trim($l), $lines);

                return array_values(array_filter($features, fn ($f) => $f !== ''));
            }
        }

        return [];
    }
}
