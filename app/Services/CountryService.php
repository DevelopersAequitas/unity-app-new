<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PragmaRX\Countries\Package\Countries;
use Throwable;

class CountryService
{
    public const CACHE_KEY = 'worldwide_countries_list:v1';

    /**
     * Fallback countries in case the third-party package fails to load.
     *
     * @var array<int, array{name: string, code: string, dial_code: string, flag: string}>
     */
    private const FALLBACK_COUNTRIES = [
        ['name' => 'India', 'code' => 'IN', 'dial_code' => '+91', 'flag' => '🇮🇳'],
        ['name' => 'United States', 'code' => 'US', 'dial_code' => '+1', 'flag' => '🇺🇸'],
        ['name' => 'United Kingdom', 'code' => 'GB', 'dial_code' => '+44', 'flag' => '🇬🇧'],
        ['name' => 'United Arab Emirates', 'code' => 'AE', 'dial_code' => '+971', 'flag' => '🇦🇪'],
        ['name' => 'Canada', 'code' => 'CA', 'dial_code' => '+1', 'flag' => '🇨🇦'],
        ['name' => 'Australia', 'code' => 'AU', 'dial_code' => '+61', 'flag' => '🇦🇺'],
        ['name' => 'Singapore', 'code' => 'SG', 'dial_code' => '+65', 'flag' => '🇸🇬'],
    ];

    /**
     * Get worldwide countries, optionally filtered by a search query.
     *
     * @return array<int, array{name: string, code: string, dial_code: ?string, flag: ?string}>
     */
    public function getCountries(?string $search = null): array
    {
        $countries = $this->getAllCountries();

        $search = strtolower(trim((string) $search));
        if ($search === '') {
            return $countries;
        }

        $searchNoPlus = str_replace('+', '', $search);

        return array_values(array_filter($countries, function (array $country) use ($search, $searchNoPlus): bool {
            $nameMatches = str_contains(strtolower($country['name']), $search);
            $codeMatches = str_contains(strtolower($country['code']), $search);
            $dialMatches = $country['dial_code'] !== null
                && str_contains(str_replace('+', '', $country['dial_code']), $searchNoPlus);

            return $nameMatches || $codeMatches || $dialMatches;
        }));
    }

    /**
     * Get paginated worldwide countries, optionally filtered by search query.
     *
     * @return array{
     *     items: array<int, array{name: string, code: string, dial_code: ?string, flag: ?string}>,
     *     pagination: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int
     *     }
     * }
     */
    public function getPaginatedCountries(?string $search = null, int $page = 1, int $perPage = 20): array
    {
        $countries = $this->getCountries($search);
        $total = count($countries);
        $perPage = max(1, min($perPage, 250));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, $page);

        $offset = ($page - 1) * $perPage;
        $items = array_slice($countries, $offset, $perPage);

        return [
            'items' => array_values($items),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * Retrieve all cached worldwide countries or load them from the package.
     *
     * @return array<int, array{name: string, code: string, dial_code: ?string, flag: ?string}>
     */
    public function getAllCountries(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDays(30), function (): array {
            return $this->loadCountriesFromPackage();
        });
    }

    /**
     * Clear the cached worldwide countries list.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Parse and build worldwide country list from package.
     *
     * @return array<int, array{name: string, code: string, dial_code: ?string, flag: ?string}>
     */
    private function loadCountriesFromPackage(): array
    {
        try {
            $countriesPackage = new Countries;
            $all = $countriesPackage->all();

            $items = [];

            foreach ($all as $country) {
                $code = strtoupper((string) ($country->cca2 ?? ''));
                if (strlen($code) !== 2 || ! ctype_alpha($code)) {
                    continue;
                }

                $name = (string) ($country->name->common ?? $country->name ?? '');
                if ($name === '') {
                    continue;
                }

                $dialCode = $this->resolveDialCode($country);
                $flag = $this->getCountryFlagEmoji($code);

                $items[$code] = [
                    'name' => $name,
                    'code' => $code,
                    'dial_code' => $dialCode,
                    'flag' => $flag,
                ];
            }

            if (empty($items)) {
                return self::FALLBACK_COUNTRIES;
            }

            // Sort alphabetically by country name (case-insensitive)
            usort($items, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return array_values($items);
        } catch (Throwable $e) {
            Log::error('Failed to load worldwide countries from package: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return self::FALLBACK_COUNTRIES;
        }
    }

    /**
     * Resolve calling dial code with leading '+' prefix.
     */
    private function resolveDialCode(object $country): ?string
    {
        // 1. Try canonical dialling calling code (e.g. ['91'] for India, ['1'] for US/CA)
        $callingCodes = $country->dialling->calling_code ?? null;
        if ($callingCodes && is_iterable($callingCodes)) {
            foreach ($callingCodes as $codeItem) {
                $cleaned = trim((string) $codeItem);
                if ($cleaned !== '') {
                    return str_starts_with($cleaned, '+') ? $cleaned : '+'.$cleaned;
                }
            }
        }

        // 2. Try calling_codes array
        if (isset($country->calling_codes) && is_iterable($country->calling_codes)) {
            foreach ($country->calling_codes as $codeItem) {
                $cleaned = trim((string) $codeItem);
                if ($cleaned !== '') {
                    return str_starts_with($cleaned, '+') ? $cleaned : '+'.$cleaned;
                }
            }
        }

        // 3. Try IDD root + suffix
        if (isset($country->idd->root)) {
            $root = trim((string) $country->idd->root);
            if ($root !== '') {
                $suffix = '';
                if (isset($country->idd->suffixes) && is_iterable($country->idd->suffixes)) {
                    $suffixes = is_array($country->idd->suffixes)
                        ? $country->idd->suffixes
                        : iterator_to_array($country->idd->suffixes);

                    if (count($suffixes) === 1) {
                        $suffix = (string) reset($suffixes);
                    }
                }

                $dialCode = $root.$suffix;

                return str_starts_with($dialCode, '+') ? $dialCode : '+'.$dialCode;
            }
        }

        return null;
    }

    /**
     * Convert ISO 3166-1 alpha-2 code to emoji flag using Regional Indicator Symbols.
     */
    private function getCountryFlagEmoji(string $countryCode): ?string
    {
        if (strlen($countryCode) !== 2 || ! ctype_alpha($countryCode)) {
            return null;
        }

        $upper = strtoupper($countryCode);

        return mb_chr(127397 + ord($upper[0]), 'UTF-8').mb_chr(127397 + ord($upper[1]), 'UTF-8');
    }
}
