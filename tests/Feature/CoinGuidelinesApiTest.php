<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CoinGuideline;
use Database\Seeders\CoinGuidelineSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoinGuidelinesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('coin_guidelines');
        Schema::create('coin_guidelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->default('The Coin Reward System');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('activity');
            $table->unsignedInteger('coins')->default(0);
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'display_order']);
        });
    }

    public function test_get_coin_guidelines_returns_successful_response(): void
    {
        $this->seed(CoinGuidelineSeeder::class);

        $response = $this->getJson('/api/v1/coin-guidelines');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Coin guidelines fetched successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'title',
                    'description',
                    'icon',
                    'guidelines' => [
                        '*' => [
                            'id',
                            'activity',
                            'coins',
                            'display_order',
                        ],
                    ],
                ],
            ]);

        $guidelines = $response->json('data.guidelines');
        $this->assertCount(7, $guidelines);
        $this->assertSame('Testimonial', $guidelines[0]['activity']);
        $this->assertSame(5000, $guidelines[0]['coins']);
        $this->assertSame(1, $guidelines[0]['display_order']);
    }

    public function test_get_coin_guidelines_only_returns_active_records_ordered_by_display_order(): void
    {
        CoinGuideline::query()->create([
            'activity' => 'Second Activity',
            'coins' => 2000,
            'display_order' => 2,
            'is_active' => true,
        ]);

        CoinGuideline::query()->create([
            'activity' => 'First Activity',
            'coins' => 1000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        CoinGuideline::query()->create([
            'activity' => 'Hidden Inactive Activity',
            'coins' => 9999,
            'display_order' => 0,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/coin-guidelines');

        $response->assertStatus(200);
        $guidelines = $response->json('data.guidelines');

        $this->assertCount(2, $guidelines);
        $this->assertSame('First Activity', $guidelines[0]['activity']);
        $this->assertSame(1000, $guidelines[0]['coins']);
        $this->assertSame('Second Activity', $guidelines[1]['activity']);
        $this->assertSame(2000, $guidelines[1]['coins']);
    }

    public function test_get_coin_guidelines_empty_state_returns_valid_structure(): void
    {
        $response = $this->getJson('/api/v1/coin-guidelines');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'The Coin Reward System',
                    'guidelines' => [],
                ],
            ]);
    }
}
