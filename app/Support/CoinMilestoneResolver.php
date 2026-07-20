<?php

namespace App\Support;

class CoinMilestoneResolver
{
    /**
     * @var array<int, array{threshold:int, medal_rank:string, title:string, meaning:string}>
     */
    private const MILESTONES = [
        [
            'threshold' => 100000,
            'medal_rank' => 'Bronze',
            'title' => 'Unity Builder',
            'meaning' => 'You’ve stepped into the game — your journey of consistent contribution has begun.',
        ],
        [
            'threshold' => 200000,
            'medal_rank' => 'Silver',
            'title' => 'Network Builder',
            'meaning' => 'Reliable, trusted, and visibly present. You’re building real, lasting momentum.',
        ],
        [
            'threshold' => 300000,
            'medal_rank' => 'Gold',
            'title' => 'Action Leader',
            'meaning' => 'You lift your peers up, contribute actively, and power up your circle’s performance.',
        ],
        [
            'threshold' => 500000,
            'medal_rank' => 'Platinum',
            'title' => 'Growth Champion',
            'meaning' => "You're making your mark with energy, referrals, visibility, and engagement.",
        ],
        [
            'threshold' => 750000,
            'medal_rank' => 'Titanium',
            'title' => 'Synergy Architect',
            'meaning' => 'You know how to collaborate deeply, create win-wins, and keep the vibe high.',
        ],
        [
            'threshold' => 1000000,
            'medal_rank' => 'Diamond',
            'title' => 'Community Star',
            'meaning' => "Your actions inspire. You're not just growing — you're building community strength.",
        ],
        [
            'threshold' => 1500000,
            'medal_rank' => 'Elite',
            'title' => 'Collaboration Legend',
            'meaning' => 'Trusted by all, respected by many — your presence drives people to take action.',
        ],
        [
            'threshold' => 2000000,
            'medal_rank' => 'Supreme',
            'title' => 'Collaboration Champion',
            'meaning' => 'You’ve cracked the code — contribution with consistency and strategic purpose.',
        ],
        [
            'threshold' => 3000000,
            'medal_rank' => 'Royal',
            'title' => 'Unity Champion',
            'meaning' => 'You’re not just a member; you’re an engine of progress across multiple circles.',
        ],
        [
            'threshold' => 5000000,
            'medal_rank' => 'Super Star',
            'title' => 'Global Collaborator',
            'meaning' => 'Your influence now spans regions — collaboration at scale, value at every level.',
        ],
        [
            'threshold' => 7500000,
            'medal_rank' => 'Champion',
            'title' => 'Ecosystem Architect',
            'meaning' => 'Wherever you show up, growth follows — you multiply possibilities for others.',
        ],
        [
            'threshold' => 10000000,
            'medal_rank' => 'Crown',
            'title' => 'Peers Global Titan 👑',
            'meaning' => 'The summit of contribution. A beacon of excellence and an icon of Peers Global legacy.',
        ],
    ];

    /**
     * @return array<int, array{threshold:int, medal_rank:string, title:string, meaning:string}>
     */
    public static function getMilestones(): array
    {
        return self::MILESTONES;
    }

    /**
     * @return array{medal_rank:?string,title:?string,meaning:?string}
     */
    public static function resolve(int|float|null $coinsBalance): array
    {
        $balance = (int) floor((float) ($coinsBalance ?? 0));

        $resolved = [
            'medal_rank' => null,
            'title' => null,
            'meaning' => null,
        ];

        foreach (self::MILESTONES as $milestone) {
            if ($balance < $milestone['threshold']) {
                break;
            }

            $resolved = [
                'medal_rank' => $milestone['medal_rank'],
                'title' => $milestone['title'],
                'meaning' => $milestone['meaning'],
            ];
        }

        return $resolved;
    }
}
