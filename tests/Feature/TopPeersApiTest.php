<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessDeal;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\UserTag;
use App\Models\UserTagAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopPeersApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('user_tag_assignments');
        Schema::dropIfExists('user_tags');
        Schema::dropIfExists('users');
        Schema::dropIfExists('business_deals');
        Schema::dropIfExists('p2p_meetings');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('referrals');

        Schema::create('user_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_tag_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->unique(['user_id', 'tag_id']);
        });

        UserTag::create([
            'name' => 'Team Member',
            'slug' => 'team_member',
            'description' => 'Internal team member excluded from leaderboards',
            'is_active' => true,
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('business_type')->nullable();
            $table->string('city')->nullable();
            $table->string('profile_photo_file_id')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->date('deal_date')->nullable();
            $table->decimal('deal_amount', 15, 2)->nullable();
            $table->string('business_type')->nullable();
            $table->text('comment')->nullable();
            $table->uuid('referral_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initiator_user_id');
            $table->uuid('peer_user_id');
            $table->date('meeting_date')->nullable();
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            $table->integer('rating')->default(5);
            $table->uuid('referral_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->string('referral_type')->nullable();
            $table->date('referral_date')->nullable();
            $table->string('referral_of')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('hot_value')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('status_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createUser(string $name, string $company = 'Test Corp', string $city = 'Ahmedabad'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => trim(strstr($name, ' ') ?: ''),
            'display_name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
            'company_name' => $company,
            'city' => $city,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_requests_fail(): void
    {
        $this->getJson('/api/v1/leaderboards/business-deals')->assertStatus(401);
        $this->getJson('/api/v1/leaderboards/p2p-meetings')->assertStatus(401);
        $this->getJson('/api/v1/leaderboards/testimonials')->assertStatus(401);
        $this->getJson('/api/v1/leaderboards/referrals')->assertStatus(401);
    }

    public function test_top_business_deals_returns_top_10(): void
    {
        // Create 12 users
        $users = [];
        for ($i = 1; $i <= 12; $i++) {
            $users[$i] = $this->createUser("Peer {$i}", "Company {$i}");
        }

        $recipient = $this->createUser('Recipient User');

        // Create varying number of deals: User 1 has 10 deals, User 2 has 9 deals, etc.
        for ($i = 1; $i <= 12; $i++) {
            $dealCount = 13 - $i;
            for ($j = 1; $j <= $dealCount; $j++) {
                BusinessDeal::query()->create([
                    'id' => (string) Str::uuid(),
                    'from_user_id' => $users[$i]->id,
                    'to_user_id' => $recipient->id,
                    'deal_amount' => 1000 * $j,
                    'deal_date' => '2026-06-01',
                    'is_deleted' => false,
                ]);
            }
        }

        Sanctum::actingAs($users[1]);

        $response = $this->getJson('/api/v1/leaderboards/business-deals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activity_type', 'business_deals')
            ->assertJsonPath('data.total', 10)
            ->assertJsonCount(10, 'data.peers')
            ->assertJsonPath('data.peers.0.id', $users[1]->id)
            ->assertJsonPath('data.peers.0.rank', 1)
            ->assertJsonPath('data.peers.0.deals_count', 12)
            ->assertJsonPath('data.peers.1.id', $users[2]->id)
            ->assertJsonPath('data.peers.1.rank', 2)
            ->assertJsonPath('data.peers.1.deals_count', 11)
            ->assertJsonPath('data.my_rank.rank', 1)
            ->assertJsonPath('data.my_rank.deals_count', 12);
    }

    public function test_top_p2p_meetings_returns_ranked_peers(): void
    {
        $userA = $this->createUser('Peer A');
        $userB = $this->createUser('Peer B');
        $userC = $this->createUser('Peer C');

        // User A has 3 meetings as initiator
        for ($i = 0; $i < 3; $i++) {
            P2pMeeting::query()->create([
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $userA->id,
                'peer_user_id' => $userC->id,
                'meeting_date' => '2026-06-10',
                'is_deleted' => false,
            ]);
        }

        // User B has 1 meeting
        P2pMeeting::query()->create([
            'id' => (string) Str::uuid(),
            'initiator_user_id' => $userB->id,
            'peer_user_id' => $userC->id,
            'meeting_date' => '2026-06-10',
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($userB);

        // With default type 'all', User C has 4 meetings (3 with A, 1 with B) and User A has 3
        $responseAll = $this->getJson('/api/v1/leaderboards/p2p-meetings');
        $responseAll->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activity_type', 'p2p_meetings')
            ->assertJsonPath('data.peers.0.id', $userC->id)
            ->assertJsonPath('data.peers.0.rank', 1)
            ->assertJsonPath('data.peers.0.meetings_count', 4)
            ->assertJsonPath('data.peers.1.id', $userA->id)
            ->assertJsonPath('data.peers.1.rank', 2)
            ->assertJsonPath('data.peers.1.meetings_count', 3);

        // With type 'initiated', User A has 3 initiated meetings and is rank 1
        $responseInitiated = $this->getJson('/api/v1/leaderboards/p2p-meetings?type=initiated');
        $responseInitiated->assertOk()
            ->assertJsonPath('data.peers.0.id', $userA->id)
            ->assertJsonPath('data.peers.0.rank', 1)
            ->assertJsonPath('data.peers.0.meetings_count', 3);
    }

    public function test_top_testimonials_returns_ranked_peers(): void
    {
        $user1 = $this->createUser('Testimonial Peer 1');
        $user2 = $this->createUser('Testimonial Peer 2');
        $target = $this->createUser('Target Peer');

        // User 1 gives 2 testimonials
        for ($i = 1; $i <= 2; $i++) {
            Testimonial::query()->create([
                'id' => (string) Str::uuid(),
                'from_user_id' => $user1->id,
                'to_user_id' => $target->id,
                'content' => 'Great peer!',
                'rating' => 5,
                'is_deleted' => false,
            ]);
        }

        // User 2 gives 1 testimonial
        Testimonial::query()->create([
            'id' => (string) Str::uuid(),
            'from_user_id' => $user2->id,
            'to_user_id' => $target->id,
            'content' => 'Awesome collaboration!',
            'rating' => 4,
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/leaderboards/testimonials');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activity_type', 'testimonials')
            ->assertJsonPath('data.peers.0.id', $user1->id)
            ->assertJsonPath('data.peers.0.rank', 1)
            ->assertJsonPath('data.peers.0.testimonials_count', 2)
            ->assertJsonPath('data.peers.0.avg_rating', 5)
            ->assertJsonPath('data.peers.1.id', $user2->id)
            ->assertJsonPath('data.peers.1.testimonials_count', 1);
    }

    public function test_top_referrals_returns_ranked_peers(): void
    {
        $user1 = $this->createUser('Referral Peer 1');
        $user2 = $this->createUser('Referral Peer 2');
        $target = $this->createUser('Target Peer');

        for ($i = 1; $i <= 4; $i++) {
            Referral::query()->create([
                'id' => (string) Str::uuid(),
                'from_user_id' => $user1->id,
                'to_user_id' => $target->id,
                'referral_type' => 'business',
                'referral_date' => '2026-06-15',
                'is_deleted' => false,
            ]);
        }

        Referral::query()->create([
            'id' => (string) Str::uuid(),
            'from_user_id' => $user2->id,
            'to_user_id' => $target->id,
            'referral_type' => 'business',
            'referral_date' => '2026-06-15',
            'is_deleted' => false,
        ]);

        Sanctum::actingAs($user2);

        $response = $this->getJson('/api/v1/leaderboards/referrals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activity_type', 'referrals')
            ->assertJsonPath('data.peers.0.id', $user1->id)
            ->assertJsonPath('data.peers.0.rank', 1)
            ->assertJsonPath('data.peers.0.referrals_count', 4)
            ->assertJsonPath('data.peers.1.id', $user2->id)
            ->assertJsonPath('data.peers.1.rank', 2)
            ->assertJsonPath('data.peers.1.referrals_count', 1)
            ->assertJsonPath('data.my_rank.rank', 2)
            ->assertJsonPath('data.my_rank.referrals_count', 1);
    }

    public function test_limit_parameter_works(): void
    {
        $user1 = $this->createUser('Peer 1');
        $user2 = $this->createUser('Peer 2');
        $user3 = $this->createUser('Peer 3');
        $target = $this->createUser('Target Peer');

        foreach ([$user1, $user2, $user3] as $idx => $user) {
            for ($k = 0; $k <= (3 - $idx); $k++) {
                Referral::query()->create([
                    'id' => (string) Str::uuid(),
                    'from_user_id' => $user->id,
                    'to_user_id' => $target->id,
                    'referral_date' => '2026-06-15',
                    'is_deleted' => false,
                ]);
            }
        }

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/leaderboards/referrals?limit=2');

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(2, 'data.peers');
    }

    public function test_team_members_are_excluded_from_p2p_meetings_leaderboard(): void
    {
        $teamMemberTag = UserTag::where('slug', 'team_member')->firstOrFail();

        $normalPeer = $this->createUser('Normal Peer');
        $teamMember = $this->createUser('Team Member');
        $otherPeer = $this->createUser('Other Peer');

        // Assign team member tag
        UserTagAssignment::create([
            'user_id' => $teamMember->id,
            'tag_id' => $teamMemberTag->id,
        ]);

        // Team member has 10 meetings
        for ($i = 0; $i < 10; $i++) {
            P2pMeeting::query()->create([
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $teamMember->id,
                'peer_user_id' => $otherPeer->id,
                'meeting_date' => '2026-06-10',
                'is_deleted' => false,
            ]);
        }

        // Normal peer has 2 meetings
        for ($i = 0; $i < 2; $i++) {
            P2pMeeting::query()->create([
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $normalPeer->id,
                'peer_user_id' => $otherPeer->id,
                'meeting_date' => '2026-06-10',
                'is_deleted' => false,
            ]);
        }

        Sanctum::actingAs($normalPeer);

        $response = $this->getJson('/api/v1/leaderboards/p2p-meetings');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verify team member is NOT in the leaderboard peers
        $peerIds = collect($response->json('data.peers'))->pluck('id')->all();
        $this->assertNotContains($teamMember->id, $peerIds);
        $this->assertContains($normalPeer->id, $peerIds);
    }
}
