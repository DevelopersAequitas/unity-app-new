<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\CoinMilestoneResolver;
use PHPUnit\Framework\TestCase;

class CoinMilestoneResolverTest extends TestCase
{
    public function test_it_returns_empty_values_below_first_milestone(): void
    {
        $resolved = CoinMilestoneResolver::resolve(99999);

        $this->assertSame([
            'medal_rank' => null,
            'title' => null,
            'meaning' => null,
        ], $resolved);
    }

    public function test_it_resolves_exact_milestone(): void
    {
        $resolved = CoinMilestoneResolver::resolve(100000);

        $this->assertSame('Bronze', $resolved['medal_rank']);
        $this->assertSame('Unity Builder', $resolved['title']);
    }

    public function test_it_resolves_between_milestones_to_highest_eligible(): void
    {
        $resolved = CoinMilestoneResolver::resolve(320000);

        $this->assertSame('Gold', $resolved['medal_rank']);
        $this->assertSame('Action Leader', $resolved['title']);
    }

    public function test_it_resolves_ten_lakh_balance(): void
    {
        $resolved = CoinMilestoneResolver::resolve(1000000);

        $this->assertSame('Diamond', $resolved['medal_rank']);
        $this->assertSame('Community Star', $resolved['title']);
    }

    public function test_it_resolves_above_hundred_lakh_balance_to_crown(): void
    {
        $resolved = CoinMilestoneResolver::resolve(12000000);

        $this->assertSame('Crown', $resolved['medal_rank']);
        $this->assertSame('Peers Global Titan 👑', $resolved['title']);
    }

    public function test_user_model_sync_method_updates_fields_from_balance(): void
    {
        $user = new User;
        $user->coins_balance = 820000;

        $changed = $user->syncCoinMilestoneAttributes();

        $this->assertTrue($changed);
        $this->assertSame('Titanium', $user->coin_medal_rank);
        $this->assertSame('Synergy Architect', $user->coin_milestone_title);
    }
}
