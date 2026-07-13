<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinClaimsActivitiesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_api_returns_scaled_coins(): void
    {
        $user = User::factory()->create([
            'coins_balance' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/coin-claims/activities');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $code = $item['code'];
            $coins = $item['coins'];

            // Expected coins from config/coins.php (e.g. 1000 for attend_circle_meeting)
            $expectedCoins = (int) config("coins.claim_coin.{$code}");
            $this->assertSame($expectedCoins, $coins);
        }
    }
}
