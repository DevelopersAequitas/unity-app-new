<?php

namespace Tests\Feature;

use App\Models\AwardCoinsHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoinMilestoneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('awards_coins_history');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('status', 50)->default('active');
            $table->string('membership_status', 50)->default('visitor');
            $table->bigInteger('coins_balance')->default(0);
            $table->string('public_profile_slug', 80)->nullable();
            $table->string('coin_medal_rank', 255)->nullable();
            $table->string('coin_milestone_title', 255)->nullable();
            $table->string('coin_milestone_meaning', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('awards_coins_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->bigInteger('coins_earned');
            $table->string('medal_rank');
            $table->string('title');
            $table->text('meaning');
            $table->timestamp('achieved_at');
            $table->timestamps();
        });
    }

    public function test_milestone_check_command_saves_correct_records_without_duplicates()
    {
        // 1. Create a user with 250,000 coins (qualifies for Bronze 100k and Silver 200k)
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'coins_balance' => 250000,
        ]);

        // 2. Run the milestone check command
        $this->artisan('coins:check-milestones')
            ->expectsOutputToContain('Completed!')
            ->assertExitCode(0);

        // 3. Verify history table has 2 records for this user
        $history = AwardCoinsHistory::where('user_id', $user->id)
            ->orderBy('coins_earned', 'asc')
            ->get();

        $this->assertCount(2, $history);

        $this->assertEquals(100000, $history[0]->coins_earned);
        $this->assertEquals('Bronze', $history[0]->medal_rank);
        $this->assertEquals('Unity Builder', $history[0]->title);

        $this->assertEquals(200000, $history[1]->coins_earned);
        $this->assertEquals('Silver', $history[1]->medal_rank);
        $this->assertEquals('Network Builder', $history[1]->title);

        // 4. Run again, verify no duplicates are created
        $this->artisan('coins:check-milestones')
            ->assertExitCode(0);

        $this->assertEquals(2, AwardCoinsHistory::where('user_id', $user->id)->count());
    }

    public function test_latest_milestone_api_returns_correct_current_and_next_progress()
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'coins_balance' => 250000,
        ]);

        // Authenticate the user
        Sanctum::actingAs($user);

        // Seed some history manually
        AwardCoinsHistory::create([
            'user_id' => $user->id,
            'coins_earned' => 100000,
            'medal_rank' => 'Bronze',
            'title' => 'Unity Builder',
            'meaning' => 'Stepped into the game',
            'achieved_at' => now()->subDay(),
        ]);

        AwardCoinsHistory::create([
            'user_id' => $user->id,
            'coins_earned' => 200000,
            'medal_rank' => 'Silver',
            'title' => 'Network Builder',
            'meaning' => 'Reliable, trusted',
            'achieved_at' => now(),
        ]);

        // Call latest milestone API
        $response = $this->getJson("/api/v1/users/{$user->id}/milestone/latest");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_coins_balance' => 250000,
                    'current_milestone' => [
                        'medal_rank' => 'Silver',
                        'title' => 'Network Builder',
                        'threshold' => 200000,
                    ],
                    'next_milestone' => [
                        'medal_rank' => 'Gold',
                        'title' => 'Action Leader',
                        'threshold' => 300000,
                        'coins_needed' => 50000,
                        'progress_percentage' => 50.0,
                    ],
                ],
            ]);
    }

    public function test_milestone_history_api_returns_all_achieved_ranks()
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'coins_balance' => 350000,
        ]);

        Sanctum::actingAs($user);

        // Seed history
        AwardCoinsHistory::create([
            'user_id' => $user->id,
            'coins_earned' => 100000,
            'medal_rank' => 'Bronze',
            'title' => 'Unity Builder',
            'meaning' => 'Stepped into the game',
            'achieved_at' => now()->subDays(2),
        ]);

        AwardCoinsHistory::create([
            'user_id' => $user->id,
            'coins_earned' => 200000,
            'medal_rank' => 'Silver',
            'title' => 'Network Builder',
            'meaning' => 'Reliable, trusted',
            'achieved_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/users/{$user->id}/milestone/history");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'medal_rank' => 'Bronze',
                        'title' => 'Unity Builder',
                    ],
                    [
                        'medal_rank' => 'Silver',
                        'title' => 'Network Builder',
                    ],
                ],
            ]);
    }
}
