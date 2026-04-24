<?php

namespace App\Http\Resources;

class MemberListResource extends UserResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $medal = $this->resolveMemberListMedal();

        $data['medal_rank'] = $medal['medal_rank'];
        $data['medal_title'] = $medal['medal_title'];
        $data['medal_meaning'] = $medal['medal_meaning'];
        $data['medal_vibe'] = $medal['medal_vibe'];

        return $data;
    }

    /**
     * @return array{medal_rank:?string,medal_title:?string,medal_meaning:?string,medal_vibe:?string}
     */
    private function resolveMemberListMedal(): array
    {
        $coinsBalance = (int) ($this->coins_balance ?? 0);

        if ($coinsBalance < 100000) {
            return [
                'medal_rank' => null,
                'medal_title' => null,
                'medal_meaning' => null,
                'medal_vibe' => null,
            ];
        }

        return match (true) {
            $coinsBalance < 200000 => [
                'medal_rank' => 'Bronze',
                'medal_title' => 'Unity Builder',
                'medal_meaning' => 'You started creating impact and building meaningful connections.',
                'medal_vibe' => 'Strong foundation builder',
            ],
            $coinsBalance < 300000 => [
                'medal_rank' => 'Silver',
                'medal_title' => 'Network Builder',
                'medal_meaning' => 'You are actively expanding your network and influence.',
                'medal_vibe' => 'Growing connector',
            ],
            $coinsBalance < 500000 => [
                'medal_rank' => 'Gold',
                'medal_title' => 'Action Leader',
                'medal_meaning' => 'Your actions are creating visible momentum in the community.',
                'medal_vibe' => 'High-energy achiever',
            ],
            $coinsBalance < 750000 => [
                'medal_rank' => 'Platinum',
                'medal_title' => 'Growth Champion',
                'medal_meaning' => 'You are driving major growth through contribution and leadership.',
                'medal_vibe' => 'Growth-focused powerhouse',
            ],
            $coinsBalance < 1000000 => [
                'medal_rank' => 'Titanium',
                'medal_title' => 'Synergy Architect',
                'medal_meaning' => 'You create strong collaborations and long-term value.',
                'medal_vibe' => 'Strategic collaborator',
            ],
            $coinsBalance < 1500000 => [
                'medal_rank' => 'Diamond',
                'medal_title' => 'Community Star',
                'medal_meaning' => 'You are a standout contributor with major community impact.',
                'medal_vibe' => 'Elite influencer',
            ],
            $coinsBalance < 2000000 => [
                'medal_rank' => 'Elite',
                'medal_title' => 'Collaboration Legend',
                'medal_meaning' => 'You have built exceptional trust, collaboration, and results.',
                'medal_vibe' => 'Legendary connector',
            ],
            default => [
                'medal_rank' => 'Supreme',
                'medal_title' => 'Unity Icon',
                'medal_meaning' => 'You represent the highest level of contribution, leadership, and impact.',
                'medal_vibe' => 'Top-tier visionary',
            ],
        };
    }
}
