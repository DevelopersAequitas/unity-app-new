<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebMediaController extends Controller
{
    public function index(Request $request): View
    {
        $mediaAssets = [
            ['id' => '1', 'title' => 'Peers Global Grand Launch & Conclave Reel', 'file_name' => 'hero-background.mp4', 'type' => 'video', 'size' => '28.4 MB', 'url' => '/videos/hero-background.mp4', 'updated_at' => '2026-09-15'],
            ['id' => '2', 'title' => 'Peers Cyber Earth Network Loop', 'file_name' => 'global-earth-hd.mp4', 'type' => 'video', 'size' => '42.1 MB', 'url' => '/videos/global-earth-hd.mp4', 'updated_at' => '2026-09-14'],
            ['id' => '3', 'title' => 'Leadership Journey Background', 'file_name' => 'journey-bg.mp4', 'type' => 'video', 'size' => '19.8 MB', 'url' => '/videos/journey-bg.mp4', 'updated_at' => '2026-09-10'],
            ['id' => '4', 'title' => 'Dr. Pravin Parmar Cutout Banner', 'file_name' => 'dr-pravin-cutout.png', 'type' => 'image', 'size' => '1.2 MB', 'url' => '/images/dr-pravin-cutout.png', 'updated_at' => '2026-09-08'],
            ['id' => '5', 'title' => 'Peers Global Official Logo Full', 'file_name' => 'logo-full.png', 'type' => 'image', 'size' => '240 KB', 'url' => '/images/logo-full.png', 'updated_at' => '2026-09-01'],
            ['id' => '6', 'title' => 'Ahmedabad Riverfront Banner', 'file_name' => 'ahmedabad-riverfront.png', 'type' => 'image', 'size' => '2.8 MB', 'url' => '/images/territory/ahmedabad-riverfront.png', 'updated_at' => '2026-08-25'],
        ];

        return view('admin.web.media.index', [
            'mediaAssets' => $mediaAssets,
        ]);
    }
}
