<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ask\Ask;
use App\Models\Ask\AskAnswer;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskMatch;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskTimelineLink;
use App\Models\Ask\AskType;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\Referral;
use App\Models\ReferralStatus;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostFeedAskMentionsAndReferralIsolationTest extends TestCase
{
    private const TEST_SCHEMA = 'test_post_referral_ask_iso';

    protected User $author;

    protected User $recipient;

    protected AskFlow $referralFlow;

    protected AskType $referralType;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('DROP SCHEMA IF EXISTS '.self::TEST_SCHEMA.' CASCADE');
        DB::statement('CREATE SCHEMA '.self::TEST_SCHEMA);
        DB::statement('SET search_path TO '.self::TEST_SCHEMA);

        $this->createMinimalSchema();

        ReferralStatus::query()->create(['id' => 1, 'name' => 'Contacted']);

        $this->author = $this->createUser('Author User');
        $this->recipient = $this->createUser('Recipient User');

        $this->referralFlow = AskFlow::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'referral',
            'name' => 'Ask for a Referral',
            'description' => 'Referral flow',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->referralType = AskType::query()->create([
            'id' => (string) Str::uuid(),
            'flow_id' => $this->referralFlow->id,
            'code' => 'dealer_distributor',
            'name' => 'Dealer / Distributor',
            'level' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            DB::statement('SET search_path TO public');
            DB::statement('DROP SCHEMA IF EXISTS '.self::TEST_SCHEMA.' CASCADE');
        } finally {
            parent::tearDown();
        }
    }

    public function test_ask_post_does_not_inject_matched_peers_or_responders_into_mentions(): void
    {
        $ask = Ask::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->referralType->id,
            'title' => 'Need EV distributor',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'publish_to_timeline' => true,
            'published_at' => now(),
        ]);

        AskAnswer::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'field_key' => 'goal',
            'value_text' => 'Need EV distributor',
            'sort_order' => 0,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $matchedPeer = $this->createUser("Matched Peer {$i}");
            AskMatch::query()->create([
                'id' => (string) Str::uuid(),
                'ask_id' => $ask->id,
                'matched_user_id' => $matchedPeer->id,
                'match_score' => 80,
                'match_status' => AskMatch::STATUS_SUGGESTED,
            ]);
        }

        $responder = $this->createUser('Responder Peer');
        AskResponse::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'responder_user_id' => $responder->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'status' => AskResponse::STATUS_PENDING,
        ]);

        $post = Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'title' => $ask->title,
            'content_text' => "Need EV distributor\n\nAsk for a Referral: Dealer / Distributor",
            'media' => [],
            'tags' => ['ask', 'referral'],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => 'ask',
            'source_id' => $ask->id,
            'source_event' => 'published',
            'post_type' => 'ask',
        ]);

        AskTimelineLink::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'post_id' => $post->id,
        ]);

        Sanctum::actingAs($this->author);

        $feedResponse = $this->getJson('/api/v1/posts/feed');
        $feedResponse->assertOk();
        $feedItem = collect($feedResponse->json('data.items'))->firstWhere('id', $post->id);
        $this->assertNotNull($feedItem);
        $this->assertSame([], $feedItem['mentions']);
        $this->assertSame(5, $feedItem['ask']['match_count']);
        $this->assertSame(1, $feedItem['ask']['response_count']);

        $showResponse = $this->getJson("/api/v1/posts/{$post->id}");
        $showResponse->assertOk();
        $this->assertSame([], $showResponse->json('data.mentions'));

        $userPostsResponse = $this->getJson("/api/v1/users/{$this->author->id}/posts");
        $userPostsResponse->assertOk();
        $userPostItem = collect($userPostsResponse->json('data.items'))->firstWhere('id', $post->id);
        $this->assertNotNull($userPostItem);
        $this->assertSame([], $userPostItem['mentions']);
    }

    public function test_ask_post_preserves_explicit_mentions(): void
    {
        $ask = Ask::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->referralType->id,
            'title' => 'Looking for Solar Partner',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'publish_to_timeline' => true,
            'published_at' => now(),
        ]);

        $matchedPeer = $this->createUser('Random Matched Peer');
        AskMatch::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'matched_user_id' => $matchedPeer->id,
            'match_score' => 75,
            'match_status' => AskMatch::STATUS_SUGGESTED,
        ]);

        $explicitPeer = $this->createUser('Explicit Mentioned Peer');

        $post = Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'title' => $ask->title,
            'content_text' => 'Looking for Solar Partner',
            'media' => [],
            'tags' => ['ask', 'referral'],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => 'ask',
            'source_id' => $ask->id,
            'source_event' => 'published',
            'post_type' => 'ask',
        ]);

        AskTimelineLink::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'post_id' => $post->id,
        ]);

        PostMention::query()->create([
            'post_id' => $post->id,
            'peer_id' => $explicitPeer->id,
        ]);

        Sanctum::actingAs($this->author);

        $feedResponse = $this->getJson('/api/v1/posts/feed');
        $feedResponse->assertOk();
        $feedItem = collect($feedResponse->json('data.items'))->firstWhere('id', $post->id);
        $this->assertNotNull($feedItem);
        $this->assertCount(1, $feedItem['mentions']);
        $this->assertSame((string) $explicitPeer->id, $feedItem['mentions'][0]['id']);

        $showResponse = $this->getJson("/api/v1/posts/{$post->id}");
        $showResponse->assertOk();
        $this->assertCount(1, $showResponse->json('data.mentions'));
        $this->assertSame((string) $explicitPeer->id, $showResponse->json('data.mentions.0.id'));

        $userPostsResponse = $this->getJson("/api/v1/users/{$this->author->id}/posts");
        $userPostsResponse->assertOk();
        $userPostItem = collect($userPostsResponse->json('data.items'))->firstWhere('id', $post->id);
        $this->assertNotNull($userPostItem);
        $this->assertCount(1, $userPostItem['mentions']);
        $this->assertSame((string) $explicitPeer->id, $userPostItem['mentions'][0]['id']);
    }

    public function test_storing_direct_referral_does_not_create_timeline_post_and_remains_in_activity_referral_history(): void
    {
        Sanctum::actingAs($this->author);

        $response = $this->postJson('/api/v1/activities/referrals', [
            'to_user_id' => (string) $this->recipient->id,
            'referral_type' => 'customer_referral',
            'referral_date' => '2026-11-28',
            'referral_of' => 'Ashishbhai Makwana',
            'phone' => '9876543210',
            'hot_value' => 4,
            'remarks' => 'Buy flat',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $referralId = (string) $response->json('data.id');
        $this->assertNotEmpty($referralId);
        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, PostMention::query()->count());

        $historyResponse = $this->getJson('/api/v1/activities/referrals');
        $historyResponse->assertOk();
        $historyIds = collect($historyResponse->json('data.items'))->pluck('id')->all();
        $this->assertContains($referralId, $historyIds);
    }

    public function test_direct_referrals_and_legacy_referral_posts_are_excluded_from_feed_and_post_history(): void
    {
        $directReferral = Referral::query()->create([
            'id' => (string) Str::uuid(),
            'from_user_id' => $this->author->id,
            'to_user_id' => $this->recipient->id,
            'referral_type' => 'customer_referral',
            'referral_date' => '2026-11-28',
            'referral_of' => 'Direct Referral Lead',
            'phone' => '9876543210',
            'hot_value' => 3,
            'remarks' => 'Direct referral without post row',
            'status_id' => 1,
            'is_deleted' => false,
        ]);

        $legacyReferralPost = Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'content_text' => 'Hey Peers, I gave a business referral for Legacy Lead to Recipient User.',
            'media' => [],
            'tags' => ['referral'],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => 'referral',
            'source_id' => $directReferral->id,
            'source_event' => 'referral_created',
            'post_type' => 'referral',
        ]);

        $ask = Ask::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->referralType->id,
            'title' => 'Need Logistics Partner',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'publish_to_timeline' => true,
            'published_at' => now(),
        ]);

        $askPost = Post::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'title' => $ask->title,
            'content_text' => 'Need Logistics Partner',
            'media' => [],
            'tags' => ['ask', 'referral'],
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'active' => true,
            'status' => 'active',
            'source_type' => 'ask',
            'source_id' => $ask->id,
            'source_event' => 'published',
            'post_type' => 'ask',
        ]);

        AskTimelineLink::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $ask->id,
            'post_id' => $askPost->id,
        ]);

        Sanctum::actingAs($this->author);

        $feedIds = collect($this->getJson('/api/v1/posts/feed')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($askPost->id, $feedIds);
        $this->assertNotContains($directReferral->id, $feedIds);
        $this->assertNotContains($legacyReferralPost->id, $feedIds);

        $userPostIds = collect($this->getJson("/api/v1/users/{$this->author->id}/posts")->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($askPost->id, $userPostIds);
        $this->assertNotContains($directReferral->id, $userPostIds);
        $this->assertNotContains($legacyReferralPost->id, $userPostIds);

        $myPostIds = collect($this->getJson('/api/v1/profile/posts')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($askPost->id, $myPostIds);
        $this->assertNotContains($directReferral->id, $myPostIds);
        $this->assertNotContains($legacyReferralPost->id, $myPostIds);
    }

    public function test_ask_referral_global_feed_and_history_exclude_direct_referrals(): void
    {
        $referralAsk = Ask::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->referralType->id,
            'title' => 'Looking for Packaging Vendor',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'publish_to_timeline' => true,
            'published_at' => now(),
        ]);

        $otherAsk = Ask::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->recipient->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->referralType->id,
            'title' => 'Need Retail Buyer Intro',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'publish_to_timeline' => true,
            'published_at' => now(),
        ]);

        $askResponse = AskResponse::query()->create([
            'id' => (string) Str::uuid(),
            'ask_id' => $otherAsk->id,
            'responder_user_id' => $this->author->id,
            'introduced_user_id' => $this->author->id,
            'response_type' => AskResponse::TYPE_CAN_INTRODUCE_PEER,
            'message' => 'I can connect you.',
            'status' => AskResponse::STATUS_PENDING,
        ]);

        $directReferral = Referral::query()->create([
            'id' => (string) Str::uuid(),
            'from_user_id' => $this->author->id,
            'to_user_id' => $this->recipient->id,
            'referral_type' => 'b2b_referral',
            'referral_date' => '2026-11-28',
            'referral_of' => 'Direct Referral Must Not Leak',
            'phone' => '9876543210',
            'hot_value' => 5,
            'remarks' => 'Private direct referral',
            'status_id' => 1,
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($this->author);

        $globalIds = collect($this->getJson('/api/v1/asks/referral/global')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($referralAsk->id, $globalIds);
        $this->assertContains($otherAsk->id, $globalIds);
        $this->assertNotContains($directReferral->id, $globalIds);

        $myDefaultIds = collect($this->getJson('/api/v1/asks/referral/my')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($referralAsk->id, $myDefaultIds);
        $this->assertNotContains($directReferral->id, $myDefaultIds);

        $myGivenIds = collect($this->getJson('/api/v1/asks/referral/my?filter=given')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($askResponse->id, $myGivenIds);
        $this->assertNotContains($directReferral->id, $myGivenIds);

        $myReceivedIds = collect($this->getJson('/api/v1/asks/referral/my?filter=received')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertContains($askResponse->id, $myReceivedIds);
        $this->assertNotContains($directReferral->id, $myReceivedIds);

        $myAllResponse = $this->getJson('/api/v1/asks/referral/my?filter=all')->assertOk();
        $myAllIds = collect($myAllResponse->json('data.items'))->pluck('id')->all();
        $this->assertContains($referralAsk->id, $myAllIds);
        $this->assertContains($askResponse->id, $myAllIds);
        $this->assertNotContains($directReferral->id, $myAllIds);
        $this->assertNotContains($directReferral->id, collect($myAllResponse->json('data.given_referrals'))->pluck('id')->all());
        $this->assertNotContains($directReferral->id, collect($myAllResponse->json('data.received_introductions'))->pluck('id')->all());
    }

    private function createUser(string $displayName): User
    {
        $parts = explode(' ', $displayName, 2);

        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? 'Peer',
            'display_name' => $displayName,
            'email' => Str::slug($displayName).'.'.Str::random(6).'@example.com',
            'phone' => '9'.random_int(100000000, 999999999),
            'company_name' => 'Test Co',
            'status' => 'active',
            'membership_status' => 'paid_peer',
            'public_profile_slug' => Str::slug($displayName).'-'.Str::random(5),
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('city_of_residence')->nullable();
            $table->string('district_id')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->unsignedBigInteger('main_business_category_id')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->string('profile_photo_file_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->string('public_profile_slug')->nullable();
            $table->string('status')->default('active');
            $table->string('membership_status')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->uuid('active_circle_id')->nullable();
            $table->uuid('circle_id')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impact_points')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->string('title')->nullable();
            $table->text('content_text')->nullable();
            $table->jsonb('media')->nullable();
            $table->jsonb('tags')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('approved');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('post_type')->default('standard');
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('peer_id');
            $table->timestamps();
        });

        Schema::create('post_likes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->timestamps();
        });

        Schema::create('post_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->text('content')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_saves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->timestamps();
        });

        Schema::create('referral_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->string('referral_type');
            $table->date('referral_date');
            $table->string('referral_of');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->integer('hot_value')->default(1);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('status_id')->default(1);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ask_flows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ask_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('flow_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('flow_id')->nullable();
            $table->uuid('type_id')->nullable();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->string('visibility_type')->default('all_peers');
            $table->uuid('visibility_district_id')->nullable();
            $table->uuid('visibility_circle_id')->nullable();
            $table->boolean('publish_to_timeline')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->string('outcome_status')->nullable();
            $table->string('approx_deal_value')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ask_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ask_id');
            $table->uuid('option_group_id')->nullable();
            $table->uuid('option_id')->nullable();
            $table->string('field_key');
            $table->text('value_text')->nullable();
            $table->double('value_number')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ask_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ask_id');
            $table->uuid('matched_user_id');
            $table->double('match_score')->default(0);
            $table->jsonb('matched_dimensions')->nullable();
            $table->string('match_reason')->nullable();
            $table->string('algorithm_version')->nullable();
            $table->string('match_status')->default('suggested');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ask_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ask_id');
            $table->uuid('responder_user_id');
            $table->uuid('introduced_user_id')->nullable();
            $table->string('response_type');
            $table->text('message')->nullable();
            $table->string('timeline')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ask_response_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('response_id');
            $table->string('full_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ask_timeline_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ask_id');
            $table->uuid('post_id');
            $table->timestamps();
        });

        Schema::create('collaboration_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('accepted_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('district_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
