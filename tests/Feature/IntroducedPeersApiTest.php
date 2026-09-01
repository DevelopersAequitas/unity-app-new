<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntroducedPeersApiTest extends TestCase
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
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('business_type')->nullable();
            $table->string('status')->nullable();
            $table->string('designation')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->uuid('introduced_by')->nullable();
            $table->integer('members_introduced_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->text('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->text('contribution_award_recognition')->nullable();
            $table->json('bookmarks')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->string('zoho_plan_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('pending');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('post_type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('channel')->default('push');
            $table->string('priority')->default('medium');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('event_enabled')->default(true);
            $table->boolean('circle_enabled')->default(true);
            $table->boolean('business_enabled')->default(true);
            $table->boolean('campaign_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id');
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token')->nullable();
            $table->string('platform')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_suppression_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('send_count')->default(0);
            $table->timestamps();
        });
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile/introduced-peers')
            ->assertUnauthorized();

        $this->getJson('/api/v1/profile/introducer')
            ->assertUnauthorized();

        $this->postJson('/api/v1/profile/introduced-peers', ['peer_id' => 'some-uuid'])
            ->assertUnauthorized();
    }

    public function test_get_profile_introducer_returns_null_when_none(): void
    {
        $user = User::factory()->create(['introduced_by' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/introducer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No introducer set for this peer.');
    }

    public function test_can_get_profile_introducer(): void
    {
        $introducer = User::factory()->create();
        $user = User::factory()->create(['introduced_by' => $introducer->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/introducer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $introducer->id);
    }

    public function test_can_introduce_a_peer_and_list_them(): void
    {
        $user = User::factory()->create([
            'members_introduced_count' => 0,
        ]);
        $peer = User::factory()->create([
            'introduced_by' => null,
        ]);

        Sanctum::actingAs($user);

        // 1. POST to introduce the peer
        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $peer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $peer->id);

        $peer->refresh();
        $user->refresh();

        $this->assertSame($user->id, $peer->introduced_by);
        $this->assertEquals(1, $user->members_introduced_count);

        // 2. GET to list introduced peers
        $listResponse = $this->getJson('/api/v1/profile/introduced-peers');
        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $peer->id);
    }

    public function test_cannot_introduce_self(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot introduce yourself.');
    }

    public function test_cannot_introduce_peer_already_introduced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $peer = User::factory()->create([
            'introduced_by' => $user1->id,
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $peer->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This peer has already been introduced by another member.');
    }

    public function test_top_5_introducers_api_unauthorized_without_valid_bearer_token(): void
    {
        $response = $this->getJson('/api/v1/members/top-introducers');
        $response->assertUnauthorized();
    }

    public function test_top_5_introducers_api_empty_returns_data_as_empty_array(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/members/top-introducers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_top_5_introducers_api_ranking_sorting_limit(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $introducers = [];
        for ($i = 1; $i <= 7; $i++) {
            $introducers[$i] = User::factory()->create([
                'first_name' => 'Member',
                'last_name' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'display_name' => 'Member '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'status' => 'active',
            ]);
        }

        for ($k = 0; $k < 5; $k++) {
            User::factory()->create(['introduced_by' => $introducers[1]->id, 'status' => 'active']);
        }
        for ($k = 0; $k < 4; $k++) {
            User::factory()->create(['introduced_by' => $introducers[2]->id, 'status' => 'active']);
        }
        for ($k = 0; $k < 4; $k++) {
            User::factory()->create(['introduced_by' => $introducers[3]->id, 'status' => 'active']);
        }
        for ($i = 4; $i <= 7; $i++) {
            User::factory()->create(['introduced_by' => $introducers[$i]->id, 'status' => 'active']);
        }

        $response = $this->getJson('/api/v1/members/top-introducers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data');

        $response->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.id', $introducers[1]->id)
            ->assertJsonPath('data.0.introduced_members_count', 5);

        $response->assertJsonPath('data.1.rank', 2)
            ->assertJsonPath('data.1.id', $introducers[2]->id)
            ->assertJsonPath('data.1.introduced_members_count', 4);

        $response->assertJsonPath('data.2.rank', 3)
            ->assertJsonPath('data.2.id', $introducers[3]->id)
            ->assertJsonPath('data.2.introduced_members_count', 4);

        $zeroIntroducer = User::factory()->create([
            'display_name' => 'Zero Introducer Again',
            'status' => 'active',
        ]);
        $response = $this->getJson('/api/v1/members/top-introducers');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($zeroIntroducer->id, $ids);
    }

    public function test_can_introduce_an_inactive_peer(): void
    {
        $user = User::factory()->create([
            'members_introduced_count' => 0,
            'status' => 'active',
        ]);
        $inactivePeer = User::factory()->create([
            'introduced_by' => null,
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $inactivePeer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $inactivePeer->id);

        $inactivePeer->refresh();
        $user->refresh();

        $this->assertSame($user->id, $inactivePeer->introduced_by);
        $this->assertEquals(1, $user->members_introduced_count);
    }

    public function test_member_introduced_peers_api_includes_inactive_peers(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $inactivePeer = User::factory()->create([
            'introduced_by' => $user->id,
            'status' => 'inactive',
        ]);
        $activePeer = User::factory()->create([
            'introduced_by' => $user->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/members/{$user->id}/introduced-peers");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.introduced_peers_count', 2);

        $ids = collect($response->json('data.introduced_peers'))->pluck('id')->all();
        $this->assertContains($inactivePeer->id, $ids);
        $this->assertContains($activePeer->id, $ids);
    }
}
