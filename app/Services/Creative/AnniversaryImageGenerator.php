<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\AnniversaryTemplate;
use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Media\FileUploadService;
use App\Traits\HasCreativeRendering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnniversaryImageGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    public function generate(User $user, ?AnniversaryTemplate $template = null): FileModel
    {
        try {
            // 1. Load active background template
            $activeTemplate = $template ?: AnniversaryTemplate::where('is_active', true)->first();
            $baseImg = null;
            $isCustomTemplate = false;

            if ($activeTemplate && $activeTemplate->image_path) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($activeTemplate->image_path)) {
                    $contents = Storage::disk($disk)->get($activeTemplate->image_path);
                    $baseImg = @imagecreatefromstring($contents);
                    if ($baseImg) {
                        $isCustomTemplate = true;
                    }
                }
            }

            if ($baseImg) {
                $width = imagesx($baseImg);
                $height = imagesy($baseImg);
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
                imagedestroy($baseImg);
            } else {
                // Fallback to clean white canvas for tests/empty states
                $width = config('anniversary.canvas.width', 1080);
                $height = config('anniversary.canvas.height', 1080);
                $canvas = imagecreatetruecolor($width, $height);
                $whiteColor = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $whiteColor);
            }

            // Compute coordinates and sizes based on template type
            if ($isCustomTemplate) {
                // behavior aligned to Anniversary Template measurements:
                // circle center is at Y = 515, radius = 195 (Avatar size 390)
                $centerX = (int) ($width / 2);
                $centerY = (int) ($height * 0.3815); // Center Y: 515
                $avatarSize = 390; // Circle size: 390 (matches Birthday)
                $nameStartY = 745; // Starts 50px below the bottom of circle
                $nameFontSize = 42; // Reduced to 42px — SemiBold at 42pt = elegant & balanced
                $companyFontSize = 32; // Matches Birthday
                $clearCirclePadding = 12;

                $nameColor = imagecolorallocate($canvas, 255, 255, 255); // White
                $companyColor = imagecolorallocate($canvas, 193, 154, 88); // Gold (#C19A58)
            } else {
                // Static Anniversary layout parameters (original parameters)
                $centerX = config('anniversary.photo.center_x', 540);
                $centerY = config('anniversary.photo.center_y', 555);
                $avatarSize = config('anniversary.avatar.size', 370);
                $nameStartY = config('anniversary.name.y', 820);
                $clearCirclePadding = 10;

                $nameColorConf = config('anniversary.name.color', [18, 58, 112]);
                $nameColor = imagecolorallocate($canvas, $nameColorConf[0], $nameColorConf[1], $nameColorConf[2]);
                $redConf = config('anniversary.business.color_red', [197, 48, 48]);
                $companyColor = imagecolorallocate($canvas, $redConf[0], $redConf[1], $redConf[2]);
            }

            $white = imagecolorallocate($canvas, 255, 255, 255);

            // Wiping out profile photo circular region to ensure no background placeholder shows behind
            // Clear only the inner area of the avatar (diameter = avatarSize - 4) so we don't leak white background under the gold ring
            if ($isCustomTemplate) {
                imagefilledellipse($canvas, $centerX, $centerY, $avatarSize - 4, $avatarSize - 4, $white);
            } else {
                imagefilledellipse($canvas, $centerX, $centerY, $avatarSize + $clearCirclePadding, $avatarSize + $clearCirclePadding, $white);
            }

            // Draw profile photo or initials
            $this->drawAvatarOrInitial($canvas, $user, $centerX, $centerY, $avatarSize, $isCustomTemplate);

            // Draw premium gold ring and glow frame
            if ($isCustomTemplate) {
                $this->drawPremiumGoldFrame($canvas, $centerX, $centerY, $avatarSize, $white, '#C19A58');
            }

            // Draw user name and business details
            $this->drawTextAndDetails($canvas, $user, $centerX, $nameStartY, $nameFontSize ?? 44, $companyFontSize ?? 26, $nameColor, $companyColor, $isCustomTemplate);

            // Save high-quality WebP & Register via FileUploadService
            $filename = 'anniversary_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'anniv');

            imagewebp($canvas, $tempPath, 95); // Premium quality 95
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
                    $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                    Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                    Log::info("AnniversaryImageGenerator: Copied creative {$fileModel->s3_key} to public disk.");
                } catch (\Throwable $e) {
                    Log::error('AnniversaryImageGenerator: Failed to copy creative to public disk: '.$e->getMessage());
                }
            }

            @unlink($tempPath);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error('Failed to generate anniversary creative: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Draw Avatar or Initial.
     */
    private function drawAvatarOrInitial($canvas, User $user, int $centerX, int $centerY, int $avatarSize, bool $isCustomTemplate): void
    {
        $avatarSource = null;
        $tempFilePath = null;
        $profilePhotoId = $user->profile_photo_file_id ?? $user->profile_photo_id ?? null;

        if ($profilePhotoId) {
            $fileRecord = File::find($profilePhotoId);
            if ($fileRecord && $fileRecord->s3_key) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk($disk)->path($fileRecord->s3_key);
                } elseif (Storage::disk('public')->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk('public')->path($fileRecord->s3_key);
                }
            }
        }

        if (! $avatarSource && $user->profile_photo_url) {
            if (filter_var($user->profile_photo_url, FILTER_VALIDATE_URL)) {
                try {
                    $response = Http::timeout(5)->get($user->profile_photo_url);
                    if ($response->successful()) {
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'avatar_');
                        file_put_contents($tempFilePath, $response->body());
                        $avatarSource = $tempFilePath;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not download remote user avatar: '.$e->getMessage());
                }
            }
        }

        $drawnSuccessfully = false;

        if ($avatarSource && file_exists($avatarSource)) {
            try {
                $avatarImg = @imagecreatefrompng($avatarSource);
                if (! $avatarImg) {
                    $avatarImg = @imagecreatefromjpeg($avatarSource);
                }
                if (! $avatarImg) {
                    $avatarData = file_get_contents($avatarSource);
                    $avatarImg = @imagecreatefromstring($avatarData);
                }

                if ($avatarImg) {
                    $circularPhoto = $this->createCircularPhoto($avatarImg, $avatarSize);
                    if ($circularPhoto) {
                        $tx = $centerX - ($avatarSize / 2);
                        $ty = $centerY - ($avatarSize / 2);
                        imagecopy($canvas, $circularPhoto, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($circularPhoto);
                        $drawnSuccessfully = true;
                    }
                    imagedestroy($avatarImg);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not process user avatar for anniversary creative: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        // Fallback to initials
        if (! $drawnSuccessfully) {
            $displayName = $user->display_name ?: $user->first_name ?: 'User';
            $initial = strtoupper(substr($displayName, 0, 1));

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            // Fill with gold based on custom template
            $avatarBgColor = $isCustomTemplate ? imagecolorallocate($avatarImg, 193, 154, 88) : imagecolorallocate($avatarImg, 22, 63, 115);
            $avatarRadius = $avatarSize / 2;
            imagefilledellipse($avatarImg, (int) $avatarRadius, (int) $avatarRadius, $avatarSize, $avatarSize, $avatarBgColor);

            // Draw initial letter
            $fontPath = $this->getFontPath('bold');
            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
            $fontSizeInit = (int) ($avatarSize * 0.42);
            if (file_exists($fontPath)) {
                $this->drawCenteredBoldText($avatarImg, $fontSizeInit, $avatarRadius, $avatarRadius, $whiteColor, $fontPath, $initial);
            } else {
                imagestring($avatarImg, 5, (int) ($avatarRadius - 10), (int) ($avatarRadius - 10), $initial, $whiteColor);
            }

            $circularAvatar = $this->createCircularPhoto($avatarImg, $avatarSize);
            imagedestroy($avatarImg);

            $tx = $centerX - ($avatarSize / 2);
            $ty = $centerY - ($avatarSize / 2);
            imagecopy($canvas, $circularAvatar, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
            imagedestroy($circularAvatar);
        }
    }

    /**
     * Draw user name and business details.
     */
    private function drawTextAndDetails($canvas, User $user, int $centerX, int $nameStartY, int $nameFontSize, int $companyFontSize, $nameColor, $companyColor, bool $isCustomTemplate): void
    {
        // Name uses SemiBold (600 weight) — lighter, more premium than ExtraBold/Bold
        $fontPathName = $this->getFontPath('semibold');
        $fontPathSemiBold = $this->getFontPath('semibold');

        $displayName = strtoupper($user->display_name ?: ($user->first_name.' '.$user->last_name));

        if ($isCustomTemplate) {
            // Apply vertical height safety loop to prevent overlapping with greeting message at Y = 870
            $maxAllowedY = 870;
            $nameSpacing = 18;
            $companySpacing = 18;
            $companyName = $user->company_name ?: ($user->designation ?: 'Global Peer');

            do {
                // Use 820px max-width so long names always have comfortable side margins
                $nameLines = $this->wrapTextToLines($displayName, $nameFontSize, $fontPathName, 820);
                $nameHeight = 0;
                foreach ($nameLines as $line) {
                    $bbox = @imagettfbbox($nameFontSize, 0, $fontPathName, $line);
                    if ($bbox) {
                        $nameHeight += (int) (abs($bbox[5] - $bbox[1]) * 1.35);
                    }
                }

                $companyLines = $this->wrapTextToLines($companyName, $companyFontSize, $fontPathSemiBold, 950);
                $companyHeight = 0;
                foreach ($companyLines as $line) {
                    $bbox = @imagettfbbox($companyFontSize, 0, $fontPathSemiBold, $line);
                    if ($bbox) {
                        $companyHeight += (int) (abs($bbox[5] - $bbox[1]) * 1.35);
                    }
                }

                $totalHeight = $nameHeight + $nameSpacing + $companySpacing + $companyHeight;
                $availableHeight = $maxAllowedY - $nameStartY;

                if ($totalHeight > $availableHeight && $nameFontSize > 34) {
                    $nameFontSize -= 2;
                    $companyFontSize = max(22, $nameFontSize - 24);
                } else {
                    break;
                }
            } while (true);

            // Draw final optimized layouts
            $nextY = $this->drawPreWrappedCenteredText($canvas, $nameLines, $nameFontSize, $centerX, $nameStartY, $nameColor, $fontPathName);

            // Draw separator line
            $separatorY = $nextY + $nameSpacing;
            $this->drawGoldSeparator($canvas, $centerX, $separatorY, $companyColor);

            // Draw company name
            $this->drawPreWrappedCenteredText($canvas, $companyLines, $companyFontSize, $centerX, $separatorY + $companySpacing, $companyColor, $fontPathSemiBold);
        } else {
            // Static Template Typography (original behavior)
            $nextY = $this->drawWrappedCenteredText(
                $canvas,
                $displayName,
                $nameFontSize,
                $centerX,
                $nameStartY,
                $nameColor,
                $this->getFontPath('bold'),
                900
            );

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
            $fontSizeBus = $companyFontSize;

            if (file_exists($this->getFontPath('regular'))) {
                // Calculate starting X to center the combined two-colored text line
                do {
                    $bboxSpace = imagettfbbox($fontSizeBus, 0, $this->getFontPath('regular'), ' ');
                    $spaceWidth = abs($bboxSpace[4] - $bboxSpace[0]);

                    $bbox1 = imagettfbbox($fontSizeBus, 0, $this->getFontPath('regular'), $part1);
                    $w1 = abs($bbox1[4] - $bbox1[0]);

                    $bbox2 = imagettfbbox($fontSizeBus, 0, $this->getFontPath('regular'), $part2);
                    $w2 = abs($bbox2[4] - $bbox2[0]);

                    $totalW = $w1 + $spaceWidth + $w2;
                    if ($totalW > $businessMaxWidth && $fontSizeBus > 10) {
                        $fontSizeBus -= 1;
                    } else {
                        break;
                    }
                } while (true);

                $width = imagesx($canvas);
                $startX = ($width - $totalW) / 2;
                $businessY = config('anniversary.business.y', 910);

                imagettftext($canvas, $fontSizeBus, 0, (int) $startX, (int) $businessY, $nameColor, $this->getFontPath('regular'), $part1);
                imagettftext($canvas, $fontSizeBus, 0, (int) ($startX + $w1 + $spaceWidth), (int) $businessY, $companyColor, $this->getFontPath('regular'), $part2);
            } else {
                $combined = $part1.' '.$part2;
                $charWidth = imagefontwidth(4);
                $textWidth = strlen($combined) * $charWidth;
                $width = imagesx($canvas);
                $startX = ($width - $textWidth) / 2;
                $businessY = config('anniversary.business.y', 910);
                imagestring($canvas, 4, (int) $startX, (int) ($businessY - 15), $combined, $nameColor);
            }
        }
    }
}
