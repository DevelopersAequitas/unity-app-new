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

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('s3_key')->nullable();
                $table->string('mime_type')->nullable();
                $table->bigInteger('size_bytes')->nullable();
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cities')) {
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

        if (! Schema::hasTable('sme_business_story_submissions')) {
            Schema::create('sme_business_story_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('status')->nullable();
                $table->string('story_link')->nullable();
                $table->timestamps();
            });
        }
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

        $authUser = User::factory()->create([
            'status' => 'active',
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
        Sanctum::actingAs($authUser);

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
                    'company_name',
                    'life_impacted_count',
                    'profile_photo_image',
                    'designation',
                    'level4_category',
                    'is_bookmark',
                    'is_verified',
                    'match_percentage',
                ],
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ],
        ]);

        $data = $response->json('data');

        // Verify that inactive user and auth user are not returned
        $this->assertCount(1, $data);
        $this->assertSame($activeUser->id, $data[0]['id']);
        $this->assertSame('John Doe', $data[0]['name']);
        $this->assertSame('New York, IN', $data[0]['city']);
        $this->assertSame('Acme Corp', $data[0]['company_name']);
        $this->assertSame(42, $data[0]['life_impacted_count']);
        $this->assertSame('Developer', $data[0]['designation']);
        $this->assertSame('Software Engineering', $data[0]['level4_category']);
        $this->assertFalse($data[0]['is_bookmark']);
        $this->assertIsBool($data[0]['is_verified']);
        $this->assertIsInt($data[0]['match_percentage']);

        // Verify that removed or sensitive fields are NOT present
        $this->assertArrayNotHasKey('business', $data[0]);
        $this->assertArrayNotHasKey('total_life_impact', $data[0]);
        $this->assertArrayNotHasKey('email', $data[0]);
        $this->assertArrayNotHasKey('phone', $data[0]);
        $this->assertArrayNotHasKey('coins_balance', $data[0]);
        $this->assertArrayNotHasKey('industry_tags', $data[0]);
    }

    public function test_limited_users_endpoint_returns_is_verified_boolean_field(): void
    {
        $authUser = User::factory()->create([
            'status' => 'active',
        ]);

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

        Sanctum::actingAs($authUser);

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

    public function test_limited_users_endpoint_returns_members_with_pagination_15_per_page(): void
    {
        $activeUser = User::factory()->create([
            'status' => 'active',
        ]);

        User::factory()->count(25)->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($activeUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(25, $response->json('total_users'));
        $this->assertSame(25, $response->json('total_user'));
        $this->assertSame(25, $response->json('meta.total'));
        $this->assertSame(15, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertNotNull($response->json('links.next'));
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
        $this->assertCount(20, $response->json('data'));
        $this->assertSame(20, $response->json('total_users'));
        $this->assertSame(20, $response->json('total_user'));
        $response->assertJsonMissing(['meta', 'links']);

        // Verify that full data (like email and is_bookmark) is present in the response
        $data = $response->json('data');
        $this->assertArrayHasKey('email', $data[0]);
        $this->assertArrayHasKey('is_bookmark', $data[0]);
    }

    public function test_limited_users_endpoint_excludes_authenticated_user(): void
    {
        $authUser = User::factory()->create([
            'first_name' => 'Current',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Peer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($authUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $data = collect($response->json('data'));

        $this->assertNull($data->firstWhere('id', $authUser->id));
        $this->assertNotNull($data->firstWhere('id', $otherUser->id));
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

    public function test_limited_users_endpoint_returns_ranked_members_by_recommendation_algorithm(): void
    {
        $authUser = User::factory()->create([
            'status' => 'active',
            'city' => 'Ahmedabad',
            'business_category_id' => 101,
        ]);

        // User A: High match (same city, same category), medium impact
        $userHighMatch = User::factory()->create([
            'status' => 'active',
            'first_name' => 'High',
            'last_name' => 'Match',
            'city' => 'Ahmedabad',
            'business_category_id' => 101,
            'life_impacted_count' => 10,
        ]);

        // User B: Low match (different city, different category), low impact
        $userLowMatch = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Low',
            'last_name' => 'Match',
            'city' => 'Mumbai',
            'business_category_id' => 999,
            'life_impacted_count' => 0,
        ]);

        Sanctum::actingAs($authUser);

        $response = $this->getJson('/api/v1/members/limited');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // High match should be ranked first
        $this->assertSame($userHighMatch->id, $data[0]['id']);
        $this->assertGreaterThan($data[1]['match_percentage'], $data[0]['match_percentage']);
    }
}
