<?php

declare(strict_types=1);

namespace App\Services\Sponsorship;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SponsorshipMilestoneService
{
    private const MILESTONES = [
        [
            'threshold' => 1,
            'award_name' => 'The Connector Award',
            'recognition' => 'Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 2,
            'award_name' => 'Rising Voice Award',
            'recognition' => 'Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 3,
            'award_name' => 'Community Catalyst Award',
            'recognition' => 'Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 4,
            'award_name' => 'Voice of Change Award',
            'recognition' => 'Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 5,
            'award_name' => 'Influencer Award',
            'recognition' => 'Entry to Influencers Club',
        ],
        [
            'threshold' => 6,
            'award_name' => 'Inspiration Icon Award',
            'recognition' => 'Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 8,
            'award_name' => 'Super Star Award',
            'recognition' => 'Premium Recognition + Podcast Invite',
        ],
        [
            'threshold' => 10,
            'award_name' => 'Global Star',
            'recognition' => 'Recognition at City Meet',
        ],
        [
            'threshold' => 12,
            'award_name' => 'Legacy Creator',
            'recognition' => 'Trophy + Digital Certificate + Social Media Spotlight',
        ],
        [
            'threshold' => 15,
            'award_name' => 'Impact Creator Award',
            'recognition' => '₹1L Membership Credit (in kind) + City Convention Recognition',
        ],
        [
            'threshold' => 20,
            'award_name' => 'Nation Builder Award',
            'recognition' => 'Trophy + National Platform Honor',
        ],
        [
            'threshold' => 25,
            'award_name' => 'Peers Global Hall of Fame 👑',
            'recognition' => 'Crown Pin + Lifetime Badge + Unity Wall of Fame Feature',
        ],
    ];

    /**
     * Resolve milestones for a given count of sponsored members.
     *
     * @return array{total_sponsored_members:int, current_milestone:int, award_name:?string, recognition:?string, next_milestone:?int, members_remaining:int}
     */
    public function resolveMilestone(int $count): array
    {
        $currentMilestone = 0;
        $awardName = null;
        $recognition = null;

        foreach (self::MILESTONES as $milestone) {
            if ($count >= $milestone['threshold']) {
                $currentMilestone = $milestone['threshold'];
                $awardName = $milestone['award_name'];
                $recognition = $milestone['recognition'];
            } else {
                break;
            }
        }

        // Find next milestone details
        $nextMilestone = null;
        $membersRemaining = 0;

        if ($count < 25) {
            foreach (self::MILESTONES as $milestone) {
                if ($milestone['threshold'] > $count) {
                    $nextMilestone = $milestone['threshold'];
                    $membersRemaining = $milestone['threshold'] - $count;
                    break;
                }
            }
            if ($nextMilestone === null) {
                $nextMilestone = 1;
                $membersRemaining = 1;
            }
        }

        return [
            'total_sponsored_members' => $count,
            'current_milestone' => $currentMilestone,
            'award_name' => $awardName,
            'recognition' => $recognition,
            'next_milestone' => $nextMilestone,
            'members_remaining' => $membersRemaining,
        ];
    }

    /**
     * Map milestone threshold to its exact range [min, max] of counts.
     *
     * @return array{int, ?int}|null
     */
    public static function getCountRangeForMilestone(int $milestone): ?array
    {
        return match ($milestone) {
            0 => [0, 0],
            1 => [1, 1],
            2 => [2, 2],
            3 => [3, 3],
            4 => [4, 4],
            5 => [5, 5],
            6 => [6, 7],
            8 => [8, 9],
            10 => [10, 11],
            12 => [12, 14],
            15 => [15, 19],
            20 => [20, 24],
            25 => [25, null],
            default => null,
        };
    }

    /**
     * Map award name to its milestone threshold.
     */
    public static function getMilestoneForAwardName(string $awardName): ?int
    {
        $normalized = strtolower(trim($awardName));

        return match ($normalized) {
            'the connector award' => 1,
            'rising voice award' => 2,
            'community catalyst award' => 3,
            'voice of change award' => 4,
            'influencer award' => 5,
            'inspiration icon award' => 6,
            'super star award' => 8,
            'global star' => 10,
            'legacy creator' => 12,
            'impact creator award' => 15,
            'nation builder award' => 20,
            'peers global hall of fame 👑', 'peers global hall of fame' => 25,
            default => null,
        };
    }

    /**
     * Build the query for counting sponsored members.
     */
    public function buildSponsoredMembersQuery(string $sponsorId): Builder
    {
        $query = User::query()
            ->where('introduced_by', $sponsorId)
            ->where('is_sponsored_member', true);

        if (Schema::hasColumn('users', 'status')) {
            $query->whereNotIn(DB::raw('CAST(status AS TEXT)'), ['rejected', 'cancelled', 'inactive', 'pending']);
        }

        if (Schema::hasColumn('users', 'approval_status')) {
            $query->where('approval_status', 'approved');
        }

        return $query;
    }

    /**
     * Count valid sponsored members.
     */
    public function countSponsoredMembers(string $sponsorId): int
    {
        return $this->buildSponsoredMembersQuery($sponsorId)->count();
    }
}
