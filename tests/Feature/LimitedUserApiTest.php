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

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('public_profile_slug')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->nullable();
            $table->integer('life_impacted_count')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('designation')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->string('business_type')->nullable();
            $table->string('status')->nullable();
            $table->string('contact_visibility', 50)->nullable();
            $table->string('profile_visibility', 50)->nullable();
            $table->json('bookmarks')->nullable();
            $table->boolean('is_verified')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

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

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('requester_id');
            $table->uuid('addressee_id');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('status')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
                    'is_verified',
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
        $this->assertIsBool($data[0]['is_verified']);

        // Verify that other sensitive/large fields are NOT present in the limited response
        $this->assertArrayNotHasKey('email', $data[0]);
        $this->assertArrayNotHasKey('phone', $data[0]);
        $this->assertArrayNotHasKey('coins_balance', $data[0]);
        $this->assertArrayNotHasKey('industry_tags', $data[0]);
    }

    public function test_limited_users_endpoint_returns_is_verified_boolean_field(): void
    {
        $verifiedUser = User::factory()->create([
            'status' => 'active',
            'membership_status' => 'Only Unity Peer',
            'is_verified' => true,
        ]);

        $unverifiedUser = User::factory()->create([
            'status' => 'active',
            'membership_status' => 'free_peer',
            'is_verified' => false,
        ]);

        $nullVerifiedUser = User::factory()->create([
            'status' => 'active',
            'membership_status' => 'free_peer',
            'is_verified' => null,
        ]);

        Sanctum::actingAs($verifiedUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $data = collect($response->json('data'));

        $vItem = $data->firstWhere('id', $verifiedUser->id);
        $uItem = $data->firstWhere('id', $unverifiedUser->id);
        $nItem = $data->firstWhere('id', $nullVerifiedUser->id);

        $this->assertNotNull($vItem);
        $this->assertNotNull($uItem);
        $this->assertNotNull($nItem);

        $this->assertTrue($vItem['is_verified']);
        $this->assertFalse($uItem['is_verified']);
        $this->assertFalse($nItem['is_verified']);

        $this->assertIsBool($vItem['is_verified']);
        $this->assertIsBool($uItem['is_verified']);
        $this->assertIsBool($nItem['is_verified']);
    }

    public function test_limited_users_endpoint_returns_all_members_without_pagination(): void
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
        $this->assertCount(21, $response->json('data'));
        $response->assertJsonMissing(['meta', 'links']);
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
