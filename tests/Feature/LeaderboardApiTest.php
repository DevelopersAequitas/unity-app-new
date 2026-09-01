<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaderboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
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

    public function test_coins_leaderboard_user_in_top_20(): void
    {
        // Create 25 users
        $users = [];
        for ($i = 1; $i <= 25; $i++) {
            $users[] = $this->createUser('User '.$i, 1000 - ($i * 10), 0);
        }

        // Log in as User 5 (which will rank 5th, in Top 20)
        Sanctum::actingAs($users[4]);

        $response = $this->getJson('/api/v1/leaderboards/coins');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leaderboard_type', 'coins')
            ->assertJsonCount(20, 'data.members')
            ->assertJsonPath('data.my_rank', null);
    }

    public function test_coins_leaderboard_user_not_in_top_20(): void
    {
        // Create 25 users
        $users = [];
        for ($i = 1; $i <= 25; $i++) {
            $users[] = $this->createUser('User '.$i, 1000 - ($i * 10), 0);
        }

        // Log in as User 25 (which will rank 25th, outside Top 20)
        Sanctum::actingAs($users[24]);

        $response = $this->getJson('/api/v1/leaderboards/coins');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leaderboard_type', 'coins')
            ->assertJsonCount(20, 'data.members')
            ->assertJsonPath('data.my_rank.rank', 25)
            ->assertJsonPath('data.my_rank.id', $users[24]->id)
            ->assertJsonPath('data.my_rank.display_name', $users[24]->display_name);
    }

    public function test_impacts_leaderboard_user_in_top_20(): void
    {
        // Create 25 users
        $users = [];
        for ($i = 1; $i <= 25; $i++) {
            $users[] = $this->createUser('User '.$i, 0, 100 - $i);
        }

        // Log in as User 10 (which will rank 10th, in Top 20)
        Sanctum::actingAs($users[9]);

        $response = $this->getJson('/api/v1/leaderboards/impacts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leaderboard_type', 'impacts')
            ->assertJsonCount(20, 'data.members')
            ->assertJsonPath('data.my_rank', null);
    }

    public function test_impacts_leaderboard_user_not_in_top_20(): void
    {
        // Create 25 users
        $users = [];
        for ($i = 1; $i <= 25; $i++) {
            $users[] = $this->createUser('User '.$i, 0, 100 - $i);
        }

        // Log in as User 23 (which will rank 23rd, outside Top 20)
        Sanctum::actingAs($users[22]);

        $response = $this->getJson('/api/v1/leaderboards/impacts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leaderboard_type', 'impacts')
            ->assertJsonCount(20, 'data.members')
            ->assertJsonPath('data.my_rank.rank', 23)
            ->assertJsonPath('data.my_rank.id', $users[22]->id)
            ->assertJsonPath('data.my_rank.display_name', $users[22]->display_name);
    }
}
