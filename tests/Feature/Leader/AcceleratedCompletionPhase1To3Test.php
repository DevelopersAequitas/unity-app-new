<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Leader\Services\LeaderFinanceService;
use App\Leader\Services\LeaderReportsService;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\FinanceWallet;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcceleratedCompletionPhase1To3Test extends TestCase
{
    use RefreshDatabase;

    public function test_finance_cross_circle_payment_not_over_attributed_and_budget_scoped(): void
    {
        $circleA = Circle::query()->create(['id' => (string) Str::uuid(), 'name' => 'Circle A']);
        $circleB = Circle::query()->create(['id' => (string) Str::uuid(), 'name' => 'Circle B']);

        $peer = User::factory()->create(['active_circle_id' => $circleA->id]);
        CircleMember::query()->create(['id' => (string) Str::uuid(), 'circle_id' => $circleA->id, 'user_id' => $peer->id, 'status' => 'approved']);
        CircleMember::query()->create(['id' => (string) Str::uuid(), 'circle_id' => $circleB->id, 'user_id' => $peer->id, 'status' => 'approved']);

        Payment::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $peer->id,
            'circle_id' => $circleA->id,
            'amount' => 5000,
            'base_amount' => 5000,
            'total_amount' => 5000,
            'status' => 'Paid',
            'paid_at' => now(),
        ]);

        FinanceWallet::query()->create([
            'id' => (string) Str::uuid(),
            'wallet_type' => 'circle_budget',
            'scope_id' => $circleA->id,
            'current_balance' => 10000,
        ]);
        FinanceWallet::query()->create([
            'id' => (string) Str::uuid(),
            'wallet_type' => 'circle_budget',
            'scope_id' => $circleB->id,
            'current_balance' => 25000,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $financeService = app(LeaderFinanceService::class);

        $txB = $financeService->getTransactions($circleB->id, null, $admin);
        $this->assertCount(0, $txB);

        $recorded = $financeService->recordOfflinePayment($admin, [
            'peer_id' => $peer->id,
            'circle_id' => $circleA->id,
            'amount' => 1500,
            'payment_mode' => 'NEFT',
        ]);
        $this->assertSame($circleA->id, $recorded['circle_id']);
    }

    public function test_reports_do_not_use_random_circle_or_fabricated_defaults(): void
    {
        $service = app(LeaderReportsService::class);
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->expectException(ValidationException::class);
        $service->submitReport(['report_type' => 'Monthly'], (string) $user->id, $user);
    }

    public function test_logout_revokes_sanctum_token(): void
    {
        $leader = User::factory()->create(['role' => 'circle_founder']);
        $token = $leader->createToken('leader-app');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/leader/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
