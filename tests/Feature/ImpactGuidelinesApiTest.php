<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpactGuideline;
use Database\Seeders\ImpactGuidelineSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImpactGuidelinesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('impact_guidelines');
        Schema::create('impact_guidelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->default('Your Life Impact Score');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('action');
            $table->string('category')->default('Business & Growth');
            $table->unsignedInteger('impact_value')->default(1);
            $table->string('impact_unit')->default('Lives');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'display_order']);
        });
    }

    public function test_get_impact_guidelines_returns_successful_response(): void
    {
        $this->seed(ImpactGuidelineSeeder::class);

        $response = $this->getJson('/api/v1/impact-guidelines');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Impact guidelines fetched successfully.',
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
                            'action',
                            'category',
                            'impact_value',
                            'impact_unit',
                            'display_order',
                        ],
                    ],
                ],
            ]);

        $guidelines = $response->json('data.guidelines');
        $this->assertCount(5, $guidelines);
        $this->assertSame('Closed a business deal through Peers Global', $guidelines[0]['action']);
        $this->assertSame('Business & Growth', $guidelines[0]['category']);
        $this->assertSame(5, $guidelines[0]['impact_value']);
        $this->assertSame('Lives', $guidelines[0]['impact_unit']);
        $this->assertSame(1, $guidelines[0]['display_order']);
    }

    public function test_get_impact_guidelines_only_returns_active_records_ordered(): void
    {
        ImpactGuideline::query()->create([
            'action' => 'Second Action',
            'category' => 'Category B',
            'impact_value' => 2,
            'impact_unit' => 'Lives',
            'display_order' => 2,
            'is_active' => true,
        ]);

        ImpactGuideline::query()->create([
            'action' => 'First Action',
            'category' => 'Category A',
            'impact_value' => 1,
            'impact_unit' => 'Life',
            'display_order' => 1,
            'is_active' => true,
        ]);

        ImpactGuideline::query()->create([
            'action' => 'Inactive Action',
            'category' => 'Category C',
            'impact_value' => 10,
            'impact_unit' => 'Lives',
            'display_order' => 0,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/impact-guidelines');

        $response->assertStatus(200);
        $guidelines = $response->json('data.guidelines');

        $this->assertCount(2, $guidelines);
        $this->assertSame('First Action', $guidelines[0]['action']);
        $this->assertSame(1, $guidelines[0]['impact_value']);
        $this->assertSame('Life', $guidelines[0]['impact_unit']);
        $this->assertSame('Second Action', $guidelines[1]['action']);
    }

    public function test_get_impact_guidelines_empty_state_returns_valid_structure(): void
    {
        $response = $this->getJson('/api/v1/impact-guidelines');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Your Life Impact Score',
                    'guidelines' => [],
                ],
            ]);
    }
}
