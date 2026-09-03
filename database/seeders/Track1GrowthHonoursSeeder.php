<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MilestoneBadge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Track1GrowthHonoursSeeder extends Seeder
{
    /**
     * Run the database seeds for Track 1 — Growth Honours.
     */
    public function run(): void
    {
        $honours = [
            [
                'title' => 'Connector',
                'required_count' => 1,
                'description' => "You opened the first door. Someone's business is different because you made one introduction.",
                'sort_order' => 1,
            ],
            [
                'title' => 'Catalyst',
                'required_count' => 3,
                'description' => 'You are no longer an accident. Three is a pattern — you have started a reaction.',
                'sort_order' => 2,
            ],
            [
                'title' => 'Influencer',
                'required_count' => 5,
                'description' => 'People act on your word. When you recommend, others move.',
                'sort_order' => 3,
            ],
            [
                'title' => 'Ambassador',
                'required_count' => 10,
                'description' => 'You now represent Peers Global wherever you go, whether or not you are asked to.',
                'sort_order' => 4,
            ],
            [
                'title' => 'Rainmaker',
                'required_count' => 20,
                'description' => 'Growth follows you into the room. You do not wait for opportunity - you create it for others.',
                'sort_order' => 5,
            ],
            [
                'title' => 'Trailblazer',
                'required_count' => 35,
                'description' => 'You go first. Others follow the path you have already walked.',
                'sort_order' => 6,
            ],
            [
                'title' => 'Vanguard',
                'required_count' => 50,
                'description' => 'You lead from the front. Fifty founders are inside this community because of you.',
                'sort_order' => 7,
            ],
            [
                'title' => 'Luminary',
                'required_count' => 75,
                'description' => 'Others navigate by you. Your name has become a reference point in your city.',
                'sort_order' => 8,
            ],
            [
                'title' => 'Movement Maker',
                'required_count' => 100,
                'description' => 'You have crossed from network to movement. One hundred lives changed direction on your word.',
                'sort_order' => 9,
            ],
            [
                'title' => 'Community Titan',
                'required_count' => 150,
                'description' => 'The community stands on people like you. Remove you, and something visible falls.',
                'sort_order' => 10,
            ],
            [
                'title' => 'Network Architect',
                'required_count' => 250,
                'description' => 'You did not join this network. You designed a part of it that will outlast you.',
                'sort_order' => 11,
            ],
            [
                'title' => 'Global Icon',
                'required_count' => 500,
                'description' => 'Your name introduces Peers Global before you do.',
                'sort_order' => 12,
            ],
        ];

        foreach ($honours as $honour) {
            $slug = Str::slug($honour['title']);
            $targetFilename = $slug.'.png';
            $targetDiskPath = 'milestone-badges/'.$targetFilename;
            $sourcePath = public_path('images/member_introduce_badges/'.$honour['title'].'.png');

            if (file_exists($sourcePath)) {
                Storage::disk('public')->put(
                    $targetDiskPath,
                    (string) file_get_contents($sourcePath)
                );
            }

            $encodedTitle = str_replace(' ', '%20', $honour['title']);
            $badgeImageUrl = 'https://peersunity.com/images/member_introduce_badges/'.$encodedTitle.'.png';

            MilestoneBadge::query()->updateOrCreate(
                [
                    'type' => MilestoneBadge::TYPE_MEMBER_INTRODUCTION,
                    'title' => $honour['title'],
                ],
                [
                    'description' => $honour['description'],
                    'required_count' => $honour['required_count'],
                    'badge_image_url' => $badgeImageUrl,
                    'sort_order' => $honour['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
