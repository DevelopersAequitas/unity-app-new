<?php

namespace App\Support;

class MemberMedalResolver
{
    /**
     * @return array{medal_rank:?string,medal_title:?string,medal_meaning:?string,medal_vibe:?string}
     */
    public static function resolve(int|float|null $coinsBalance): array
    {
        $balance = (int) floor((float) ($coinsBalance ?? 0));

        return match (true) {
            $balance >= 2000000 => self::medal(
                'Supreme',
                'Unity Icon',
                'You represent the highest level of contribution, leadership, and impact.',
                'Top-tier visionary'
            ),
            $balance >= 1500000 => self::medal(
                'Elite',
                'Collaboration Legend',
                'You have built exceptional trust, collaboration, and results.',
                'Legendary connector'
            ),
            $balance >= 1000000 => self::medal(
                'Diamond',
                'Community Star',
                'You are a standout contributor with major community impact.',
                'Elite influencer'
            ),
            $balance >= 750000 => self::medal(
                'Titanium',
                'Synergy Architect',
                'You create strong collaborations and long-term value.',
                'Strategic collaborator'
            ),
            $balance >= 500000 => self::medal(
                'Platinum',
                'Growth Champion',
                'You are driving major growth through contribution and leadership.',
                'Growth-focused powerhouse'
            ),
            $balance >= 300000 => self::medal(
                'Gold',
                'Action Leader',
                'Your actions are creating visible momentum in the community.',
                'High-energy achiever'
            ),
            $balance >= 200000 => self::medal(
                'Silver',
                'Network Builder',
                'You are actively expanding your network and influence.',
                'Growing connector'
            ),
            $balance >= 100000 => self::medal(
                'Bronze',
                'Unity Builder',
                'You started creating impact and building meaningful connections.',
                'Strong foundation builder'
            ),
            default => self::medal(),
        };
    }

    /**
     * @return array{medal_rank:?string,medal_title:?string,medal_meaning:?string,medal_vibe:?string}
     */
    private static function medal(
        ?string $rank = null,
        ?string $title = null,
        ?string $meaning = null,
        ?string $vibe = null
    ): array {
        return [
            'medal_rank' => $rank,
            'medal_title' => $title,
            'medal_meaning' => $meaning,
            'medal_vibe' => $vibe,
        ];
    }
}
