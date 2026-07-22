<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeerIdSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('peer_id', 50)->nullable()->unique();
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
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->uuid('profile_video_id')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('business_type')->nullable();
            $table->string('status')->nullable();
            $table->string('designation')->nullable();
            $table->json('media')->nullable();
            $table->json('bookmarks')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
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

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('status')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->string('joined_via')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('addon_name')->nullable();
            $table->uuid('circle_subscription_id')->nullable();
            $table->string('subscription_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sme_business_story_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('status')->nullable();
            $table->string('story_link')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_creation_auto_assigns_peer_id(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Peer',
            'last_name' => 'Test',
            'display_name' => 'Peer Test',
            'email' => 'peertest_'.Str::random(6).'@example.com',
            'status' => 'active',
        ]);

        $this->assertNotEmpty($user->peer_id);
        $this->assertStringStartsWith('PG3182736', $user->peer_id);
    }

    public function test_profile_api_returns_peer_id(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Profile',
            'last_name' => 'User',
            'display_name' => 'Profile User',
            'email' => 'profile_'.Str::random(6).'@example.com',
            'status' => 'active',
            'peer_id' => 'PG318273699',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('data.peer_id', 'PG318273699');
    }

    public function test_members_api_returns_peer_id(): void
    {
        $authUser = User::factory()->create([
            'first_name' => 'Auth',
            'last_name' => 'User',
            'display_name' => 'Auth User',
            'email' => 'auth_'.Str::random(6).'@example.com',
            'status' => 'active',
            'peer_id' => 'PG31827361',
        ]);

        $memberUser = User::factory()->create([
            'first_name' => 'Member',
            'last_name' => 'User',
            'display_name' => 'Member User',
            'email' => 'member_'.Str::random(6).'@example.com',
            'status' => 'active',
        ]);
        $this->assertNotEmpty($memberUser->fresh()->peer_id);
        $memberUser->peer_id = 'PG31827362';
        $memberUser->save();

        Sanctum::actingAs($authUser);

        $response = $this->getJson('/api/v1/members');

        $response->assertOk();
        $members = $response->json('data');
        $this->assertNotEmpty($members);

        $foundMember = collect($members)->firstWhere('id', $memberUser->id);
        $this->assertNotNull($foundMember);
        $this->assertNotEmpty($foundMember['peer_id']);
        $this->assertStringStartsWith('PG3182736', $foundMember['peer_id']);
    }

    public function test_profile_update_does_not_change_peer_id(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Update',
            'last_name' => 'Test',
            'display_name' => 'Update Test',
            'email' => 'update_'.Str::random(6).'@example.com',
            'status' => 'active',
            'peer_id' => 'PG3182736123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'first_name' => 'UpdatedName',
            'peer_id' => 'PG3182736999', // Client attempting to modify peer_id
        ]);

        $response->assertOk();

        $freshUser = $user->fresh();
        $this->assertEquals('PG3182736123', $freshUser->peer_id);
    }
}
