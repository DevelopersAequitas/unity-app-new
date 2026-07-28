<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\AnniversaryTemplate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

$template = AnniversaryTemplate::where('is_active', true)->first();
if (! $template) {
    exit("No active template found.\n");
}

$disk = config('filesystems.default', 'public');
if (! Storage::disk($disk)->exists($template->image_path)) {
    exit("File does not exist in storage.\n");
}

$realPath = Storage::disk($disk)->path($template->image_path);
$cleanSource = 'C:\\Users\\DEll\\.gemini\\antigravity-ide\\brain\\e3327d0e-ee58-4b72-9713-5458adfb8b6f\\media__1785220893759.jpg';

if (! file_exists($cleanSource)) {
    exit("Clean source file not found.\n");
}

// Load clean source image
$im = imagecreatefromjpeg($cleanSource);
if (! $im) {
    exit("Failed to load clean source image.\n");
}

$white = imagecolorallocate($im, 255, 255, 255);

// Draw a narrower white rectangle over "USER NAME" and "BUSINESS NAME / INDUSTRY"
// Coordinates: X: 280 to 800 (narrower so it doesn't touch the rising curve on the sides), Y: 720 to 865
imagefilledrectangle($im, 280, 720, 800, 865, $white);

// Overwrite the template in storage
if (imagejpeg($im, $realPath, 100)) {
    echo "Successfully cleaned template with narrow coordinates!\n";
} else {
    echo "Failed to save cleaned template.\n";
}

// Copy to public for viewing
copy($realPath, public_path('test_template.jpg'));
echo "Copied to public/test_template.jpg\n";

imagedestroy($im);
