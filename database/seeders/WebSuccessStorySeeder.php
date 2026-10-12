<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Web\WebSuccessStory;
use Illuminate\Database\Seeder;

class WebSuccessStorySeeder extends Seeder
{
    public function run(): void
    {
        if (WebSuccessStory::count() > 0) {
            return;
        }

        $stories = [
            [
                'person_name' => 'Sarah Jenkins',
                'designation' => 'Founder & Managing Director',
                'company' => 'Nexus Healthcare AI',
                'story_title' => 'Scaling Cross-Border Diagnostics by 400%',
                'quote' => 'Connecting with enterprise peers transformed how we secure tier-1 hospital contracts across Southeast Asia.',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'youtube_video_id' => 'dQw4w9WgXcQ',
                'youtube_thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'person_name' => 'Vikram Singhania',
                'designation' => 'Chief Executive Officer',
                'company' => 'Apex Global Logistics',
                'story_title' => '₹4.2 Cr Cross-Border Supply Corridor JV',
                'quote' => 'Through the Peers Global Conclave, we finalized automated manifest clearance with Zen Cloud in under 6 weeks.',
                'youtube_url' => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                'youtube_video_id' => 'ScMzIvxBSi4',
                'youtube_thumbnail_url' => 'https://img.youtube.com/vi/ScMzIvxBSi4/maxresdefault.jpg',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'person_name' => 'Elena Rostova',
                'designation' => 'VP of Strategic Partnerships',
                'company' => 'Horizon Polymers & CleanTech',
                'story_title' => '28 MW Rooftop Solar JV Execution',
                'quote' => 'The synergy between our industrial plants and renewable tech peers unlocked massive capital subsidies and fast EPC syndication.',
                'youtube_url' => 'https://www.youtube.com/watch?v=L_LUpnjgPso',
                'youtube_video_id' => 'L_LUpnjgPso',
                'youtube_thumbnail_url' => 'https://img.youtube.com/vi/L_LUpnjgPso/maxresdefault.jpg',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'person_name' => 'Arjun Mehta',
                'designation' => 'Co-Founder & CTO',
                'company' => 'Zen Cloud Solutions',
                'story_title' => 'AI Dispatch Infrastructure Expansion',
                'quote' => 'Peer referrals provided us with qualified enterprise pilots that would have otherwise taken over a year of cold enterprise sales.',
                'youtube_url' => 'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
                'youtube_video_id' => 'kJQP7kiw5Fk',
                'youtube_thumbnail_url' => 'https://img.youtube.com/vi/kJQP7kiw5Fk/maxresdefault.jpg',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($stories as $storyData) {
            WebSuccessStory::create($storyData);
        }
    }
}
