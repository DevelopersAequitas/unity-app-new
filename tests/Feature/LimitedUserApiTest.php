<?php

namespace Tests\Feature;

use App\Models\CircleCategoryLevel4;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LimitedUserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_limited_users_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/members/limited');
        $response->assertStatus(401);
    }

    public function test_limited_users_endpoint_returns_only_active_members_with_limited_data(): void
    {
        // Create category
        $category = CircleCategoryLevel4::create([
            'name' => 'Software Engineering',
        ]);

        // 1. Create active user
        $activeUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'city' => 'New York',
            'life_impacted_count' => 42,
            'status' => 'active',
            'business_category_id' => $category->id,
        ]);

        // 2. Create inactive user
        $inactiveUser = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'inactive',
        ]);

        // Authenticate
        Sanctum::actingAs($activeUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'profile_photo_image',
                    'city',
                    'business_name',
                    'total_life_impact',
                    'company_name',
                    'level4_category',
                ],
            ],
        ]);

        $data = $response->json('data');

        // Verify that inactive user is not returned
        $this->assertCount(1, $data);
        $this->assertSame($activeUser->id, $data[0]['id']);
        $this->assertSame('John Doe', $data[0]['name']);
        $this->assertSame('New York', $data[0]['city']);
        $this->assertSame('Acme Corp', $data[0]['business_name']);
        $this->assertSame('Acme Corp', $data[0]['company_name']);
        $this->assertSame(42, $data[0]['total_life_impact']);
        $this->assertSame('Software Engineering', $data[0]['level4_category']);
    }

    public function test_limited_users_endpoint_returns_paginated_members(): void
    {
        $activeUser = User::factory()->create([
            'status' => 'active',
        ]);

        User::factory()->count(20)->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($activeUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $response->assertJsonStructure([
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
        $this->assertSame(21, $response->json('meta.total'));
    }

    public function test_members_endpoint_returns_all_members_without_pagination(): void
    {
        $activeUser = User::factory()->create([
            'status' => 'active',
        ]);

        User::factory()->count(20)->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($activeUser);

        $response = $this->getJson('/api/v1/members');

        $response->assertOk();
        $this->assertCount(21, $response->json('data'));
        $response->assertJsonMissing(['meta', 'links']);
    }
}
