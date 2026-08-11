<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CoinsLedger;
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

        Schema::dropIfExists('users');
        Schema::dropIfExists('coins_ledger');

        Schema::create('users', function (Blueprint $table): void {
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

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('reference', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_milestone_check_command_runs_successfully()
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'coins_balance' => 250000,
        ]);

        $this->artisan('coins:check-milestones')
            ->expectsOutputToContain('Completed!')
            ->assertExitCode(0);
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

        Sanctum::actingAs($user);

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

    public function test_milestone_history_api_returns_ledger_transactions()
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'coins_balance' => 350000,
        ]);

        CoinsLedger::query()->create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'amount' => 100000,
            'balance_after' => 100000,
            'reference' => 'Initial bonus',
            'created_at' => now()->subDays(2),
        ]);

        CoinsLedger::query()->create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'amount' => 100000,
            'balance_after' => 200000,
            'reference' => 'Activity bonus',
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$user->id}/milestone/history");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'amount' => 100000,
                        'balance_after' => 100000,
                        'medal_rank' => 'Bronze',
                        'title' => 'Unity Builder',
                    ],
                    [
                        'amount' => 100000,
                        'balance_after' => 200000,
                        'medal_rank' => 'Silver',
                        'title' => 'Network Builder',
                    ],
                ],
            ]);
    }
}
