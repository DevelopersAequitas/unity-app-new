<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CoinGuideline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CoinGuidelineSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTitle = 'The Coin Reward System';
        $defaultDescription = 'Coins are rewards for being an active community builder. They reflect your engagement and contributions to the network.';
        $defaultIcon = null;

        $items = [
            ['activity' => 'Testimonial', 'coins' => 5000, 'display_order' => 1],
            ['activity' => 'Referral', 'coins' => 3000, 'display_order' => 2],
            ['activity' => 'Referral signup', 'coins' => 1000, 'display_order' => 3],
            ['activity' => 'Requirement', 'coins' => 3000, 'display_order' => 4],
            ['activity' => 'Business deal', 'coins' => 10000, 'display_order' => 5],
            ['activity' => 'P2P meeting', 'coins' => 3000, 'display_order' => 6],
            ['activity' => 'Recommend peer', 'coins' => 1000, 'display_order' => 7],
        ];

        foreach ($items as $item) {
            CoinGuideline::query()->updateOrCreate(
                ['activity' => $item['activity']],
                [
                    'id' => (string) Str::uuid(),
                    'title' => $defaultTitle,
                    'description' => $defaultDescription,
                    'icon' => $defaultIcon,
                    'coins' => $item['coins'],
                    'display_order' => $item['display_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
