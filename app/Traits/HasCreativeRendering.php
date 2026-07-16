<?php

namespace App\Traits;

use App\Models\FileModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Log;

trait HasCreativeRendering
{
    /**
     * Circular Cropping and masking.
     */
    protected function createCircularPhoto($srcImg, int $targetSize)
    {
        $width = imagesx($srcImg);
        $height = imagesy($srcImg);

        // Crop center square to avoid aspect ratio stretching
        $minSize = min($width, $height);
        $srcX = (int) (($width - $minSize) / 2);
        $srcY = (int) (($height - $minSize) / 2);

        $circleImg = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($circleImg, false);
        imagesavealpha($circleImg, true);

        $transparent = imagecolorallocatealpha($circleImg, 0, 0, 0, 127);
        imagefill($circleImg, 0, 0, $transparent);

        $resized = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, $transparent);
        imagecopyresampled($resized, $srcImg, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $minSize, $minSize);

        $r = $targetSize / 2;
        for ($x = 0; $x < $targetSize; $x++) {
            for ($y = 0; $y < $targetSize; $y++) {
                $dx = $x - $r;
                $dy = $y - $r;
                if (($dx * $dx + $dy * $dy) <= ($r * $r)) {
                    $color = imagecolorat($resized, $x, $y);
                    imagesetpixel($circleImg, $x, $y, $color);
                }
            }
        }

        imagedestroy($resized);

        return $circleImg;
    }

    /**
     * Draw wrapped and centered text with automatic font sizing.
     */
    protected function drawWrappedCenteredText($canvas, string $text, int $startFontSize, int $centerX, int $startY, $color, string $fontPath, int $maxWidth, float $lineHeightMultiplier = 1.35): int
    {
        $fontSize = $startFontSize;
        $lines = [];

        do {
            $lines = [];
            $currentLine = '';
            $words = explode(' ', $text);
            $hasOverflowWord = false;

            foreach ($words as $word) {
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $testLine);
                if (! $bbox) {
                    $lines = [$text];
                    break;
                }
                $w = abs($bbox[4] - $bbox[0]);
                if ($w > $maxWidth) {
                    if ($currentLine === '') {
                        $hasOverflowWord = true;
                        break;
                    } else {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    }
                } else {
                    $currentLine = $testLine;
                }
            }
            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }

            $anyLineOverflow = false;
            foreach ($lines as $line) {
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $line);
                if ($bbox) {
                    $w = abs($bbox[4] - $bbox[0]);
                    if ($w > $maxWidth) {
                        $anyLineOverflow = true;
                        break;
                    }
                }
            }

            if (($hasOverflowWord || $anyLineOverflow) && $fontSize > 12) {
                $fontSize -= 2;
            } else {
                break;
            }
        } while (true);

        $currentY = $startY;
        $isBold = (str_contains(basename($fontPath), 'Bold'));

        foreach ($lines as $line) {
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $line);
            if ($bbox) {
                $w = abs($bbox[4] - $bbox[0]);
                $h = abs($bbox[5] - $bbox[1]);
                $x = $centerX - ($w / 2);
                $y = $currentY + $h;

                if ($isBold) {
                    // Draw clean outline for bold text to make it stand out prominently
                    imagettftext($canvas, $fontSize, 0, (int) $x - 1, (int) $y, $color, $fontPath, $line);
                    imagettftext($canvas, $fontSize, 0, (int) $x + 1, (int) $y, $color, $fontPath, $line);
                    imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y - 1, $color, $fontPath, $line);
                    imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y + 1, $color, $fontPath, $line);
                }
                imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y, $color, $fontPath, $line);

                $currentY += (int) ($h * $lineHeightMultiplier);
            } else {
                imagestring($canvas, 5, (int) ($centerX - 10), (int) $currentY, $line, $color);
                $currentY += 20;
            }
        }

        return $currentY;
    }

    /**
     * Draw centered bold text fallback.
     */
    protected function drawCenteredBoldText($image, float $size, float $centerX, float $centerY, int $color, string $fontFile, string $text)
    {
        $bbox = imagettfbbox($size, 0, $fontFile, $text);
        if ($bbox) {
            $textWidth = abs($bbox[4] - $bbox[0]);
            $textHeight = abs($bbox[5] - $bbox[1]);
            $x = $centerX - ($textWidth / 2);
            $y = $centerY + ($textHeight / 2) - $bbox[1];

            imagettftext($image, $size, 0, (int) $x - 1, (int) $y, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x + 1, (int) $y, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y - 1, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y + 1, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y, $color, $fontFile, $text);
        } else {
            imagestring($image, 5, (int) ($centerX - 10), (int) ($centerY - 10), $text, $color);
        }
    }

    /**
     * Get clean professional font path.
     */
    protected function getFontPath(bool $bold = false): string
    {
        $fontName = $bold ? 'Montserrat-Bold.ttf' : 'Montserrat-Regular.ttf';
        $fontPath = public_path('fonts/'.$fontName);
        if (! file_exists($fontPath)) {
            $fontPath = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
        }

        return $fontPath;
    }
}
