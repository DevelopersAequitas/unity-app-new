<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CircleCategoryLevel4;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
            'designation' => 'Developer',
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
                    'first_name',
                    'last_name',
                    'city',
                    'business',
                    'total_life_impact',
                    'profile_photo_image',
                    'designation',
                    'level4_category',
                    'is_bookmark',
                ],
            ],
        ]);

        $data = $response->json('data');

        // Verify that inactive user is not returned
        $this->assertCount(1, $data);
        $this->assertSame($activeUser->id, $data[0]['id']);
        $this->assertSame('John Doe', $data[0]['name']);
        $this->assertSame('New York', $data[0]['city']);
        $this->assertSame('Acme Corp', $data[0]['business']);
        $this->assertSame(42, $data[0]['total_life_impact']);
        $this->assertSame('Developer', $data[0]['designation']);
        $this->assertSame('Software Engineering', $data[0]['level4_category']);
        $this->assertFalse($data[0]['is_bookmark']);

        // Verify that other sensitive/large fields are NOT present in the limited response
        $this->assertArrayNotHasKey('email', $data[0]);
        $this->assertArrayNotHasKey('phone', $data[0]);
        $this->assertArrayNotHasKey('coins_balance', $data[0]);
        $this->assertArrayNotHasKey('industry_tags', $data[0]);
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

    public function test_members_endpoint_returns_all_members_without_pagination_with_all_fields(): void
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

        // Verify that full data (like email and is_bookmark) is present in the response
        $data = $response->json('data');
        $this->assertArrayHasKey('email', $data[0]);
        $this->assertArrayHasKey('is_bookmark', $data[0]);
    }

    public function test_user_can_bookmark_and_unbookmark_members(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $memberToBookmark = User::factory()->create(['status' => 'active']);

        Sanctum::actingAs($user);

        // Bookmark the member
        $response = $this->postJson("/api/v1/members/{$memberToBookmark->id}/bookmark");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Member bookmarked successfully.',
        ]);

        // Verify in DB that it is bookmarked
        $user->refresh();
        $this->assertContains($memberToBookmark->id, $user->bookmarks);

        // Check limited users API displays it as bookmarked
        $response = $this->getJson('/api/v1/members/limited');
        $response->assertOk();
        $data = $response->json('data');
        $targetUser = collect($data)->firstWhere('id', $memberToBookmark->id);
        $this->assertNotNull($targetUser);
        $this->assertTrue($targetUser['is_bookmark']);

        // Check regular members API displays it as bookmarked
        $response = $this->getJson('/api/v1/members');
        $response->assertOk();
        $data = $response->json('data');
        $targetUser = collect($data)->firstWhere('id', $memberToBookmark->id);
        $this->assertNotNull($targetUser);
        $this->assertTrue($targetUser['is_bookmark']);

        // Unbookmark the member
        $response = $this->deleteJson("/api/v1/members/{$memberToBookmark->id}/bookmark");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Member unbookmarked successfully.',
        ]);

        // Verify in DB that it is unbookmarked
        $user->refresh();
        $this->assertNotContains($memberToBookmark->id, $user->bookmarks ?? []);

        // Check limited users API displays it as not bookmarked
        $response = $this->getJson('/api/v1/members/limited');
        $response->assertOk();
        $data = $response->json('data');
        $targetUser = collect($data)->firstWhere('id', $memberToBookmark->id);
        $this->assertNotNull($targetUser);
        $this->assertFalse($targetUser['is_bookmark']);
    }
}
