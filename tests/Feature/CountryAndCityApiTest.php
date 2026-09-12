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

    public function test_countries_api_returns_paginated_country_list_with_dial_codes_and_flags(): void
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
                    'items' => [
                        '*' => [
                            'name',
                            'code',
                            'dial_code',
                            'flag',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);

        $this->assertSame(1, $response->json('data.pagination.current_page'));
        $this->assertSame(20, $response->json('data.pagination.per_page'));
        $this->assertGreaterThan(200, $response->json('data.pagination.total'));
        $this->assertCount(20, $response->json('data.items'));
    }

    public function test_countries_api_supports_custom_pagination_parameters(): void
    {
        $response = $this->getJson('/api/v1/countries?page=2&per_page=15');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.pagination.current_page'));
        $this->assertSame(15, $response->json('data.pagination.per_page'));
        $this->assertCount(15, $response->json('data.items'));
    }

    public function test_countries_api_supports_non_paginated_all_mode(): void
    {
        $response = $this->getJson('/api/v1/countries?paginate=false');

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

        // Verify no duplicate country codes
        $codes = $countries->pluck('code');
        $this->assertSame($codes->count(), $codes->unique()->count());

        // Verify sorted alphabetically
        $names = $countries->pluck('name')->all();
        $sortedNames = $names;
        usort($sortedNames, fn (string $a, string $b): int => strcasecmp($a, $b));
        $this->assertSame($sortedNames, $names);
    }

    public function test_countries_api_search_filter_with_pagination(): void
    {
        $response = $this->getJson('/api/v1/countries?search=united');

        $response->assertOk();
        $items = collect($response->json('data.items'));

        $this->assertTrue($items->contains('name', 'United States'));
        $this->assertTrue($items->contains('name', 'United Kingdom'));
        $this->assertTrue($items->contains('name', 'United Arab Emirates'));
        $this->assertFalse($items->contains('name', 'India'));

        // Search by dial code
        $dialResponse = $this->getJson('/api/v1/countries?search=+91');
        $dialResponse->assertOk();
        $dialItems = collect($dialResponse->json('data.items'));
        $this->assertTrue($dialItems->contains('code', 'IN'));

        // Search by country code
        $codeResponse = $this->getJson('/api/v1/countries?search=IN');
        $codeResponse->assertOk();
        $codeItems = collect($codeResponse->json('data.items'));
        $this->assertTrue($codeItems->contains('code', 'IN'));

        // Search returns empty list when no match is found
        $emptyResponse = $this->getJson('/api/v1/countries?search=NonExistentCountryXYZ123');
        $emptyResponse->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_countries_api_caches_country_list_and_handles_safe_dial_code(): void
    {
        $countryService = app(CountryService::class);
        $countryService->clearCache();

        $this->assertFalse(Cache::has(CountryService::CACHE_KEY));

        $response = $this->getJson('/api/v1/countries?per_page=250');
        $response->assertOk();

        $this->assertTrue(Cache::has(CountryService::CACHE_KEY));

        // Antarctica (AQ) has no calling code, should be handled safely as null without throwing errors
        $countries = collect($response->json('data.items'));
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
