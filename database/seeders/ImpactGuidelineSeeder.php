<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ImpactGuideline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImpactGuidelineSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTitle = 'Your Life Impact Score';
        $defaultDescription = 'Study this. Know it. Start counting from today. Every action below earns you impact — tracked in the Unity App.';
        $defaultIcon = null;

        $items = [
            [
                'action' => 'Closed a business deal through Peers Global',
                'category' => 'Business & Growth',
                'impact_value' => 5,
                'impact_unit' => 'Lives',
                'display_order' => 1,
            ],
            [
                'action' => 'Gave a qualified business referral',
                'category' => 'Business & Growth',
                'impact_value' => 1,
                'impact_unit' => 'Life',
                'display_order' => 2,
            ],
            [
                'action' => 'Created a collaboration opportunity',
                'category' => 'Business & Growth',
                'impact_value' => 1,
                'impact_unit' => 'Life',
                'display_order' => 3,
            ],
            [
                'action' => 'Connected two members for collaboration',
                'category' => 'Business & Growth',
                'impact_value' => 1,
                'impact_unit' => 'Life',
                'display_order' => 4,
            ],
            [
                'action' => 'Received a testimonial or review from a peer',
                'category' => 'Trust & Visibility',
                'impact_value' => 5,
                'impact_unit' => 'Lives',
                'display_order' => 5,
            ],
        ];

        foreach ($items as $item) {
            ImpactGuideline::query()->updateOrCreate(
                ['action' => $item['action']],
                [
                    'id' => (string) Str::uuid(),
                    'title' => $defaultTitle,
                    'description' => $defaultDescription,
                    'icon' => $defaultIcon,
                    'category' => $item['category'],
                    'impact_value' => $item['impact_value'],
                    'impact_unit' => $item['impact_unit'],
                    'display_order' => $item['display_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
