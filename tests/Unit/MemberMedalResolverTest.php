<?php

namespace Tests\Unit;

use App\Support\MemberMedalResolver;
use PHPUnit\Framework\TestCase;

class MemberMedalResolverTest extends TestCase
{
    public function test_it_returns_null_values_below_bronze_threshold(): void
    {
        $resolved = MemberMedalResolver::resolve(99999);

        $this->assertSame([
            'medal_rank' => null,
            'medal_title' => null,
            'medal_meaning' => null,
            'medal_vibe' => null,
        ], $resolved);
    }

    public function test_it_resolves_diamond_medal_data(): void
    {
        $resolved = MemberMedalResolver::resolve(1106000);

        $this->assertSame('Diamond', $resolved['medal_rank']);
        $this->assertSame('Community Star', $resolved['medal_title']);
        $this->assertSame('You are a standout contributor with major community impact.', $resolved['medal_meaning']);
        $this->assertSame('Elite influencer', $resolved['medal_vibe']);
    }

    public function test_it_handles_null_balance_safely(): void
    {
        $resolved = MemberMedalResolver::resolve(null);

        $this->assertNull($resolved['medal_rank']);
        $this->assertNull($resolved['medal_title']);
        $this->assertNull($resolved['medal_meaning']);
        $this->assertNull($resolved['medal_vibe']);
    }

    public function test_it_resolves_supreme_for_two_million_and_above(): void
    {
        $resolved = MemberMedalResolver::resolve(2500000);

        $this->assertSame('Supreme', $resolved['medal_rank']);
        $this->assertSame('Unity Icon', $resolved['medal_title']);
    }
}
