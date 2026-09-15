<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportGlobalCitiesCommand extends Command
{
    protected $signature = 'cities:import-global {--force : Force re-import of all global cities}';

    protected $description = 'Import worldwide cities from geographical dataset into cities table.';

    public function handle(): int
    {
        $this->info('Starting Global Cities Import...');

        $citiesPath = base_path('vendor/pragmarx/countries/src/data/cities/default');
        $statesPath = base_path('vendor/pragmarx/countries/src/data/states/default');

        if (! File::isDirectory($citiesPath)) {
            $this->error("Cities data directory not found at: {$citiesPath}");

            return self::FAILURE;
        }

        // Build state name -> state code lookup map across countries
        $stateCodeMap = $this->buildStateCodeMap($statesPath);

        // Fetch all existing city signatures from database to prevent duplicate inserts
        $existing = DB::table('cities')
            ->select(['name', 'country_code', 'state'])
            ->get()
            ->mapWithKeys(function ($item): array {
                $key = $this->makeCityKey((string) $item->name, (string) $item->country_code, (string) $item->state);

                return [$key => true];
            })
            ->all();

        $this->info('Existing cities in database: '.count($existing));

        $cityFiles = File::glob("{$citiesPath}/*.json");
        $this->info('Found '.count($cityFiles).' country city files.');

        $toInsert = [];
        $skipped = 0;
        $added = 0;

        foreach ($cityFiles as $file) {
            $countryCca3 = strtolower(basename($file, '.json'));

            // Keep existing 4,200 comprehensive Indian cities intact
            if ($countryCca3 === 'ind') {
                continue;
            }

            $content = File::get($file);
            $citiesData = json_decode($content, true);

            if (! is_array($citiesData)) {
                continue;
            }

            foreach ($citiesData as $city) {
                $cityName = trim((string) ($city['name'] ?? $city['nameascii'] ?? ''));
                $countryCode = strtoupper(trim((string) ($city['cca2'] ?? '')));
                $countryName = trim((string) ($city['adm0name'] ?? ''));
                $stateName = trim((string) ($city['adm1name'] ?? ''));

                if ($cityName === '' || $countryCode === '') {
                    continue;
                }

                // Resolve state code
                $stateCode = $this->resolveStateCode($countryCode, $stateName, $stateCodeMap);

                $key = $this->makeCityKey($cityName, $countryCode, $stateName);

                if (isset($existing[$key])) {
                    $skipped++;

                    continue;
                }

                $existing[$key] = true;

                $toInsert[] = [
                    'id' => (string) Str::uuid(),
                    'name' => $cityName,
                    'state' => $stateName !== '' ? $stateName : null,
                    'state_code' => $stateCode,
                    'district' => null,
                    'country' => $countryName !== '' ? $countryName : null,
                    'country_code' => $countryCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $added++;
            }
        }

        // Add guarantee cities if missing from packages
        $guaranteeCities = $this->getGuaranteeCities();
        foreach ($guaranteeCities as $gCity) {
            $key = $this->makeCityKey($gCity['name'], $gCity['country_code'], (string) ($gCity['state'] ?? ''));
            if (! isset($existing[$key])) {
                $existing[$key] = true;
                $toInsert[] = [
                    'id' => (string) Str::uuid(),
                    'name' => $gCity['name'],
                    'state' => $gCity['state'],
                    'state_code' => $gCity['state_code'],
                    'district' => null,
                    'country' => $gCity['country'],
                    'country_code' => $gCity['country_code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $added++;
            }
        }

        $this->info("Prepared {$added} new global cities (skipped {$skipped} duplicates).");

        // Chunk insert to avoid memory/query limit issues
        $chunks = array_chunk($toInsert, 500);
        foreach ($chunks as $chunk) {
            DB::table('cities')->insert($chunk);
        }

        $totalNow = DB::table('cities')->count();
        $this->info("Global cities import completed! Total cities in database: {$totalNow}");

        return self::SUCCESS;
    }

    /**
     * Build state name -> state code lookup map from states directory.
     *
     * @return array<string, array<string, string>> [COUNTRY_CODE => [LOWER_STATE_NAME => STATE_CODE]]
     */
    private function buildStateCodeMap(string $statesPath): array
    {
        $map = [
            'IN' => City::$stateCodes,
            'US' => [
                'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
                'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'florida' => 'FL', 'georgia' => 'GA',
                'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA',
                'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
                'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
                'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH',
                'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY', 'north carolina' => 'NC',
                'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA',
                'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD', 'tennessee' => 'TN',
                'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA',
                'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
            ],
            'AE' => [
                'abu dhabi' => 'AZ', 'dubai' => 'DU', 'sharjah' => 'SH', 'ajman' => 'AJ',
                'ras al khaimah' => 'RK', 'fujairah' => 'FU', 'umm al quwain' => 'UQ',
            ],
            'CA' => [
                'ontario' => 'ON', 'quebec' => 'QC', 'british columbia' => 'BC', 'alberta' => 'AB',
                'manitoba' => 'MB', 'saskatchewan' => 'SK', 'nova scotia' => 'NS', 'new brunswick' => 'NB',
                'newfoundland and labrador' => 'NL', 'prince edward island' => 'PE',
                'northwest territories' => 'NT', 'nunavut' => 'NU', 'yukon' => 'YT',
            ],
            'AU' => [
                'new south wales' => 'NSW', 'victoria' => 'VIC', 'queensland' => 'QLD',
                'western australia' => 'WA', 'south australia' => 'SA', 'tasmania' => 'TAS',
                'australian capital territory' => 'ACT', 'northern territory' => 'NT',
            ],
        ];

        if (! File::isDirectory($statesPath)) {
            return $map;
        }

        foreach (File::glob("{$statesPath}/*.json") as $stateFile) {
            $content = File::get($stateFile);
            $states = json_decode($content, true);
            if (! is_array($states)) {
                continue;
            }

            foreach ($states as $code => $stateInfo) {
                if (! is_array($stateInfo)) {
                    continue;
                }

                $countryCode = strtoupper((string) ($stateInfo['cca2'] ?? ''));
                if ($countryCode === '') {
                    continue;
                }

                $stateName = (string) ($stateInfo['name'] ?? $stateInfo['extra']['admin'] ?? '');
                if ($stateName !== '') {
                    $cleanCode = strtoupper(trim((string) $code));
                    $map[$countryCode][strtolower(trim($stateName))] = $cleanCode;
                }

                if (! empty($stateInfo['alt_names']) && is_array($stateInfo['alt_names'])) {
                    foreach ($stateInfo['alt_names'] as $alt) {
                        if (is_string($alt) && trim($alt) !== '') {
                            $map[$countryCode][strtolower(trim($alt))] = strtoupper(trim((string) $code));
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Resolve state code for a given country and state name.
     */
    private function resolveStateCode(string $countryCode, string $stateName, array $stateCodeMap): ?string
    {
        if ($stateName === '') {
            return null;
        }

        $lowerState = strtolower($stateName);

        if (isset($stateCodeMap[$countryCode][$lowerState])) {
            return $stateCodeMap[$countryCode][$lowerState];
        }

        // If state name itself is short (2-3 chars), it might already be the code
        if (strlen($stateName) <= 3 && ctype_alpha($stateName)) {
            return strtoupper($stateName);
        }

        return null;
    }

    /**
     * Generate unique signature key for a city.
     */
    private function makeCityKey(string $name, string $countryCode, string $state): string
    {
        return strtolower(trim($name)).'|'.strtoupper(trim($countryCode)).'|'.strtolower(trim($state));
    }

    /**
     * Guarantee essential worldwide cities are included.
     *
     * @return array<int, array{name: string, country: string, country_code: string, state: ?string, state_code: ?string}>
     */
    private function getGuaranteeCities(): array
    {
        return [
            // UAE
            ['name' => 'Dubai', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Dubai', 'state_code' => 'DU'],
            ['name' => 'Abu Dhabi', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Abu Dhabi', 'state_code' => 'AZ'],
            ['name' => 'Sharjah', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Sharjah', 'state_code' => 'SH'],
            ['name' => 'Ajman', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Ajman', 'state_code' => 'AJ'],
            ['name' => 'Ras Al Khaimah', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Ras Al Khaimah', 'state_code' => 'RK'],
            ['name' => 'Fujairah', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Fujairah', 'state_code' => 'FU'],
            ['name' => 'Umm Al Quwain', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'state' => 'Umm Al Quwain', 'state_code' => 'UQ'],

            // United Kingdom
            ['name' => 'London', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'England', 'state_code' => 'ENG'],
            ['name' => 'Manchester', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'England', 'state_code' => 'ENG'],
            ['name' => 'Birmingham', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'England', 'state_code' => 'ENG'],
            ['name' => 'Edinburgh', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'Scotland', 'state_code' => 'SCT'],
            ['name' => 'Glasgow', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'Scotland', 'state_code' => 'SCT'],
            ['name' => 'Liverpool', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'England', 'state_code' => 'ENG'],
            ['name' => 'Leeds', 'country' => 'United Kingdom', 'country_code' => 'GB', 'state' => 'England', 'state_code' => 'ENG'],

            // United States
            ['name' => 'New York', 'country' => 'United States', 'country_code' => 'US', 'state' => 'New York', 'state_code' => 'NY'],
            ['name' => 'Los Angeles', 'country' => 'United States', 'country_code' => 'US', 'state' => 'California', 'state_code' => 'CA'],
            ['name' => 'Chicago', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Illinois', 'state_code' => 'IL'],
            ['name' => 'Houston', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Texas', 'state_code' => 'TX'],
            ['name' => 'Phoenix', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Arizona', 'state_code' => 'AZ'],
            ['name' => 'Philadelphia', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Pennsylvania', 'state_code' => 'PA'],
            ['name' => 'San Antonio', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Texas', 'state_code' => 'TX'],
            ['name' => 'San Diego', 'country' => 'United States', 'country_code' => 'US', 'state' => 'California', 'state_code' => 'CA'],
            ['name' => 'Dallas', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Texas', 'state_code' => 'TX'],
            ['name' => 'San Jose', 'country' => 'United States', 'country_code' => 'US', 'state' => 'California', 'state_code' => 'CA'],
            ['name' => 'Austin', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Texas', 'state_code' => 'TX'],
            ['name' => 'San Francisco', 'country' => 'United States', 'country_code' => 'US', 'state' => 'California', 'state_code' => 'CA'],
            ['name' => 'Seattle', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Washington', 'state_code' => 'WA'],
            ['name' => 'Denver', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Colorado', 'state_code' => 'CO'],
            ['name' => 'Boston', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Massachusetts', 'state_code' => 'MA'],
            ['name' => 'Las Vegas', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Nevada', 'state_code' => 'NV'],
            ['name' => 'Miami', 'country' => 'United States', 'country_code' => 'US', 'state' => 'Florida', 'state_code' => 'FL'],

            // Singapore
            ['name' => 'Singapore', 'country' => 'Singapore', 'country_code' => 'SG', 'state' => 'Singapore', 'state_code' => 'SG'],

            // Canada
            ['name' => 'Toronto', 'country' => 'Canada', 'country_code' => 'CA', 'state' => 'Ontario', 'state_code' => 'ON'],
            ['name' => 'Vancouver', 'country' => 'Canada', 'country_code' => 'CA', 'state' => 'British Columbia', 'state_code' => 'BC'],
            ['name' => 'Montreal', 'country' => 'Canada', 'country_code' => 'CA', 'state' => 'Quebec', 'state_code' => 'QC'],

            // Australia
            ['name' => 'Sydney', 'country' => 'Australia', 'country_code' => 'AU', 'state' => 'New South Wales', 'state_code' => 'NSW'],
            ['name' => 'Melbourne', 'country' => 'Australia', 'country_code' => 'AU', 'state' => 'Victoria', 'state_code' => 'VIC'],
            ['name' => 'Brisbane', 'country' => 'Australia', 'country_code' => 'AU', 'state' => 'Queensland', 'state_code' => 'QLD'],

            // Germany
            ['name' => 'Berlin', 'country' => 'Germany', 'country_code' => 'DE', 'state' => 'Berlin', 'state_code' => 'BE'],
            ['name' => 'Munich', 'country' => 'Germany', 'country_code' => 'DE', 'state' => 'Bavaria', 'state_code' => 'BY'],
            ['name' => 'Frankfurt', 'country' => 'Germany', 'country_code' => 'DE', 'state' => 'Hesse', 'state_code' => 'HE'],

            // France
            ['name' => 'Paris', 'country' => 'France', 'country_code' => 'FR', 'state' => 'Île-de-France', 'state_code' => 'IDF'],

            // Japan
            ['name' => 'Tokyo', 'country' => 'Japan', 'country_code' => 'JP', 'state' => 'Tokyo', 'state_code' => '13'],

            // Kenya
            ['name' => 'Nairobi', 'country' => 'Kenya', 'country_code' => 'KE', 'state' => 'Nairobi', 'state_code' => '30'],

            // South Africa
            ['name' => 'Johannesburg', 'country' => 'South Africa', 'country_code' => 'ZA', 'state' => 'Gauteng', 'state_code' => 'GP'],
            ['name' => 'Cape Town', 'country' => 'South Africa', 'country_code' => 'ZA', 'state' => 'Western Cape', 'state_code' => 'WC'],
        ];
    }
}
