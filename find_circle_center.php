<?php
$cleanSource = "C:\\Users\\DEll\\.gemini\\antigravity-ide\\brain\\e3327d0e-ee58-4b72-9713-5458adfb8b6f\\media__1785220893759.jpg";

if (!file_exists($cleanSource)) {
    die("Clean source not found.\n");
}

$im = imagecreatefromjpeg($cleanSource);
$width = imagesx($im);
$height = imagesy($im);

// Find the bounding box of the colored ring
// The background is white (RGB 255,255,255), so we look for non-white pixels
// inside a crop area where the circle is likely to be (e.g. Y between 300 and 700, X between 300 and 800)
$minX = $width;
$maxX = 0;
$minY = $height;
$maxY = 0;

for ($y = 310; $y < 710; $y++) {
    for ($x = 330; $x < 750; $x++) {
        $rgb = imagecolorat($im, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        // If the pixel is not white (distance from 255,255,255 is significant)
        // AND it's part of the red or blue ring
        if ($r < 240 || $g < 240 || $b < 240) {
            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

$centerX = ($minX + $maxX) / 2;
$centerY = ($minY + $maxY) / 2;
$diameterX = $maxX - $minX;
$diameterY = $maxY - $minY;

echo "Bounding Box: Left=$minX, Right=$maxX, Top=$minY, Bottom=$maxY\n";
echo "Calculated Center: X=$centerX, Y=$centerY\n";
echo "Diameter: Width=$diameterX, Height=$diameterY\n";
imagedestroy($im);
