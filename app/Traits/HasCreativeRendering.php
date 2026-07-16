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
     * Wrap text into lines of a maximum width.
     */
    protected function wrapTextToLines(string $text, int $fontSize, string $fontPath, int $maxWidth): array
    {
        $lines = [];
        $currentLine = '';
        $words = explode(' ', $text);

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $testLine);
            if (! $bbox) {
                return [$text];
            }
            $w = abs($bbox[4] - $bbox[0]);
            if ($w > $maxWidth) {
                if ($currentLine === '') {
                    $lines[] = $word;
                    $currentLine = '';
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

        return $lines;
    }

    /**
     * Draw pre-wrapped centered text lines and return the bottom Y coordinate.
     */
    protected function drawPreWrappedCenteredText($canvas, array $lines, int $fontSize, int $centerX, int $startY, $color, string $fontPath, float $lineHeightMultiplier = 1.35): int
    {
        $currentY = $startY;
        $isBold = (str_contains(basename($fontPath), 'Bold') || str_contains(basename($fontPath), 'SemiBold'));

        foreach ($lines as $line) {
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $line);
            if ($bbox) {
                $w = abs($bbox[4] - $bbox[0]);
                $h = abs($bbox[5] - $bbox[1]);
                $x = $centerX - ($w / 2);
                $y = $currentY + $h;

                if ($isBold) {
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
     * Draw wrapped and centered text with automatic font sizing.
     */
    protected function drawWrappedCenteredText($canvas, string $text, int $startFontSize, int $centerX, int $startY, $color, string $fontPath, int $maxWidth, float $lineHeightMultiplier = 1.35): int
    {
        $style = (str_contains(basename($fontPath), 'Bold')) ? 'bold' : 'regular';
        $fontPathResolved = $this->getFontPath($style);
        $lines = $this->wrapTextToLines($text, $startFontSize, $fontPathResolved, $maxWidth);
        return $this->drawPreWrappedCenteredText($canvas, $lines, $startFontSize, $centerX, $startY, $color, $fontPathResolved, $lineHeightMultiplier);
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
     * Draw decorative gold separator line and ornament.
     */
    protected function drawGoldSeparator($canvas, int $centerX, int $y, $color): void
    {
        // 1. Draw diamond in the center
        $points = [
            $centerX, $y - 6,       // Top
            $centerX + 6, $y,       // Right
            $centerX, $y + 6,       // Bottom
            $centerX - 6, $y        // Left
        ];
        imagefilledpolygon($canvas, $points, 4, $color);

        // 2. Draw small decorative dots on the left and right of the diamond
        imagefilledellipse($canvas, $centerX - 14, $y, 3, 3, $color);
        imagefilledellipse($canvas, $centerX + 14, $y, 3, 3, $color);

        // 3. Draw horizontal lines extending outward
        imagesetthickness($canvas, 2);
        imageline($canvas, $centerX - 120, $y, $centerX - 24, $y, $color);
        imageline($canvas, $centerX + 24, $y, $centerX + 120, $y, $color);
        imagesetthickness($canvas, 1); // restore
    }

    /**
     * Draw a premium circular frame and gold outer glow around the avatar.
     */
    protected function drawPremiumGoldFrame($canvas, int $centerX, int $centerY, int $avatarSize, $whiteColor, string $goldColorHex): void
    {
        $goldRgb = $this->hexToRgb($goldColorHex);
        $goldColor = imagecolorallocate($canvas, $goldRgb['r'], $goldRgb['g'], $goldRgb['b']);

        // 1. Draw subtle outer gold glow (draw semi-transparent concentric rings)
        for ($offset = 10; $offset <= 18; $offset += 2) {
            $diameter = $avatarSize + $offset;
            $alpha = min(127, max(0, (int) (127 * (0.85 + ($offset - 10) * 0.02))));
            $glowColor = imagecolorallocatealpha($canvas, $goldRgb['r'], $goldRgb['g'], $goldRgb['b'], $alpha);
            imagesetthickness($canvas, 2);
            imageellipse($canvas, $centerX, $centerY, $diameter, $diameter, $glowColor);
        }

        // 2. Draw soft gold ring
        imagesetthickness($canvas, 3);
        imageellipse($canvas, $centerX, $centerY, $avatarSize + 6, $avatarSize + 6, $goldColor);

        // 3. Draw thin white inner border
        imagesetthickness($canvas, 2);
        imageellipse($canvas, $centerX, $centerY, $avatarSize, $avatarSize, $whiteColor);

        imagesetthickness($canvas, 1); // restore
    }

    /**
     * Parse hex color string to RGB components.
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    /**
     * Get clean professional font path based on weight style.
     */
    protected function getFontPath(string $style = 'regular'): string
    {
        $filename = match ($style) {
            'extrabold' => 'Montserrat-ExtraBold.ttf',
            'semibold' => 'Montserrat-SemiBold.ttf',
            'bold' => 'Montserrat-Bold.ttf',
            default => 'Montserrat-Regular.ttf',
        };

        $fontPath = public_path('fonts/'.$filename);
        if (! file_exists($fontPath)) {
            $fontPath = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
        }

        return $fontPath;
    }
}
