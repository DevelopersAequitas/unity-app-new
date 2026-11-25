<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\CircleCategoryLevel4;
use App\Models\City;
use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WelcomeCreativeImageGenerator
{
    use HasCreativeRendering;

    /**
     * Generate or return existing welcome creative file model.
     */
    public function generate(User $user, ?FileModel $targetFileRecord = null): FileModel
    {
        $templatePath = public_path('images/welcome-template.png');
        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Welcome creative template not found at {$templatePath}");
        }

        $templateContent = file_get_contents($templatePath);
        $canvas = @imagecreatefromstring((string) $templateContent);
        if (! $canvas) {
            $canvas = @imagecreatefrompng($templatePath) ?: @imagecreatefromjpeg($templatePath);
        }
        if (! $canvas) {
            throw new \RuntimeException('Failed to load welcome template image.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($canvas);
        }

        $width = imagesx($canvas);
        $height = imagesy($canvas);
        imagealphablending($canvas, true);

        // Fonts
        $fontBold = $this->getFontPath('bold');
        $fontSemiBold = $this->getFontPath('semibold');
        $fontRegular = $this->getFontPath('regular');

        // Target Colors
        // 1. Navy Blue for Member Name (#002F6C)
        $colorNavy = imagecolorallocate($canvas, 0, 47, 108);
        // 2. Crimson / Red Accent for Category / Divider (#B22222 / #AE271B)
        $colorCrimson = imagecolorallocate($canvas, 178, 34, 34);
        // 3. Slate / Medium Gray for City (#616161)
        $colorGray = imagecolorallocate($canvas, 97, 97, 97);
        // Fallback Avatar Background
        $darkCircleBg = imagecolorallocate($canvas, 0, 47, 108);

        // Center coordinates & circle avatar
        $centerX = (int) ($width / 2); // 410 on 819w template
        $circleCenterY = (int) round($height * 0.493); // ~505px on 1024h template
        $targetDiameter = (int) round($width * 0.44); // ~360px on 819w template

        $this->drawAvatarOrInitial($canvas, $user, $centerX, $circleCenterY, $targetDiameter, $darkCircleBg, $fontBold);

        // 1. Peer Name (Uppercase, Dark Navy, Bold)
        $name = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($name === '') {
            $name = 'PEER MEMBER';
        }
        $name = strtoupper($name);

        // 2. Peer City (Uppercase, Gray, SemiBold)
        $cityModel = null;
        try {
            $cityModel = $user->relationLoaded('city') ? $user->getRelation('city') : ($user->cityRelation ?? null);
            if (! $cityModel && ! empty($user->city_id) && Schema::hasTable('cities')) {
                $cityModel = City::find($user->city_id);
            }
        } catch (\Throwable) {
            $cityModel = null;
        }
        $cityName = $cityModel?->name ?? $user->city_name ?? $user->city ?? $user->business_city ?? '';
        if (is_array($cityName)) {
            $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
        }
        if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
            $decoded = json_decode(trim($cityName), true);
            $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
        }
        $cityName = trim((string) $cityName);
        if (in_array(strtolower($cityName), ['null', 'none', 'n/a'], true)) {
            $cityName = '';
        }
        $cityText = ! empty($cityName) ? strtoupper($cityName) : 'PEERS GLOBAL';

        // 3. Peer Category (Uppercase, Vibrant Navy / Accent, Bold)
        $categoryName = '';
        try {
            if ($user->relationLoaded('level4Category') && $user->level4Category) {
                $categoryName = $user->level4Category->name ?? '';
            } elseif (! empty($user->business_category_id)) {
                $categoryName = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
            } elseif (! empty($user->category_id)) {
                $categoryName = CircleCategoryLevel4::find($user->category_id)?->name ?? '';
            }

            if (empty($categoryName) && $user->relationLoaded('mainBusinessCategory') && $user->mainBusinessCategory) {
                $categoryName = $user->mainBusinessCategory->name ?? '';
            }

            if (empty($categoryName) && isset($user->businessCategory)) {
                $categoryName = $user->businessCategory->name ?? '';
            }
        } catch (\Throwable) {
            // Safely fallback to string attributes if category table lookup fails
        }

        if (empty($categoryName)) {
            $categoryName = $user->category_name ?? $user->business_sub_category ?? $user->industry ?? $user->business_category ?? '';
        }

        if (is_array($categoryName)) {
            $categoryName = $categoryName['name'] ?? $categoryName['label'] ?? '';
        }
        $categoryName = trim((string) $categoryName);
        if (in_array(strtolower($categoryName), ['null', 'none', 'n/a', 'peers global member', 'peer'], true)) {
            $categoryName = '';
        }

        $designation = trim((string) ($user->designation ?? $user->job_title ?? ''));
        $categoryText = ! empty($categoryName) ? $categoryName : $designation;
        if (empty($categoryText)) {
            $categoryText = 'COMMUNITY MEMBER';
        }
        $categoryText = strtoupper($categoryText);

        // Helper to draw center-aligned text with auto-scaling to fit within maxWidth
        $drawCenterText = function ($img, int $fontSize, int $baselineY, int $color, string $font, string $text, int $maxWidth = 720) use ($width): void {
            if (empty($text)) {
                return;
            }
            $size = $fontSize;
            $bbox = @imagettfbbox($size, 0, $font, $text);
            while ($bbox && abs($bbox[4] - $bbox[0]) > $maxWidth && $size > 10) {
                $size -= 1;
                $bbox = @imagettfbbox($size, 0, $font, $text);
            }
            if ($bbox) {
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = (int) (($width - $textWidth) / 2);
                imagettftext($img, $size, 0, $x, $baselineY, $color, $font, $text);
            }
        };

        // Render Only: Peer Name, Peer Category, and Peer City
        // Scale baseline coordinates proportionally to canvas height (1024 standard)
        $scaleY = $height / 1024.0;
        $scaleX = $width / 819.0;

        // Line 1: Peer Name (Navy #002F6C, Bold, ~25pt, Baseline Y ~ 762)
        $nameBaselineY = (int) round(762 * $scaleY);
        $nameFontSize = (int) round(25 * min($scaleX, $scaleY));
        $drawCenterText($canvas, $nameFontSize, $nameBaselineY, $colorNavy, $fontBold, $name, (int) round(680 * $scaleX));

        // Line 2: Peer City (Gray #616161, SemiBold, ~15pt, Baseline Y ~ 798)
        $cityBaselineY = (int) round(798 * $scaleY);
        $cityFontSize = (int) round(15 * min($scaleX, $scaleY));
        $drawCenterText($canvas, $cityFontSize, $cityBaselineY, $colorGray, $fontSemiBold, $cityText, (int) round(680 * $scaleX));

        // Divider: Gradient line with central crimson dot (Y ~ 818)
        $dividerY = (int) round(818 * $scaleY);
        $dividerHalfWidth = (int) round(135 * $scaleX);
        $this->drawGradientDivider($canvas, $centerX, $dividerY, $dividerHalfWidth, $colorNavy, $colorCrimson);

        // Line 3: Peer Category (Navy Blue #002F6C, Bold, ~14pt, Baseline Y ~ 848)
        $categoryBaselineY = (int) round(848 * $scaleY);
        $categoryFontSize = (int) round(14 * min($scaleX, $scaleY));
        $drawCenterText($canvas, $categoryFontSize, $categoryBaselineY, $colorNavy, $fontBold, $categoryText, (int) round(680 * $scaleX));

        // Save canvas to disk
        $tempPath = tempnam(sys_get_temp_dir(), 'welcome_');
        if ($tempPath === false) {
            $tempPath = storage_path('framework/cache/'.(string) Str::uuid().'.tmp');
        }

        imagepng($canvas, $tempPath, 9);
        imagedestroy($canvas);

        $disk = config('filesystems.default', 'public');
        $finalPath = 'creatives/welcome/welcome_'.Str::uuid().'.png';

        if ($targetFileRecord && $targetFileRecord->s3_key) {
            $finalPath = preg_replace('/\.(webp|jpg|jpeg)$/i', '.png', $targetFileRecord->s3_key);
            $fileModel = $targetFileRecord;
            $fileModel->s3_key = $finalPath;
        } else {
            $fileModel = new FileModel;
            $fileModel->id = (string) Str::uuid();
            $fileModel->uploader_user_id = $user->id;
            $fileModel->s3_key = $finalPath;
        }

        $stream = fopen($tempPath, 'r');
        $stored = Storage::disk($disk)->put($finalPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $stored) {
            @unlink($tempPath);
            throw new \RuntimeException("Failed to store welcome creative image for user {$user->id} to disk {$disk}");
        }

        $fileModel->mime_type = 'image/png';
        $fileModel->size_bytes = filesize($tempPath);
        $fileModel->width = $width;
        $fileModel->height = $height;
        if (Schema::hasTable('files')) {
            $fileModel->save();
        }

        if ($disk !== 'public') {
            try {
                $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                Storage::disk('public')->put($fileModel->s3_key, $fileContent);
            } catch (\Throwable $e) {
                Log::warning('WelcomeCreativeImageGenerator: Failed copying creative to public disk: '.$e->getMessage());
            }
        }

        @unlink($tempPath);

        return $fileModel;
    }

    /**
     * Draw left and right gradient divider lines with a center crimson dot.
     */
    private function drawGradientDivider($canvas, float $centerX, float $y, float $lineWidth, $colorNavy, $colorCrimson): void
    {
        $leftStart = $centerX - 6 - $lineWidth;
        $rightStart = $centerX + 6;

        for ($i = 0; $i < $lineWidth; $i++) {
            $ratio = $i / $lineWidth;
            $alpha = (int) (127 * (1.0 - $ratio));
            $color = imagecolorallocatealpha($canvas, 0, 47, 108, $alpha);
            imagesetpixel($canvas, (int) ($leftStart + $i), (int) $y, $color);
            imagesetpixel($canvas, (int) ($leftStart + $i), (int) ($y + 1), $color);
        }

        imagefilledellipse($canvas, (int) $centerX, (int) $y, 6, 6, $colorCrimson);

        for ($i = 0; $i < $lineWidth; $i++) {
            $ratio = $i / $lineWidth;
            $alpha = (int) (127 * $ratio);
            $color = imagecolorallocatealpha($canvas, 178, 34, 34, $alpha);
            imagesetpixel($canvas, (int) ($rightStart + $i), (int) $y, $color);
            imagesetpixel($canvas, (int) ($rightStart + $i), (int) ($y + 1), $color);
        }
    }

    /**
     * Draw user avatar or initials inside circle.
     */
    private function drawAvatarOrInitial($canvas, User $user, int $centerX, int $centerY, int $avatarSize, int $fallbackBgColor, string $fontBold): void
    {
        $avatarSource = null;
        $tempFilePath = null;
        $profilePhotoId = $user->profile_photo_file_id ?? $user->profile_photo_id ?? $user->avatar_file_id ?? null;

        if ($profilePhotoId) {
            $fileRecord = FileModel::find($profilePhotoId) ?? File::find($profilePhotoId);
            if ($fileRecord && $fileRecord->s3_key) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk($disk)->path($fileRecord->s3_key);
                } elseif (Storage::disk('public')->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk('public')->path($fileRecord->s3_key);
                }
            }
        }

        if (! $avatarSource && ! empty($user->profile_photo_path)) {
            if (Storage::disk('public')->exists($user->profile_photo_path)) {
                $avatarSource = Storage::disk('public')->path($user->profile_photo_path);
            }
        }

        if (! $avatarSource && ! empty($user->avatar)) {
            if (Storage::disk('public')->exists($user->avatar)) {
                $avatarSource = Storage::disk('public')->path($user->avatar);
            }
        }

        if (! $avatarSource && ! empty($user->profile_photo_url)) {
            $photoUrl = (string) $user->profile_photo_url;
            if (Storage::disk('public')->exists($photoUrl)) {
                $avatarSource = Storage::disk('public')->path($photoUrl);
            } elseif (str_starts_with($photoUrl, 'storage/')) {
                $relativePath = substr($photoUrl, 8);
                if (Storage::disk('public')->exists($relativePath)) {
                    $avatarSource = Storage::disk('public')->path($relativePath);
                }
            }

            if (! $avatarSource) {
                if (str_starts_with($photoUrl, '/')) {
                    $photoUrl = url($photoUrl);
                }
                if (filter_var($photoUrl, FILTER_VALIDATE_URL)) {
                    try {
                        $response = Http::withoutVerifying()->timeout(5)->get($photoUrl);
                        if ($response->successful()) {
                            $tempFilePath = tempnam(sys_get_temp_dir(), 'avatar_wc_');
                            file_put_contents($tempFilePath, $response->body());
                            $avatarSource = $tempFilePath;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('WelcomeCreativeImageGenerator: Could not download remote avatar: '.$e->getMessage());
                    }
                }
            }
        }

        $drawn = false;

        if ($avatarSource && file_exists($avatarSource)) {
            try {
                $avatarImg = @imagecreatefrompng($avatarSource);
                if (! $avatarImg) {
                    $avatarImg = @imagecreatefromjpeg($avatarSource);
                }
                if (! $avatarImg) {
                    $avatarImg = @imagecreatefromwebp($avatarSource);
                }
                if (! $avatarImg) {
                    $avatarData = file_get_contents($avatarSource);
                    $avatarImg = @imagecreatefromstring((string) $avatarData);
                }

                if ($avatarImg) {
                    $origW = imagesx($avatarImg);
                    $origH = imagesy($avatarImg);
                    if ($origW > 900 || $origH > 900) {
                        $maxDim = 900;
                        if ($origW >= $origH) {
                            $newW = $maxDim;
                            $newH = (int) ($origH * ($maxDim / $origW));
                        } else {
                            $newH = $maxDim;
                            $newW = (int) ($origW * ($maxDim / $origH));
                        }
                        $downscaled = imagecreatetruecolor($newW, $newH);
                        imagealphablending($downscaled, false);
                        imagesavealpha($downscaled, true);
                        imagecopyresampled($downscaled, $avatarImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                        imagedestroy($avatarImg);
                        $avatarImg = $downscaled;
                    }

                    $circularPhoto = $this->createCircularPhoto($avatarImg, $avatarSize);
                    if ($circularPhoto) {
                        $tx = (int) ($centerX - ($avatarSize / 2));
                        $ty = (int) ($centerY - ($avatarSize / 2));
                        imagecopy($canvas, $circularPhoto, $tx, $ty, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($circularPhoto);
                        $drawn = true;
                    }
                    imagedestroy($avatarImg);
                }
            } catch (\Throwable $e) {
                Log::warning('WelcomeCreativeImageGenerator: Error processing avatar: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        if (! $drawn) {
            // Draw initials fallback inside circular avatar
            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            $radius = (int) ($avatarSize / 2);
            imagefilledellipse($avatarImg, $radius, $radius, $avatarSize, $avatarSize, $fallbackBgColor);

            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);

            $name = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
            $initials = '';
            if (! empty($name)) {
                $parts = preg_split('/\s+/', $name);
                if (count($parts) >= 2) {
                    $initials = strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
                } else {
                    $initials = strtoupper(substr($name, 0, min(2, strlen($name))));
                }
            }
            if (empty($initials)) {
                $initials = 'P';
            }

            // Draw initials centered in avatar circle
            $this->drawCenteredBoldText($avatarImg, 110, $radius, $radius, $whiteColor, $fontBold, $initials);

            $tx = (int) ($centerX - ($avatarSize / 2));
            $ty = (int) ($centerY - ($avatarSize / 2));
            imagecopy($canvas, $avatarImg, $tx, $ty, 0, 0, $avatarSize, $avatarSize);
            imagedestroy($avatarImg);
        }
    }
}
