<?php

namespace Database\Seeders;

use App\Models\AppChangelog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AppChangelogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppChangelog::truncate();

        AppChangelog::create([
            'id' => (string) Str::uuid(),
            'version' => '1.0.5',
            'platform' => 'android',
            'title' => 'Performance Boost & Versioning',
            'description' => 'This update optimizes device resources and introduces app versioning tracking.',
            'features' => [
                'Optimized push notifications delivery speed',
                'Added automated app version compatibility warning checks',
                'Enhanced security controls on user profile data'
            ],
            'is_released' => true,
            'released_at' => now(),
        ]);

        AppChangelog::create([
            'id' => (string) Str::uuid(),
            'version' => '1.0.4',
            'platform' => 'ios',
            'title' => 'iOS UI Improvements & Fixes',
            'description' => 'A stability release focused on layout refinements for newer iOS models.',
            'features' => [
                'Fixed notch layout overlaps on iPhone 15/16',
                'Increased contact visibility status update responsiveness',
                'Reduced background battery consumption during live chat'
            ],
            'is_released' => true,
            'released_at' => now()->subDays(5),
        ]);

        AppChangelog::create([
            'id' => (string) Str::uuid(),
            'version' => '1.0.3',
            'platform' => 'all',
            'title' => 'Collaboration Hub & Milestones',
            'description' => 'Introducing brand new collaboration features and automated coin milestone achievements!',
            'features' => [
                'Peer Introduction creative cards generated automatically on timeline',
                'Milestone tracker showing earned medals for your network contributions',
                'Brand Partner offers expiry reminder notifications'
            ],
            'is_released' => true,
            'released_at' => now()->subDays(12),
        ]);
    }
}
