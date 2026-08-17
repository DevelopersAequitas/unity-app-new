<?php

declare(strict_types=1);

namespace App\Support;

class ContributionMilestoneResolver
{
    /**
     * @var array<int, array{threshold:int, award_name:string, recognition:string}>
     */
    private const MILESTONES = [
        [
            'threshold' => 1,
            'award_name' => 'Connector',
            'recognition' => 'Digital Honour',
        ],
        [
            'threshold' => 3,
            'award_name' => 'Catalyst',
            'recognition' => 'Digital Honour',
        ],
        [
            'threshold' => 5,
            'award_name' => 'Influencer',
            'recognition' => 'Digital Honour',
        ],
        [
            'threshold' => 10,
            'award_name' => 'Ambassador',
            'recognition' => 'Digital Honour',
        ],
        [
            'threshold' => 20,
            'award_name' => 'Rainmaker',
            'recognition' => 'Circle Honour (Pinned before your Circle)',
        ],
        [
            'threshold' => 35,
            'award_name' => 'Trailblazer',
            'recognition' => 'Circle Honour (Pinned before your Circle)',
        ],
        [
            'threshold' => 50,
            'award_name' => 'Vanguard',
            'recognition' => 'Circle Honour (Pinned before your Circle)',
        ],
        [
            'threshold' => 75,
            'award_name' => 'Luminary',
            'recognition' => 'City Honour (Pinned at City Meeting)',
        ],
        [
            'threshold' => 100,
            'award_name' => 'Movement Maker',
            'recognition' => 'City Honour (Pinned at City Meeting)',
        ],
        [
            'threshold' => 150,
            'award_name' => 'Community Titan',
            'recognition' => 'City Honour (Pinned at City Meeting)',
        ],
        [
            'threshold' => 250,
            'award_name' => 'Network Architect',
            'recognition' => 'National Honour (Awarded on National Stage)',
        ],
        [
            'threshold' => 500,
            'award_name' => 'Global Icon',
            'recognition' => 'National Honour (Awarded on National Stage)',
        ],
    ];

    /**
     * @return array{award_name:?string,recognition:?string}
     */
    public static function resolve(int|float|null $introducedCount): array
    {
        $count = (int) floor((float) ($introducedCount ?? 0));

        $resolved = [
            'award_name' => null,
            'recognition' => null,
        ];

        foreach (self::MILESTONES as $milestone) {
            if ($count < $milestone['threshold']) {
                break;
            }

            $resolved = [
                'award_name' => $milestone['award_name'],
                'recognition' => $milestone['recognition'],
            ];
        }

        return $resolved;
    }
}
