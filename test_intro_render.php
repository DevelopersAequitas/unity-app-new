<?php

use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$cleanSource = 'C:\\Users\\DEll\\.gemini\\antigravity-ide\\brain\\e3327d0e-ee58-4b72-9713-5458adfb8b6f\\media__1785254941578.png';

if (! file_exists($cleanSource)) {
    exit("Source file not found.\n");
}

// Load source
$im = imagecreatefrompng($cleanSource);
$width = imagesx($im);
$height = imagesy($im);

// Define colors
$white = imagecolorallocate($im, 255, 255, 255);
$gold = imagecolorallocate($im, 193, 154, 88); // #C19A58
$lightGray = imagecolorallocate($im, 220, 220, 220);

// Sample names
$referrerName = 'Jayesh Patel';
$newMemberName = 'Drashti Shah';

// Fonts
$fontBold = public_path('fonts/Montserrat-Bold.ttf');
if (! file_exists($fontBold)) {
    $fontBold = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
}

$fontRegular = public_path('fonts/Montserrat-Regular.ttf');
if (! file_exists($fontRegular)) {
    $fontRegular = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
}

// 1. Draw sample photos inside the gold circles
// Bounding coordinates: Left X=275, Right X=545, Y=395, Diameter=250
$circleY = 395;
$avatarSize = 250;
$radius = $avatarSize / 2;

// We will draw dummy initials placeholders for demonstration
function drawInitialCircle($canvas, $centerX, $centerY, $size, $initial, $fontPath, $bgColor, $textColor)
{
    $avatarImg = imagecreatetruecolor($size, $size);
    imagealphablending($avatarImg, false);
    imagesavealpha($avatarImg, true);
    $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
    imagefill($avatarImg, 0, 0, $transparent);

    // Fill with solid gold-like color
    $radius = $size / 2;
    imagefilledellipse($avatarImg, (int) $radius, (int) $radius, $size, $size, $bgColor);

    // Draw initial text centered
    $fontSize = (int) ($size * 0.45);
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $initial);
    $w = abs($bbox[4] - $bbox[0]);
    $h = abs($bbox[5] - $bbox[1]);
    $x = $radius - ($w / 2);
    $y = $radius + ($h / 2) - $bbox[1];

    imagettftext($avatarImg, $fontSize, 0, (int) $x, (int) $y, $textColor, $fontPath, $initial);

    // Draw a nice white border
    imagesetthickness($avatarImg, 4);
    imageellipse($avatarImg, (int) $radius, (int) $radius, $size - 4, $size - 4, imagecolorallocate($avatarImg, 255, 255, 255));

    // Crop circular
    // In our HasCreativeRendering, we have createCircularPhoto logic.
    // Let's copy it on canvas
    $tx = $centerX - ($size / 2);
    $ty = $centerY - ($size / 2);
    imagecopy($canvas, $avatarImg, (int) $tx, (int) $ty, 0, 0, $size, $size);
    imagedestroy($avatarImg);
}

// Draw Referrer (Left) and New Member (Right)
$referrerBgColor = imagecolorallocate($im, 22, 63, 115); // Navy Blue
$newMemberBgColor = imagecolorallocate($im, 197, 48, 48); // Red
$textWhite = imagecolorallocate($im, 255, 255, 255);

drawInitialCircle($im, 228, 396, 252, 'JP', $fontBold, $referrerBgColor, $textWhite);
drawInitialCircle($im, 583, 396, 252, 'DS', $fontBold, $newMemberBgColor, $textWhite);

// 2. Draw names below the circles
// Left name: centered under X = 228, Y = 565
$nameFontSize = 18;
$bboxLeft = imagettfbbox($nameFontSize, 0, $fontBold, $referrerName);
$wLeft = abs($bboxLeft[4] - $bboxLeft[0]);
imagettftext($im, $nameFontSize, 0, (int) (228 - ($wLeft / 2)), 565, $gold, $fontBold, $referrerName);

// Right name: centered under X = 583, Y = 565
$bboxRight = imagettfbbox($nameFontSize, 0, $fontBold, $newMemberName);
$wRight = abs($bboxRight[4] - $bboxRight[0]);
imagettftext($im, $nameFontSize, 0, (int) (583 - ($wRight / 2)), 565, $gold, $fontBold, $newMemberName);

// 3. Draw congratulations text at the bottom (Y starting at 675)
$paragraph = "Congratulations to $referrerName for introducing $newMemberName to the Peers Global Community of Collaboration. Wishing you both a successful journey filled with meaningful connections, collaboration, and endless opportunities.";

// Helper to wrap text
function wrapTextToLines($text, $fontSize, $fontPath, $maxWidth)
{
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';

    foreach ($words as $word) {
        $testLine = $currentLine === '' ? $word : $currentLine.' '.$word;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
        $w = abs($bbox[4] - $bbox[0]);
        if ($w > $maxWidth) {
            $lines[] = $currentLine;
            $currentLine = $word;
        } else {
            $currentLine = $testLine;
        }
    }
    if ($currentLine !== '') {
        $lines[] = $currentLine;
    }

    return $lines;
}

$lines = wrapTextToLines($paragraph, 15, $fontRegular, 680); // Width 680px for a clean margin
$currentY = 675;
$lineHeight = 28;

foreach ($lines as $line) {
    $bbox = imagettfbbox(15, 0, $fontRegular, $line);
    $w = abs($bbox[4] - $bbox[0]);
    $x = ($width / 2) - ($w / 2);
    imagettftext($im, 15, 0, (int) $x, $currentY, $white, $fontRegular, $line);
    $currentY += $lineHeight;
}

// Save output image
imagejpeg($im, public_path('test_intro.jpg'), 95);
echo "Successfully generated preview to public/test_intro.jpg\n";
imagedestroy($im);
