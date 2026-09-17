<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTagLeaderboardExclusionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('user_tag_assignments');
        Schema::dropIfExists('user_tags');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('company_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('profile_photo_file_id')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->string('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->string('contribution_award_recognition')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

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
    }

    private function createUser(string $name, int $coins, int $impacts): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => trim(strstr($name, ' ') ?: ''),
            'display_name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
            'coins_balance' => $coins,
            'life_impacted_count' => $impacts,
            'status' => 'active',
        ]);
    }

    public function test_team_member_is_excluded_from_coins_leaderboard(): void
    {
        $teamTag = UserTag::where('slug', 'team_member')->first();

        $normalUser1 = $this->createUser('Top Leader', 5000, 50);
        $teamMember = $this->createUser('Internal Tester', 4000, 40);
        $normalUser2 = $this->createUser('Normal User', 3000, 30);

        // Assign team member tag
        $teamMember->assignTag($teamTag);

        $response = $this->getJson('/api/v1/leaderboards/coins');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.members')
            ->assertJsonPath('data.members.0.id', $normalUser1->id)
            ->assertJsonPath('data.members.1.id', $normalUser2->id);

        $this->assertFalse(collect($response->json('data.members'))->pluck('id')->contains($teamMember->id));
    }

    public function test_team_member_is_excluded_from_impacts_leaderboard(): void
    {
        $teamTag = UserTag::where('slug', 'team_member')->first();

        $normalUser1 = $this->createUser('Impact Star', 100, 500);
        $teamMember = $this->createUser('QA Engineer', 100, 400);
        $normalUser2 = $this->createUser('Impact Junior', 100, 300);

        $teamMember->assignTag($teamTag);

        $response = $this->getJson('/api/v1/leaderboards/impacts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.members')
            ->assertJsonPath('data.members.0.id', $normalUser1->id)
            ->assertJsonPath('data.members.1.id', $normalUser2->id);

        $this->assertFalse(collect($response->json('data.members'))->pluck('id')->contains($teamMember->id));
    }

    public function test_removing_team_member_tag_restores_user_to_leaderboard(): void
    {
        $teamTag = UserTag::where('slug', 'team_member')->first();

        $user = $this->createUser('Temporary Team Member', 5000, 500);
        $user->assignTag($teamTag);

        // Verify excluded
        $res1 = $this->getJson('/api/v1/leaderboards/coins');
        $this->assertFalse(collect($res1->json('data.members'))->pluck('id')->contains($user->id));

        // Remove tag
        $user->removeTag($teamTag);

        // Verify restored
        $res2 = $this->getJson('/api/v1/leaderboards/coins');
        $this->assertTrue(collect($res2->json('data.members'))->pluck('id')->contains($user->id));
    }

    public function test_other_tags_do_not_exclude_user_from_leaderboards(): void
    {
        $developerTag = UserTag::create([
            'name' => 'Developer',
            'slug' => 'developer',
            'is_active' => true,
        ]);

        $user = $this->createUser('Developer User', 5000, 500);
        $user->assignTag($developerTag);

        $response = $this->getJson('/api/v1/leaderboards/coins');
        $this->assertTrue(collect($response->json('data.members'))->pluck('id')->contains($user->id));
    }
}
