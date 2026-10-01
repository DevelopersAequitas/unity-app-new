<?php

namespace Tests\Feature;

use App\Models\CircleCategory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('circle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('circle_key')->nullable();
            $table->integer('level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_level3', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_authenticated_user_can_retrieve_circle_categories(): void
    {
        // Create a category
        CircleCategory::create([
            'name' => 'Manufacturing & Engineering Circles',
            'slug' => 'manufacturing-engineering-circles',
            'level' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/circle-categories');

        $response->assertOk();
        // Check data is NOT null and contains the category
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'level',
                        'sort_order',
                        'is_active',
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Manufacturing & Engineering Circles', $response->json('data.items.0.name'));
    }

    public function test_unauthenticated_user_can_retrieve_circle_categories(): void
    {
        CircleCategory::create([
            'name' => 'Technology, IT & Digital Services Circles',
            'slug' => 'technology-it-digital-services-circles',
            'level' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/circle-categories');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Technology, IT & Digital Services Circles', $response->json('data.items.0.name'));
    }

    public function test_can_retrieve_circle_category_with_only_level4_categories(): void
    {
        $category = CircleCategory::create([
            'name' => 'Real Estate, Construction & Infrastructure',
            'slug' => 'real-estate-construction-infrastructure',
            'circle_key' => 'real_estate',
            'level' => 1,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $category->level4Categories()->create([
            'name' => 'Residential Developer (Mid-Segment)',
            'slug' => 'residential-developer-mid-segment',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/circle-categories/'.$category->id);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $category->id);
        $response->assertJsonPath('data.counts.level4', 1);
        $response->assertJsonPath('data.counts.total_children', 1);
        $response->assertJsonPath('data.pagination.current_page', 1);
        $response->assertJsonPath('data.pagination.per_page', 30);
        $response->assertJsonPath('data.pagination.total', 1);
        $response->assertJsonMissingPath('data.level2_categories');
        $response->assertJsonMissingPath('data.level3_categories');
        $response->assertJsonCount(1, 'data.level4_categories');
        $response->assertJsonPath('data.level4_categories.0.name', 'Residential Developer (Mid-Segment)');
    }
}
