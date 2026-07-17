<?php

namespace App\Services\Media;

use App\Models\BirthdayCreativeConfig;
use App\Models\FileModel;
use App\Models\User;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Log;

class BirthdayCreativeImageService
{
    use HasCreativeRendering;

    /**
     * Generate birthday creative image for a user.
     */
    public function generate(User $user): FileModel
    {
        try {
            $config = BirthdayCreativeConfig::first();
            if (! $config) {
                // Default config if none exists
                $config = new BirthdayCreativeConfig([
                    'is_enabled' => true,
                    'background_gradient_start' => '#8E2DE2',
                    'background_gradient_end' => '#4A00E0',
                    'text_color' => '#FFFFFF',
                ]);
            }

            // Resolve base background template
            $baseImg = null;
            $isCustomTemplate = false;

            // 1. Check if there is an uploaded template in database configuration
            if ($config && $config->template_file_id) {
                $templateFile = FileModel::find($config->template_file_id);
                if ($templateFile && $templateFile->s3_key) {
                    $disk = config('filesystems.default', 'public');
                    $templateData = null;
                    if (Storage::disk($disk)->exists($templateFile->s3_key)) {
                        $templateData = Storage::disk($disk)->get($templateFile->s3_key);
                    } elseif (Storage::disk('public')->exists($templateFile->s3_key)) {
                        $templateData = Storage::disk('public')->get($templateFile->s3_key);
                    }

                    if ($templateData) {
                        $baseImg = @imagecreatefromstring($templateData);
                        if ($baseImg) {
                            $isCustomTemplate = true;
                        }
                    }
                }
            }

            // 2. Fall back to the default static template if no uploaded template exists
            if (! $baseImg) {
                $staticTemplatePath = public_path('images/birthday-template.png');
                if (file_exists($staticTemplatePath)) {
                    $baseImg = @imagecreatefrompng($staticTemplatePath);
                    if (! $baseImg) {
                        $baseImg = @imagecreatefromjpeg($staticTemplatePath);
                    }
                    if (! $baseImg) {
                        $baseImg = @imagecreatefromstring(file_get_contents($staticTemplatePath));
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
                // Fallback to gradient background if no template exists
                $width = 1080;
                $height = 1080;
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                $gradient = $this->createGradientImage(
                    $width,
                    $height,
                    $config->background_gradient_start ?? '#8E2DE2',
                    $config->background_gradient_end ?? '#4A00E0'
                );
                imagecopyresampled($canvas, $gradient, 0, 0, 0, 0, $width, $height, imagesx($gradient), imagesy($gradient));
                imagedestroy($gradient);
            }

            // Compute dynamic coordinate boundaries based on template source
            if ($isCustomTemplate) {
                // Configured for 1080x1350 premium portrait template
                $centerX = (int) ($width / 2);
                $centerY = (int) ($height * 0.4126); // Center Y: 557
                $avatarSize = 390; // Subtle increase (8-10% of 360)
                $nameStartY = 765; // Spacing maintained exactly
                $nameFontSize = 42; // Reduced to 42px — SemiBold at 42pt = elegant & balanced
                $companyFontSize = 32;
                $nameColor = imagecolorallocate($canvas, 255, 255, 255); // White
                $companyColor = imagecolorallocate($canvas, 193, 154, 88); // Gold (#C19A58)
            } else {
                // Configured for 1024x1024 fallback square template
                $centerX = (int) ($width / 2);
                $centerY = (int) ($height * 0.5); // Center Y: 512
                $avatarSize = (int) ($width * 0.254); // Circle diameter: 260
                $nameStartY = (int) ($height * 0.699); // Adjusted to Y: 716
                $nameFontSize = 42;
                $companyFontSize = 22;
                $nameColor = imagecolorallocate($canvas, 0, 35, 140); // Deep Blue (#00238C)
                $companyColor = imagecolorallocate($canvas, 168, 29, 52); // Red/Purple (#A81D34)
            }

            $white = imagecolorallocate($canvas, 255, 255, 255);

            // Wiping out profile photo circular region to ensure no background placeholder shows behind
            // Clear only the inner area of the avatar (diameter = avatarSize - 4) so we don't leak white background under the gold ring
            if ($isCustomTemplate) {
                imagefilledellipse($canvas, $centerX, $centerY, $avatarSize - 4, $avatarSize - 4, $white);
            } else {
                imagefilledellipse($canvas, $centerX, $centerY, $avatarSize + 10, $avatarSize + 10, $white);
            }

            // Draw profile photo or initials
            $this->drawAvatarOrInitial($canvas, $user, $centerX, $centerY, $avatarSize, $isCustomTemplate);

            // Draw premium gold ring and glow frame
            if ($isCustomTemplate) {
                $this->drawPremiumGoldFrame($canvas, $centerX, $centerY, $avatarSize, $white, '#C19A58');
            }

            // Add dynamic user name and company name
            $this->drawTextAndDetails($canvas, $user, $centerX, $nameStartY, $nameFontSize, $companyFontSize, $nameColor, $companyColor, $isCustomTemplate);

            // Save image to storage
            $diskName = 'public';
            $folder = 'uploads/birthday/'.now()->format('Y/m/d');
            if (! Storage::disk($diskName)->exists($folder)) {
                Storage::disk($diskName)->makeDirectory($folder);
            }

            $fileName = Str::uuid().'.jpg';
            $relativeFilePath = $folder.'/'.$fileName;
            $absolutePath = Storage::disk($diskName)->path($relativeFilePath);

            // Ensure directories exist
            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'bday');
            imagejpeg($canvas, $tempPath, 90);
            imagedestroy($canvas);

            rename($tempPath, $absolutePath);

            // Create File record in database
            $fileModel = FileModel::create([
                'uploader_user_id' => $user->id,
                's3_key' => $relativeFilePath,
                'mime_type' => 'image/jpeg',
                'size_bytes' => filesize($absolutePath),
                'width' => $width,
                'height' => $height,
            ]);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error("Failed to generate birthday creative for user {$user->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Create vertical gradient image fallback.
     */
    private function createGradientImage(int $width, int $height, string $startColor, string $endColor)
    {
        $canvas = imagecreatetruecolor($width, $height);

        $start = $this->hexToRgb($startColor);
        $end = $this->hexToRgb($endColor);

        for ($y = 0; $y < $height; $y++) {
            $r = (int) ($start['r'] + ($end['r'] - $start['r']) * ($y / $height));
            $g = (int) ($start['g'] + ($end['g'] - $start['g']) * ($y / $height));
            $b = (int) ($start['b'] + ($end['b'] - $start['b']) * ($y / $height));
            $color = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, 0, $y, $width, $y, $color);
        }

        return $canvas;
    }

    /**
     * Helper to parse hex to rgb.
     */
    private function hexToRgb(string $hex): array
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
     * Draw Avatar or Name Initial.
     */
    private function drawAvatarOrInitial($canvas, User $user, int $centerX, int $centerY, int $avatarSize, bool $isCustomTemplate): void
    {
        // Resolve avatar image source
        $avatarSource = null;
        $tempFilePath = null;
        if (! empty($user->profile_photo_file_id)) {
            $fileRecord = FileModel::find($user->profile_photo_file_id);
            if ($fileRecord && $fileRecord->s3_key) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk($disk)->path($fileRecord->s3_key);
                } elseif (Storage::disk('public')->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk('public')->path($fileRecord->s3_key);
                }
            }
        }

        if (! $avatarSource && ! empty($user->profile_photo_url)) {
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
            } else {
                $avatarSource = $user->profile_photo_url;
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
                Log::warning('Could not process user avatar for birthday creative: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        // Draw initial if avatar failed or is missing
        if (! $drawnSuccessfully) {
            $displayName = $user->display_name ?: $user->first_name ?: 'User';
            $initial = strtoupper(substr($displayName, 0, 1));

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            // Fill the avatar circle with gold based on custom template
            $avatarBgColor = $isCustomTemplate ? imagecolorallocate($avatarImg, 193, 154, 88) : imagecolorallocate($avatarImg, 0, 35, 140);
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
     * Draw dynamic user name and company name.
     */
    private function drawTextAndDetails($canvas, User $user, int $centerX, int $nameStartY, int $nameFontSize, int $companyFontSize, $nameColor, $companyColor, bool $isCustomTemplate): void
    {
        // Name uses SemiBold (600 weight) — lighter, more premium than ExtraBold/Bold
        $fontPathName    = $this->getFontPath('semibold');
        $fontPathSemiBold = $this->getFontPath('semibold');

        $displayName = strtoupper($user->display_name ?: ($user->first_name.' '.$user->last_name));
        $companyName = $user->company_name ?: ($user->designation ?: 'Global Peer');

        if ($isCustomTemplate) {
            // Apply vertical height safety loop to prevent overlapping with greeting message at Y = 870
            $maxAllowedY = 870;
            $nameSpacing = 18;
            $companySpacing = 18;

            do {
                // Use 820px max-width so long names always have comfortable side margins
                $nameLines = $this->wrapTextToLines($displayName, $nameFontSize, $fontPathName, 820);
                $nameHeight = 0;
                foreach ($nameLines as $line) {
                    $bbox = @imagettfbbox($nameFontSize, 0, $fontPathName, $line);
                    if ($bbox) {
                        $nameHeight += (int)(abs($bbox[5] - $bbox[1]) * 1.35);
                    }
                }

                $companyLines = $this->wrapTextToLines($companyName, $companyFontSize, $fontPathSemiBold, 950);
                $companyHeight = 0;
                foreach ($companyLines as $line) {
                    $bbox = @imagettfbbox($companyFontSize, 0, $fontPathSemiBold, $line);
                    if ($bbox) {
                        $companyHeight += (int)(abs($bbox[5] - $bbox[1]) * 1.35);
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
            // Original logic for static fallback template
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

            $this->drawWrappedCenteredText(
                $canvas,
                $companyName,
                $companyFontSize,
                $centerX,
                $nextY + 14,
                $companyColor,
                $this->getFontPath('regular'),
                950
            );
        }
    }
}
