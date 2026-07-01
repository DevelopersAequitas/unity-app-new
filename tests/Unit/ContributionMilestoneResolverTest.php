<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ContributionMilestoneResolver;
use PHPUnit\Framework\TestCase;

class ContributionMilestoneResolverTest extends TestCase
{
    public function test_it_returns_empty_values_when_count_is_null_or_zero(): void
    {
        $this->assertSame([
            'award_name' => null,
            'recognition' => null,
        ], ContributionMilestoneResolver::resolve(null));

        $this->assertSame([
            'award_name' => null,
            'recognition' => null,
        ], ContributionMilestoneResolver::resolve(0));
    }

    public function test_it_resolves_exactly_one(): void
    {
        $resolved = ContributionMilestoneResolver::resolve(1);

        $this->assertSame('The Connector Award', $resolved['award_name']);
        $this->assertSame('Digital Certificate + Social Media Spotlight', $resolved['recognition']);
    }

    public function test_it_resolves_exactly_five(): void
    {
        $resolved = ContributionMilestoneResolver::resolve(5);

        $this->assertSame('Influencer Award', $resolved['award_name']);
        $this->assertSame('Entry to Influencers Club', $resolved['recognition']);
    }

    public function test_it_resolves_nine_to_super_star_award(): void
    {
        $resolved = ContributionMilestoneResolver::resolve(9);

        $this->assertSame('Super Star Award', $resolved['award_name']);
        $this->assertSame('Premium Recognition + Podcast Invite', $resolved['recognition']);
    }

    public function test_it_resolves_fifteen_to_impact_creator_award(): void
    {
        $resolved = ContributionMilestoneResolver::resolve(15);

        $this->assertSame('Impact Creator Award', $resolved['award_name']);
        $this->assertSame('₹1L Membership Credit (in kind) + City Convention Recognition', $resolved['recognition']);
    }

    public function test_it_resolves_twenty_five_or_above_to_hall_of_fame(): void
    {
        $resolved = ContributionMilestoneResolver::resolve(30);

        $this->assertSame('Peers Global Hall of Fame 👑', $resolved['award_name']);
        $this->assertSame('Crown Pin + Lifetime Badge + Unity Wall of Fame Feature', $resolved['recognition']);
    }

    public function test_user_model_sync_method_updates_contribution_fields_from_count(): void
    {
        $user = new User;
        $user->members_introduced_count = 16;

        $changed = $user->syncContributionMilestoneAttributes();

        $this->assertTrue($changed);
        $this->assertSame('Impact Creator Award', $user->contribution_award_name);
        $this->assertSame('₹1L Membership Credit (in kind) + City Convention Recognition', $user->contribution_award_recognition);
    }
}
