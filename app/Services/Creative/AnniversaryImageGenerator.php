<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\AnniversaryTemplate;
use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Media\FileUploadService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnniversaryImageGenerator
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    public function generate(User $user, ?AnniversaryTemplate $template = null): FileModel
    {
        // Load layout parameters
        $width = config('anniversary.canvas.width', 1080);
        $height = config('anniversary.canvas.height', 1080);

        $centerX = config('anniversary.photo.center_x', 540);
        $centerY = config('anniversary.photo.center_y', 555);
        $avatarSize = config('anniversary.avatar.size', 370);

        $nameY = config('anniversary.name.y', 820);
        $businessY = config('anniversary.business.y', 910);

        $canvas = imagecreatetruecolor($width, $height);

        // Prepare colors
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $nameColorConf = config('anniversary.name.color', [18, 58, 112]);
        $navy = imagecolorallocate($canvas, $nameColorConf[0], $nameColorConf[1], $nameColorConf[2]);
        $redConf = config('anniversary.business.color_red', [197, 48, 48]);
        $redHeading = imagecolorallocate($canvas, $redConf[0], $redConf[1], $redConf[2]);

        // Enable alpha blending on the canvas
        imagealphablending($canvas, true);

        // 1. Load active background template
        $activeTemplate = $template ?: AnniversaryTemplate::where('is_active', true)->first();
        $baseImg = null;

        if ($activeTemplate && $activeTemplate->image_path) {
            $disk = config('filesystems.default', 'public');
            if (Storage::disk($disk)->exists($activeTemplate->image_path)) {
                $contents = Storage::disk($disk)->get($activeTemplate->image_path);
                $baseImg = @imagecreatefromstring($contents);
            }
        }

        if ($baseImg) {
            // Copy uploaded template onto canvas dynamically matching sizing (pixel-perfect)
            imagecopyresampled($canvas, $baseImg, 0, 0, 0, 0, $width, $height, imagesx($baseImg), imagesy($baseImg));
            imagedestroy($baseImg);
        } else {
            // Fallback to clean white canvas for tests/empty states
            imagefill($canvas, 0, 0, $white);
        }

        // 2. Clean the placeholder region using a solid white circle
        // The inner placeholder circle of size 380 is covered so that camera icons or background grey texts
        // are never visible behind the dynamic photo or fallback avatar.
        imagefilledellipse($canvas, $centerX, $centerY, 380, 380, $white);

        // 3. Draw only dynamic elements
        $fontPath = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');

        // User Profile Photo or dynamic Initials
        $photoDrawn = false;
        $photoData = null;
        $profilePhotoId = $user->profile_photo_file_id ?? $user->profile_photo_id ?? null;

        if ($profilePhotoId) {
            $fileRecord = File::find($profilePhotoId);
            if ($fileRecord && $fileRecord->s3_key) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $photoData = Storage::disk($disk)->get($fileRecord->s3_key);
                }
            }
        }

        if (! $photoData && $user->profile_photo_url) {
            if (filter_var($user->profile_photo_url, FILTER_VALIDATE_URL)) {
                $photoData = @file_get_contents($user->profile_photo_url);
            }
        }

        $tx = $centerX - ($avatarSize / 2);
        $ty = $centerY - ($avatarSize / 2);

        if ($photoData) {
            try {
                $userImg = @imagecreatefromstring($photoData);
                if ($userImg) {
                    $circularPhoto = $this->createCircularPhoto($userImg, $avatarSize);
                    if ($circularPhoto) {
                        imagecopy($canvas, $circularPhoto, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($circularPhoto);
                        $photoDrawn = true;
                    }
                    imagedestroy($userImg);
                }
            } catch (Exception $e) {
                Log::warning('AnniversaryImageGenerator: Failed to overlay profile photo: '.$e->getMessage());
            }
        }

        // Initials fallback (Matches Welcome Creative: Solid Navy Circle + Bold White Letter)
        if (! $photoDrawn) {
            $initial = strtoupper(substr($user->first_name ?: ($user->display_name ?: 'P'), 0, 1));

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            // Draw simple solid circular background (no gradient, no shadows, no glossy effects, flat navy #163F73)
            $avatarNavy = imagecolorallocate($avatarImg, 22, 63, 115); // Solid dark blue #163F73
            $avatarRadius = $avatarSize / 2;
            imagefilledellipse($avatarImg, (int) $avatarRadius, (int) $avatarRadius, $avatarSize, $avatarSize, $avatarNavy);

            // Draw white centered letter inside solid avatar
            $fontSizeInit = (int) ($avatarSize * 0.45); // Font size approx 45% of avatar diameter
            if (file_exists($fontPath)) {
                $this->drawCenteredBoldText($avatarImg, $fontSizeInit, $avatarRadius, $avatarRadius, $white, $fontPath, $initial);
            } else {
                imagestring($avatarImg, 5, (int) ($avatarRadius - 10), (int) ($avatarRadius - 10), $initial, $white);
            }

            // Clip solid avatar to a perfect circle (incorporating mask transparency)
            $circularAvatar = $this->createCircularPhoto($avatarImg, $avatarSize);
            imagedestroy($avatarImg);

            // Stamp onto the template placeholder
            imagecopy($canvas, $circularAvatar, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
            imagedestroy($circularAvatar);
        }

        // User Name
        $nameText = strtoupper($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
        $nameMaxWidth = config('anniversary.name.max_width', 900);
        if (file_exists($fontPath)) {
            $fontSize = config('anniversary.name.font_size', 44);
            // Auto-resize font size if name is too long to prevent overflow
            do {
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $nameText);
                $textWidth = abs($bbox[4] - $bbox[0]);
                if ($textWidth > $nameMaxWidth && $fontSize > 12) {
                    $fontSize -= 2;
                } else {
                    break;
                }
            } while (true);

            $this->drawCenteredBoldText($canvas, $fontSize, $centerX, $nameY, $navy, $fontPath, $nameText);
        } else {
            $this->drawCenteredTextFallback($canvas, 5, $nameY - 15, $nameText, $navy);
        }

        // Business Name / Industry Line
        $part1 = $user->company_name;
        $part2 = $user->industry ?: $user->designation;

        if (blank($part1) && blank($part2)) {
            $part1 = 'Global';
            $part2 = 'Peer';
        } elseif (blank($part1)) {
            $part1 = $part2;
            $part2 = 'Peer';
        } elseif (blank($part2)) {
            $part2 = 'Global';
        }

        $businessMaxWidth = config('anniversary.business.max_width', 950);
        if (file_exists($fontPath)) {
            $fontSizeBus = config('anniversary.business.font_size', 26);
            do {
                $bboxSpace = imagettfbbox($fontSizeBus, 0, $fontPath, ' ');
                $spaceWidth = abs($bboxSpace[4] - $bboxSpace[0]);

                $bbox1 = imagettfbbox($fontSizeBus, 0, $fontPath, $part1);
                $w1 = abs($bbox1[4] - $bbox1[0]);

                $bbox2 = imagettfbbox($fontSizeBus, 0, $fontPath, $part2);
                $w2 = abs($bbox2[4] - $bbox2[0]);

                $totalW = $w1 + $spaceWidth + $w2;
                if ($totalW > $businessMaxWidth && $fontSizeBus > 10) {
                    $fontSizeBus -= 1;
                } else {
                    break;
                }
            } while (true);

            $startX = ($width - $totalW) / 2;

            imagettftext($canvas, $fontSizeBus, 0, (int) $startX, (int) $businessY, $navy, $fontPath, $part1);
            imagettftext($canvas, $fontSizeBus, 0, (int) ($startX + $w1 + $spaceWidth), (int) $businessY, $redHeading, $fontPath, $part2);
        } else {
            $combined = $part1.' '.$part2;
            $this->drawCenteredTextFallback($canvas, 4, $businessY - 15, $combined, $navy);
        }

        // 3. Save high-quality WebP & Register via FileUploadService
        $filename = 'anniversary_'.Str::uuid().'.webp';
        $tempPath = tempnam(sys_get_temp_dir(), 'anniv');

        imagewebp($canvas, $tempPath, 95); // Set premium quality to 95 as requested
        imagedestroy($canvas);

        $uploadedFile = new UploadedFile(
            $tempPath,
            $filename,
            'image/webp',
            null,
            true // test mode
        );

        $disk = config('filesystems.default', 'public');
        $fileModel = $this->fileUploadService->store($uploadedFile, null, $disk);

        // Also copy the file to the public disk so it is accessible via direct public URLs
        if ($disk !== 'public') {
            try {
                $fileContent = \Illuminate\Support\Facades\Storage::disk($disk)->get($fileModel->s3_key);
                \Illuminate\Support\Facades\Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                \Illuminate\Support\Facades\Log::info("AnniversaryImageGenerator: Copied creative {$fileModel->s3_key} to public disk.");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('AnniversaryImageGenerator: Failed to copy creative to public disk: '.$e->getMessage());
            }
        }

        @unlink($tempPath);

        return $fileModel;
    }

    private function createCircularPhoto($srcImg, int $targetSize)
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

    private function drawCenteredBoldText($image, float $size, float $centerX, float $centerY, int $color, string $fontFile, string $text)
    {
        $bbox = imagettfbbox($size, 0, $fontFile, $text);
        if ($bbox) {
            $textWidth = abs($bbox[4] - $bbox[0]);
            $textHeight = abs($bbox[5] - $bbox[1]);
            $x = $centerX - ($textWidth / 2);
            // Mathematically correct vertical centering using baseline offset
            $y = $centerY + ($textHeight / 2) - $bbox[1];

            // Bold offsets drawing
            imagettftext($image, $size, 0, (int) $x - 1, (int) $y, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x + 1, (int) $y, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y - 1, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y + 1, $color, $fontFile, $text);
            imagettftext($image, $size, 0, (int) $x, (int) $y, $color, $fontFile, $text);
        } else {
            imagestring($image, 5, (int) ($centerX - 10), (int) ($centerY - 10), $text, $color);
        }
    }

    private function drawCenteredTextFallback($image, int $font, float $y, string $text, int $color)
    {
        $charWidth = imagefontwidth($font);
        $textWidth = strlen($text) * $charWidth;
        $x = (config('anniversary.canvas.width', 1080) - $textWidth) / 2;
        imagestring($image, $font, (int) $x, (int) $y, $text, $color);
    }
}
