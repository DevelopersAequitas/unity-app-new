<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MilestoneBadge;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Services\MilestoneBadgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MilestoneBadgeCalculationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('status', 50)->default('active');
                $table->bigInteger('coins_balance')->default(0);
                $table->integer('life_impacted_count')->default(0);
                $table->integer('members_introduced_count')->default(0);
                $table->string('coin_medal_rank', 255)->nullable();
                $table->string('coin_milestone_title', 255)->nullable();
                $table->string('coin_milestone_meaning', 255)->nullable();
                $table->string('contribution_award_name', 255)->nullable();
                $table->string('contribution_award_recognition', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('milestone_badges')) {
            Schema::create('milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type', 50);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->bigInteger('required_count')->default(0);
                $table->string('badge_image_url', 2000)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_milestone_badges')) {
            Schema::create('user_milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('badge_id');
                $table->string('milestone_type', 50);
                $table->bigInteger('achieved_count')->default(0);
                $table->string('status', 50)->default('earned');
                $table->timestamp('earned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe.'.Str::random(5).'@example.com',
            'coins_balance' => 0,
            'life_impacted_count' => 0,
            'members_introduced_count' => 0,
        ], $overrides));
    }

    public function test_user_below_threshold_does_not_receive_badge(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Change Maker',
            'required_count' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['life_impacted_count' => 5]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $this->assertDatabaseMissing('user_milestone_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ]);
    }

    public function test_user_exactly_at_threshold_receives_badge(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'coins',
            'title' => 'Coin Starter',
            'required_count' => 5000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['coins_balance' => 5000]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $this->assertDatabaseHas('user_milestone_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
            'achieved_count' => 5000,
        ]);
    }

    public function test_user_above_threshold_receives_badge(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'member_introduction',
            'title' => 'Connector',
            'required_count' => 5,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['members_introduced_count' => 13]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $this->assertDatabaseHas('user_milestone_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
            'achieved_count' => 13,
        ]);
    }

    public function test_multiple_badges_earned_in_same_category(): void
    {
        $b1 = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Change Maker',
            'required_count' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $b2 = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Life Transformer',
            'required_count' => 25,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $b3 = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Life Champion',
            'required_count' => 50,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $user = $this->createUser(['life_impacted_count' => 27]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $earnedBadges = UserMilestoneBadge::where('user_id', $user->id)
            ->where('status', 'earned')
            ->pluck('badge_id')
            ->toArray();

        $this->assertContains($b1->id, $earnedBadges);
        $this->assertContains($b2->id, $earnedBadges);
        $this->assertNotContains($b3->id, $earnedBadges);
    }

    public function test_badge_is_revoked_when_count_decreases_below_threshold(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Life Transformer',
            'required_count' => 25,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['life_impacted_count' => 27]);
        app(MilestoneBadgeService::class)->calculateForUser($user);

        $this->assertDatabaseHas('user_milestone_badges', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'status' => 'earned',
        ]);

        // User count decreases to 8
        $user->update(['life_impacted_count' => 8]);

        $userBadge = UserMilestoneBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        $this->assertNotNull($userBadge);
        $this->assertEquals('revoked', $userBadge->status);
        $this->assertNotNull($userBadge->revoked_at);
        $this->assertEquals(8, $userBadge->achieved_count);
    }

    public function test_badge_can_be_re_earned_after_revocation(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Change Maker',
            'required_count' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['life_impacted_count' => 15]);
        app(MilestoneBadgeService::class)->calculateForUser($user);

        // Decrease count
        $user->update(['life_impacted_count' => 5]);
        $userBadge = UserMilestoneBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->first();
        $this->assertEquals('revoked', $userBadge->status);

        // Increase count again to 12
        $user->update(['life_impacted_count' => 12]);

        $userBadge->refresh();
        $this->assertEquals('earned', $userBadge->status);
        $this->assertNull($userBadge->revoked_at);
        $this->assertNotNull($userBadge->earned_at);
        $this->assertEquals(12, $userBadge->achieved_count);

        // Verify duplicate records are not created
        $this->assertEquals(1, UserMilestoneBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->count());
    }

    public function test_latest_and_history_apis_return_expected_structure(): void
    {
        $badge = MilestoneBadge::create([
            'type' => 'coins',
            'title' => 'Coin Starter',
            'required_count' => 5000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createUser(['coins_balance' => 6000]);
        app(MilestoneBadgeService::class)->calculateForUser($user);

        Sanctum::actingAs($user);

        // Call latest API
        $latestRes = $this->getJson("/api/v1/users/{$user->id}/milestone/latest");
        $latestRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_life_impacted' => 0,
                    'badges' => [
                        'coins' => [
                            [
                                'badge_id' => $badge->id,
                                'title' => 'Coin Starter',
                                'type' => 'coins',
                                'status' => 'earned',
                            ],
                        ],
                    ],
                ],
            ]);

        // Call history API
        $historyRes = $this->getJson("/api/v1/users/{$user->id}/milestone/history");
        $historyRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'badges' => [
                    'coins' => [
                        [
                            'badge_id' => $badge->id,
                            'title' => 'Coin Starter',
                            'type' => 'coins',
                            'status' => 'earned',
                        ],
                    ],
                ],
            ]);
    }
}
