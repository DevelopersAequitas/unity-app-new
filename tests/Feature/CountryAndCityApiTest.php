<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_countries_api_returns_country_list_with_dial_codes_and_flags(): void
    {
        $response = $this->getJson('/api/v1/countries');

        $response->assertOk()
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
        $india = $countries->firstWhere('code', 'IN');

        $this->assertNotNull($india);
        $this->assertEquals('India', $india['name']);
        $this->assertEquals('+91', $india['dial_code']);
        $this->assertEquals('🇮🇳', $india['flag']);
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
