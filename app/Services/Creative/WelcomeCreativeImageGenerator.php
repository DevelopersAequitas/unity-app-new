<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\CircleCategoryLevel4;
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

        $canvas = imagecreatefrompng($templatePath);
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

        // Colors
        $colorDark = imagecolorallocate($canvas, 15, 23, 42);      // #0F172A
        $colorGold = imagecolorallocate($canvas, 180, 83, 9);       // #B45309 Amber/Gold
        $colorSlate = imagecolorallocate($canvas, 100, 116, 139);   // #64748B Slate
        $darkCircleBg = imagecolorallocate($canvas, 19, 34, 71);    // #132247 Navy fallback

        // 1. Profile Avatar: Diameter = 450px, Center X = 561px, Center Y = 720px
        $targetDiameter = 450;
        $circleCenterX = (int) ($width / 2); // 561
        $circleCenterY = 720;

        $this->drawAvatarOrInitial($canvas, $user, $circleCenterX, $circleCenterY, $targetDiameter, $darkCircleBg, $fontBold);

        // Text data preparation
        $name = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($name === '') {
            $name = 'PEER MEMBER';
        }
        $name = strtoupper($name);

        $company = trim((string) ($user->company_name ?? $user->company ?? ''));
        $designation = trim((string) ($user->designation ?? $user->job_title ?? ''));

        $line2Parts = [];
        if (! empty($designation)) {
            $line2Parts[] = $designation;
        }
        if (! empty($company)) {
            $line2Parts[] = $company;
        }
        $line2Text = implode(' | ', $line2Parts);
        if (empty($line2Text)) {
            $line2Text = 'Peers Global Member';
        }

        // Category / Subcategory
        $categoryName = '';
        if ($user->relationLoaded('mainBusinessCategory') && $user->mainBusinessCategory) {
            $categoryName = $user->mainBusinessCategory->name ?? '';
        } elseif ($user->relationLoaded('level4Category') && $user->level4Category) {
            $categoryName = $user->level4Category->name ?? '';
        } elseif (! empty($user->business_category_id)) {
            $categoryName = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
        }

        if (empty($categoryName)) {
            $categoryName = $user->category_name ?? $user->business_sub_category ?? '';
        }
        if (is_array($categoryName)) {
            $categoryName = $categoryName['name'] ?? $categoryName['label'] ?? '';
        }
        $categoryName = trim((string) $categoryName);
        if (in_array(strtolower($categoryName), ['null', 'none', 'n/a', 'peers global member', 'peer'], true)) {
            $categoryName = '';
        }

        // City
        $cityName = '';
        if ($user->relationLoaded('city') && $user->city) {
            $cityName = $user->city->name ?? '';
        } elseif (! empty($user->city)) {
            $cityName = is_string($user->city) ? $user->city : ($user->city['name'] ?? '');
        }
        if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
            $decoded = json_decode(trim($cityName), true);
            $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
        }
        $cityName = trim((string) $cityName);
        if (in_array(strtolower($cityName), ['null', 'none', 'n/a'], true)) {
            $cityName = '';
        }

        $line3Parts = [];
        if (! empty($categoryName)) {
            $line3Parts[] = $categoryName;
        }
        if (! empty($cityName)) {
            $line3Parts[] = $cityName;
        }
        $line3Text = implode(' | ', $line3Parts);

        // Helper to draw center-aligned text with auto-scaling to fit within maxWidth
        $drawCenterText = function ($img, int $fontSize, int $y, int $color, string $font, string $text, int $maxWidth = 950) use ($width): void {
            if (empty($text)) {
                return;
            }
            $size = $fontSize;
            $bbox = @imagettfbbox($size, 0, $font, $text);
            while ($bbox && abs($bbox[4] - $bbox[0]) > $maxWidth && $size > 12) {
                $size -= 1;
                $bbox = @imagettfbbox($size, 0, $font, $text);
            }
            if ($bbox) {
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = (int) (($width - $textWidth) / 2);
                imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
            }
        };

        // Draw Member Name (Y = 1010, Montserrat Bold, 36pt, Dark)
        $drawCenterText($canvas, 36, 1010, $colorDark, $fontBold, $name, 920);

        // Draw Line 2: Designation | Company (Y = 1065, Montserrat SemiBold, 24pt, Gold/Amber)
        $drawCenterText($canvas, 24, 1065, $colorGold, $fontSemiBold, $line2Text, 920);

        // Draw Line 3: Category | City (Y = 1115, Montserrat Regular, 20pt, Slate)
        if (! empty($line3Text)) {
            $drawCenterText($canvas, 20, 1115, $colorSlate, $fontRegular, $line3Text, 920);
        }

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
