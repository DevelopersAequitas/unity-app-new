<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Services\CountryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CountryAndCityApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('cities');

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code')->nullable();
            $table->timestamps();
        });
    }

    public function test_countries_api_returns_complete_country_list_with_dial_codes_and_flags(): void
    {
        $response = $this->getJson('/api/v1/countries');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Countries fetched successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'name',
                        'code',
                        'dial_code',
                        'flag',
                    ],
                ],
            ]);

        $countries = collect($response->json('data'));

        // Assert comprehensive worldwide coverage (over 200 countries)
        $this->assertGreaterThan(200, $countries->count());

        // Check India
        $india = $countries->firstWhere('code', 'IN');
        $this->assertNotNull($india);
        $this->assertSame('India', $india['name']);
        $this->assertSame('+91', $india['dial_code']);
        $this->assertSame('🇮🇳', $india['flag']);

        // Check United States
        $us = $countries->firstWhere('code', 'US');
        $this->assertNotNull($us);
        $this->assertSame('United States', $us['name']);
        $this->assertSame('+1', $us['dial_code']);
        $this->assertSame('🇺🇸', $us['flag']);

        // Check United Kingdom
        $uk = $countries->firstWhere('code', 'GB');
        $this->assertNotNull($uk);
        $this->assertSame('United Kingdom', $uk['name']);
        $this->assertSame('+44', $uk['dial_code']);
        $this->assertSame('🇬🇧', $uk['flag']);

        // Check countries previously missing from limited list
        $missingCountries = [
            'AF' => ['Afghanistan', '+93'],
            'AL' => ['Albania', '+355'],
            'DZ' => ['Algeria', '+213'],
            'AD' => ['Andorra', '+376'],
            'AO' => ['Angola', '+244'],
            'AM' => ['Armenia', '+374'],
            'AZ' => ['Azerbaijan', '+994'],
            'BS' => ['Bahamas', '+1242'],
            'BY' => ['Belarus', '+375'],
            'BT' => ['Bhutan', '+975'],
            'BO' => ['Bolivia', '+591'],
            'KH' => ['Cambodia', '+855'],
            'HR' => ['Croatia', '+385'],
            'CY' => ['Cyprus', '+357'],
            'EC' => ['Ecuador', '+593'],
            'ET' => ['Ethiopia', '+251'],
            'FI' => ['Finland', '+358'],
            'IS' => ['Iceland', '+354'],
            'JM' => ['Jamaica', '+1876'],
            'MV' => ['Maldives', '+960'],
            'MM' => ['Myanmar', '+95'],
            'ZW' => ['Zimbabwe', '+263'],
        ];

        foreach ($missingCountries as $code => [$expectedName, $expectedDialCode]) {
            $item = $countries->firstWhere('code', $code);
            $this->assertNotNull($item, "Expected country [{$code} - {$expectedName}] was missing from API response.");
            $this->assertSame($expectedName, $item['name']);
            $this->assertSame($expectedDialCode, $item['dial_code']);
            $this->assertNotEmpty($item['flag']);
        }

        // Verify no duplicate country codes
        $codes = $countries->pluck('code');
        $this->assertSame($codes->count(), $codes->unique()->count());

        // Verify sorted alphabetically
        $names = $countries->pluck('name')->all();
        $sortedNames = $names;
        usort($sortedNames, fn (string $a, string $b): int => strcasecmp($a, $b));
        $this->assertSame($sortedNames, $names);
    }

    public function test_countries_api_search_filter(): void
    {
        $response = $this->getJson('/api/v1/countries?search=united');

        $response->assertOk();
        $countries = collect($response->json('data'));

        $this->assertTrue($countries->contains('name', 'United States'));
        $this->assertTrue($countries->contains('name', 'United Kingdom'));
        $this->assertTrue($countries->contains('name', 'United Arab Emirates'));
        $this->assertFalse($countries->contains('name', 'India'));

        // Search by dial code
        $dialResponse = $this->getJson('/api/v1/countries?search=+91');
        $dialResponse->assertOk();
        $dialCountries = collect($dialResponse->json('data'));
        $this->assertTrue($dialCountries->contains('code', 'IN'));

        // Search by country code
        $codeResponse = $this->getJson('/api/v1/countries?search=IN');
        $codeResponse->assertOk();
        $codeCountries = collect($codeResponse->json('data'));
        $this->assertTrue($codeCountries->contains('code', 'IN'));

        // Search returns empty list when no match is found
        $emptyResponse = $this->getJson('/api/v1/countries?search=NonExistentCountryXYZ123');
        $emptyResponse->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_countries_api_caches_country_list_and_handles_safe_dial_code(): void
    {
        $countryService = app(CountryService::class);
        $countryService->clearCache();

        $this->assertFalse(Cache::has(CountryService::CACHE_KEY));

        $response = $this->getJson('/api/v1/countries');
        $response->assertOk();

        $this->assertTrue(Cache::has(CountryService::CACHE_KEY));

        // Antarctica (AQ) has no calling code, should be handled safely as null without throwing errors
        $countries = collect($response->json('data'));
        $antarctica = $countries->firstWhere('code', 'AQ');
        $this->assertNotNull($antarctica);
        $this->assertNull($antarctica['dial_code']);
        $this->assertSame('Antarctica', $antarctica['name']);
    }

    public function test_cities_api_returns_city_state_code_country_code_format(): void
    {
        City::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Surat',
            'state' => 'Gujarat',
            'country' => 'India',
            'country_code' => 'IN',
        ]);

        City::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'country_code' => 'IN',
        ]);

        $response = $this->getJson('/api/v1/cities?search=Surat');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'state',
                            'state_code',
                            'country',
                            'country_code',
                            'formatted_location',
                            'display_name',
                        ],
                    ],
                    'pagination',
                ],
            ]);

        $firstItem = $response->json('data.items.0');
        $this->assertEquals('Surat', $firstItem['name']);
        $this->assertEquals('GJ', $firstItem['state_code']);
        $this->assertEquals('IN', $firstItem['country_code']);
        $this->assertEquals('Surat, GJ, IN', $firstItem['formatted_location']);
    }
}
