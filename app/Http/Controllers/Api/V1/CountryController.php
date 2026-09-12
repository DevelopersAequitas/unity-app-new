<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends BaseApiController
{
    /**
     * Common world countries with ISO-2 codes and international calling dial codes.
     */
    private const COUNTRIES = [
        ['name' => 'India', 'code' => 'IN', 'dial_code' => '+91'],
        ['name' => 'United States', 'code' => 'US', 'dial_code' => '+1'],
        ['name' => 'United Kingdom', 'code' => 'GB', 'dial_code' => '+44'],
        ['name' => 'United Arab Emirates', 'code' => 'AE', 'dial_code' => '+971'],
        ['name' => 'Canada', 'code' => 'CA', 'dial_code' => '+1'],
        ['name' => 'Australia', 'code' => 'AU', 'dial_code' => '+61'],
        ['name' => 'Singapore', 'code' => 'SG', 'dial_code' => '+65'],
        ['name' => 'Germany', 'code' => 'DE', 'dial_code' => '+49'],
        ['name' => 'France', 'code' => 'FR', 'dial_code' => '+33'],
        ['name' => 'Saudi Arabia', 'code' => 'SA', 'dial_code' => '+966'],
        ['name' => 'Qatar', 'code' => 'QA', 'dial_code' => '+974'],
        ['name' => 'Kuwait', 'code' => 'KW', 'dial_code' => '+965'],
        ['name' => 'Oman', 'code' => 'OM', 'dial_code' => '+968'],
        ['name' => 'Bahrain', 'code' => 'BH', 'dial_code' => '+973'],
        ['name' => 'Malaysia', 'code' => 'MY', 'dial_code' => '+60'],
        ['name' => 'Indonesia', 'code' => 'ID', 'dial_code' => '+62'],
        ['name' => 'New Zealand', 'code' => 'NZ', 'dial_code' => '+64'],
        ['name' => 'South Africa', 'code' => 'ZA', 'dial_code' => '+27'],
        ['name' => 'Japan', 'code' => 'JP', 'dial_code' => '+81'],
        ['name' => 'China', 'code' => 'CN', 'dial_code' => '+86'],
        ['name' => 'Hong Kong', 'code' => 'HK', 'dial_code' => '+852'],
        ['name' => 'Italy', 'code' => 'IT', 'dial_code' => '+39'],
        ['name' => 'Spain', 'code' => 'ES', 'dial_code' => '+34'],
        ['name' => 'Netherlands', 'code' => 'NL', 'dial_code' => '+31'],
        ['name' => 'Switzerland', 'code' => 'CH', 'dial_code' => '+41'],
        ['name' => 'Sweden', 'code' => 'SE', 'dial_code' => '+46'],
        ['name' => 'Norway', 'code' => 'NO', 'dial_code' => '+47'],
        ['name' => 'Denmark', 'code' => 'DK', 'dial_code' => '+45'],
        ['name' => 'Ireland', 'code' => 'IE', 'dial_code' => '+353'],
        ['name' => 'Belgium', 'code' => 'BE', 'dial_code' => '+32'],
        ['name' => 'Austria', 'code' => 'AT', 'dial_code' => '+43'],
        ['name' => 'Poland', 'code' => 'PL', 'dial_code' => '+48'],
        ['name' => 'Portugal', 'code' => 'PT', 'dial_code' => '+351'],
        ['name' => 'Greece', 'code' => 'GR', 'dial_code' => '+30'],
        ['name' => 'Turkey', 'code' => 'TR', 'dial_code' => '+90'],
        ['name' => 'Israel', 'code' => 'IL', 'dial_code' => '+972'],
        ['name' => 'Egypt', 'code' => 'EG', 'dial_code' => '+20'],
        ['name' => 'Nigeria', 'code' => 'NG', 'dial_code' => '+234'],
        ['name' => 'Kenya', 'code' => 'KE', 'dial_code' => '+254'],
        ['name' => 'Ghana', 'code' => 'GH', 'dial_code' => '+233'],
        ['name' => 'Tanzania', 'code' => 'TZ', 'dial_code' => '+255'],
        ['name' => 'Uganda', 'code' => 'UG', 'dial_code' => '+256'],
        ['name' => 'Bangladesh', 'code' => 'BD', 'dial_code' => '+880'],
        ['name' => 'Sri Lanka', 'code' => 'LK', 'dial_code' => '+94'],
        ['name' => 'Nepal', 'code' => 'NP', 'dial_code' => '+977'],
        ['name' => 'Pakistan', 'code' => 'PK', 'dial_code' => '+92'],
        ['name' => 'Philippines', 'code' => 'PH', 'dial_code' => '+63'],
        ['name' => 'Thailand', 'code' => 'TH', 'dial_code' => '+66'],
        ['name' => 'Vietnam', 'code' => 'VN', 'dial_code' => '+84'],
        ['name' => 'South Korea', 'code' => 'KR', 'dial_code' => '+82'],
        ['name' => 'Brazil', 'code' => 'BR', 'dial_code' => '+55'],
        ['name' => 'Mexico', 'code' => 'MX', 'dial_code' => '+52'],
        ['name' => 'Argentina', 'code' => 'AR', 'dial_code' => '+54'],
        ['name' => 'Chile', 'code' => 'CL', 'dial_code' => '+56'],
        ['name' => 'Colombia', 'code' => 'CO', 'dial_code' => '+57'],
        ['name' => 'Peru', 'code' => 'PE', 'dial_code' => '+51'],
        ['name' => 'Russia', 'code' => 'RU', 'dial_code' => '+7'],
    ];

    /**
     * Get list of countries with dial codes and flags.
     */
    public function index(Request $request): JsonResponse
    {
        $search = strtolower(trim((string) $request->input('search', '')));

        $items = collect(self::COUNTRIES)
            ->map(function (array $country): array {
                $code = strtoupper($country['code']);

                return [
                    'name' => $country['name'],
                    'code' => $code,
                    'dial_code' => $country['dial_code'],
                    'flag' => $this->getCountryFlagEmoji($code),
                ];
            });

        if ($search !== '') {
            $items = $items->filter(function (array $country) use ($search): bool {
                return str_contains(strtolower($country['name']), $search)
                    || str_contains(strtolower($country['code']), $search)
                    || str_contains(str_replace('+', '', $country['dial_code']), str_replace('+', '', $search));
            })->values();
        }

        return $this->success($items->values()->all(), 'Countries fetched successfully.');
    }

    /**
     * Convert ISO 3166-1 alpha-2 code to emoji flag.
     */
    private function getCountryFlagEmoji(string $countryCode): string
    {
        if (strlen($countryCode) !== 2) {
            return '';
        }

        $upper = strtoupper($countryCode);

        return mb_chr(127397 + ord($upper[0]), 'UTF-8').mb_chr(127397 + ord($upper[1]), 'UTF-8');
    }
}
