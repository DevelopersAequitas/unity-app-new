<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Media\FileUploadService;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WearTheBadgeImageGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Get existing welcome creative URL from SQL or generate a new one app-side and store it.
     */
    public function generateOrGetUrl(User $user, bool $forceRegenerate = false): string
    {
        $existingUrl = $user->welcome_creative_url ?? $user->profile_card_image_url;

        $existingUuid = null;
        if (filled($existingUrl) && preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', (string) $existingUrl, $matches)) {
            $existingUuid = $matches[1];
        }

        $fileRecord = null;
        if ($existingUuid) {
            $fileRecord = FileModel::find($existingUuid) ?? File::find($existingUuid);
        }

        if (! $forceRegenerate && filled($existingUrl) && $fileRecord && $fileRecord->s3_key) {
            $disk = config('filesystems.default', 'public');
            if (Storage::disk($disk)->exists($fileRecord->s3_key) || Storage::disk('public')->exists($fileRecord->s3_key)) {
                return (string) $existingUrl;
            }
            $forceRegenerate = true;
        }

        $fileModel = $this->generate($user, $fileRecord);
        $imageUrl = url('/api/v1/files/'.$fileModel->id);

        $updateData = [];
        if (Schema::hasColumn('users', 'welcome_creative_url')) {
            $updateData['welcome_creative_url'] = $imageUrl;
        }
        if (Schema::hasColumn('users', 'profile_card_image_url')) {
            $updateData['profile_card_image_url'] = $imageUrl;
        }

        if (! empty($updateData)) {
            try {
                $user->forceFill($updateData)->saveQuietly();
            } catch (\Throwable $e) {
                Log::warning("WearTheBadgeImageGenerator: Could not persist creative URL to DB for user {$user->id}: {$e->getMessage()}");
            }
        }

        Log::info("WearTheBadgeImageGenerator: Automatically stored welcome creative URL in SQL for user {$user->id}", [
            'welcome_creative_url' => $imageUrl,
            'profile_card_image_url' => $imageUrl,
        ]);

        return $imageUrl;
    }

    /**
     * Generate the welcome creative badge image file app-side.
     */
    public function generate(User $user, ?FileModel $targetFileRecord = null): FileModel
    {
        try {
            $templatePath = public_path('images/d806d2e9-05ae-427a-9359-026ea10d7f64.webp');
            if (! file_exists($templatePath)) {
                $url = 'https://peersunity.com/storage/uploads/2026/05/27/d806d2e9-05ae-427a-9359-026ea10d7f64.webp';
                try {
                    @mkdir(dirname($templatePath), 0755, true);
                    $response = Http::withoutVerifying()->timeout(15)->get($url);
                    if ($response->successful()) {
                        file_put_contents($templatePath, $response->body());
                    }
                } catch (\Throwable $e) {
                    Log::warning('WearTheBadgeImageGenerator: Could not download background template: '.$e->getMessage());
                }
            }

            $baseImg = null;
            if (file_exists($templatePath)) {
                $baseImg = @imagecreatefromwebp($templatePath);
                if (! $baseImg) {
                    $baseImg = @imagecreatefrompng($templatePath);
                }
                if (! $baseImg) {
                    $baseImg = @imagecreatefromjpeg($templatePath);
                }
                if (! $baseImg) {
                    $baseImg = @imagecreatefromstring((string) file_get_contents($templatePath));
                }
            }

            if ($baseImg) {
                $width = imagesx($baseImg);
                $height = imagesy($baseImg);
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);
                imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
                imagedestroy($baseImg);
            } else {
                $width = 1122;
                $height = 1402;
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);
                $bg = imagecolorallocate($canvas, 245, 245, 245);
                imagefill($canvas, 0, 0, $bg);
            }

            // Draw Logo layer (if available)
            $logoPath = public_path('images/peersglobal-logo.png');
            if (! file_exists($logoPath)) {
                $logoPath = public_path('images/logo.png');
            }
            if (file_exists($logoPath)) {
                $logoImg = @imagecreatefrompng($logoPath);
                if (! $logoImg) {
                    $logoImg = @imagecreatefromjpeg($logoPath);
                }
                if ($logoImg) {
                    $origLogoWidth = imagesx($logoImg);
                    $origLogoHeight = imagesy($logoImg);

                    $logoWidth = 291.72;
                    $logoHeight = $origLogoHeight * ($logoWidth / $origLogoWidth);

                    $logoX = 1122 - 28.05 - $logoWidth;
                    $logoY = -77.11;

                    imagecopyresampled($canvas, $logoImg, (int) $logoX, (int) $logoY, 0, 0, (int) $logoWidth, (int) $logoHeight, $origLogoWidth, $origLogoHeight);
                    imagedestroy($logoImg);
                }
            }

            // Draw circle avatar
            $avatarSize = 440;
            $avatarCenterX = 564.366;
            $avatarCenterY = 719.646;
            $navyBlue = imagecolorallocate($canvas, 0, 47, 108); // #002F6C
            $this->drawNewUserAvatar($canvas, $user, (int) $avatarCenterX, (int) $avatarCenterY, $avatarSize, $navyBlue);

            // Extract raw data from user
            $nameRaw = trim($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
            if ($nameRaw === '') {
                $nameRaw = 'Valued Peer';
            }

            $designationRaw = trim((string) ($user->designation ?? ''));
            $companyRaw = trim((string) ($user->company_name ?? ''));
            $categoryRaw = trim((string) ($user->business_sub_category ?? ''));

            // Wrapping and Formatting Text based on Specifications
            $nameLines = $this->formatWithHyphenation(strtoupper($nameRaw), 16, 2);
            $designationLines = $designationRaw !== '' ? $this->formatWithHyphenation($designationRaw, 20, 2) : [];
            $companyLines = $companyRaw !== '' ? $this->formatWithHyphenation($companyRaw, 18, 2) : [];
            $categoryLines = $categoryRaw !== '' ? $this->formatWithHyphenation($categoryRaw, 22, 1) : [];

            // Font paths
            $fontRegular = $this->getLibreFranklinFont('regular');
            $fontMedium = $this->getLibreFranklinFont('medium');
            $fontBold = $this->getLibreFranklinFont('bold');

            // Find best scale to fit bounds
            $scale = 1.0;
            $maxTextWidth = 942.48;
            $maxTextHeight = 238.34;
            $gapBase = 10;
            $showDivider = (count($designationLines) > 0 && count($companyLines) > 0);

            while ($scale > 0.3) {
                $nameSize = 53.85 * $scale;
                $designationSize = 35.90 * $scale;
                $companySize = 42.63 * $scale;
                $categorySize = 33.66 * $scale;

                $gap = $gapBase * $scale;

                $nameHeight = count($nameLines) * ($nameSize * 1.15);
                $designationHeight = count($designationLines) * ($designationSize * 1.15);
                $dividerHeight = $showDivider ? (15 * $scale) : 0;
                $companyHeight = count($companyLines) * ($companySize * 1.15);
                $categoryHeight = count($categoryLines) * ($categorySize * 1.15);

                $totalHeight = $nameHeight;
                if ($designationHeight > 0) {
                    $totalHeight += $gap + $designationHeight;
                }
                if ($showDivider) {
                    $totalHeight += $gap + $dividerHeight;
                }
                if ($companyHeight > 0) {
                    $totalHeight += $gap + $companyHeight;
                }
                if ($categoryHeight > 0) {
                    $totalHeight += $gap + $categoryHeight;
                }

                $fits = true;
                if ($totalHeight > $maxTextHeight) {
                    $fits = false;
                } else {
                    foreach ($nameLines as $line) {
                        $bbox = @imagettfbbox($nameSize, 0, $fontMedium, $line);
                        if ($bbox && abs($bbox[4] - $bbox[0]) > $maxTextWidth) {
                            $fits = false;
                            break;
                        }
                    }
                    if ($fits && count($designationLines) > 0) {
                        foreach ($designationLines as $line) {
                            $bbox = @imagettfbbox($designationSize, 0, $fontMedium, $line);
                            if ($bbox && abs($bbox[4] - $bbox[0]) > $maxTextWidth) {
                                $fits = false;
                                break;
                            }
                        }
                    }
                    if ($fits && count($companyLines) > 0) {
                        foreach ($companyLines as $line) {
                            $bbox = @imagettfbbox($companySize, 0, $fontBold, $line);
                            if ($bbox && abs($bbox[4] - $bbox[0]) > $maxTextWidth) {
                                $fits = false;
                                break;
                            }
                        }
                    }
                    if ($fits && count($categoryLines) > 0) {
                        foreach ($categoryLines as $line) {
                            $bbox = @imagettfbbox($categorySize, 0, $fontMedium, $line);
                            if ($bbox && abs($bbox[4] - $bbox[0]) > $maxTextWidth) {
                                $fits = false;
                                break;
                            }
                        }
                    }
                }

                if ($fits) {
                    break;
                }

                $scale -= 0.05;
            }

            // Final Layout Positioning
            $areaTopY = 995.42;
            $nameSize = 53.85 * $scale;
            $designationSize = 35.90 * $scale;
            $companySize = 42.63 * $scale;
            $categorySize = 33.66 * $scale;
            $gap = $gapBase * $scale;

            $nameHeight = count($nameLines) * ($nameSize * 1.15);
            $designationHeight = count($designationLines) * ($designationSize * 1.15);
            $dividerHeight = $showDivider ? (15 * $scale) : 0;
            $companyHeight = count($companyLines) * ($companySize * 1.15);
            $categoryHeight = count($categoryLines) * ($categorySize * 1.15);

            $totalHeight = $nameHeight;
            if ($designationHeight > 0) {
                $totalHeight += $gap + $designationHeight;
            }
            if ($showDivider) {
                $totalHeight += $gap + $dividerHeight;
            }
            if ($companyHeight > 0) {
                $totalHeight += $gap + $companyHeight;
            }
            if ($categoryHeight > 0) {
                $totalHeight += $gap + $categoryHeight;
            }

            $currentY = $areaTopY + ($maxTextHeight - $totalHeight) / 2;

            $colorNavy = imagecolorallocate($canvas, 0, 47, 108); // #002F6C
            $colorGray61 = imagecolorallocate($canvas, 97, 97, 97); // #616161
            $colorGray75 = imagecolorallocate($canvas, 117, 117, 117); // #757575
            $colorCrimson = imagecolorallocate($canvas, 178, 34, 34); // #B22222

            $centerX = 1122 / 2;

            // 1. Draw Member Name
            $currentY = $this->drawWrappedLines($canvas, $nameLines, $nameSize, $centerX, $currentY, $colorNavy, $fontMedium);

            // 2. Draw Designation
            if (count($designationLines) > 0) {
                $currentY += $gap;
                $currentY = $this->drawWrappedLines($canvas, $designationLines, $designationSize, $centerX, $currentY, $colorGray61, $fontMedium);
            }

            // 3. Draw Divider
            if ($showDivider) {
                $currentY += $gap;
                $this->drawGradientDivider($canvas, $centerX, (int) ($currentY + $dividerHeight / 2), 168.3 * $scale, $colorNavy, $colorCrimson);
                $currentY += $dividerHeight;
            }

            // 4. Draw Company Name with special color rules
            if (count($companyLines) > 0) {
                $currentY += $gap;
                $currentY = $this->drawCompanyText($canvas, $companyLines, $companySize, $centerX, $currentY, $fontBold, $colorNavy, $colorCrimson);
            }

            // 5. Draw Category
            if (count($categoryLines) > 0) {
                $currentY += $gap;
                $currentY = $this->drawWrappedLines($canvas, $categoryLines, $categorySize, $centerX, $currentY, $colorGray75, $fontMedium);
            }

            // 6. Save PNG
            $filename = 'welcome_creative_'.Str::uuid().'.png';
            $tempPath = tempnam(sys_get_temp_dir(), 'wc_img');

            imagepng($canvas, $tempPath, 9);
            imagedestroy($canvas);

            $disk = config('filesystems.default', 'public');
            $finalPath = 'uploads/'.now()->format('Y/m/d').'/'.(string) Str::uuid().'.png';

            if ($targetFileRecord) {
                if ($targetFileRecord->s3_key) {
                    $finalPath = preg_replace('/\.webp$/i', '.png', $targetFileRecord->s3_key);
                }
                $targetFileRecord->s3_key = $finalPath;
                $fileModel = $targetFileRecord;
            } else {
                $fileModel = new FileModel;
                $fileModel->id = (string) Str::uuid();
                $fileModel->s3_key = $finalPath;
            }

            $stream = fopen($tempPath, 'r');
            $stored = Storage::disk($disk)->put($finalPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $stored) {
                throw new \RuntimeException("Failed to store welcome creative image for user {$user->id} to disk {$disk}");
            }

            $fileModel->mime_type = 'image/png';
            $fileModel->size_bytes = filesize($tempPath);
            $fileModel->width = $width;
            $fileModel->height = $height;
            $fileModel->save();

            // Ensure available on public disk for web / WhatsApp rendering
            if ($disk !== 'public') {
                try {
                    $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                    Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                } catch (\Throwable $e) {
                    Log::error('WearTheBadgeImageGenerator: Failed copying creative to public disk: '.$e->getMessage());
                }
            }

            @unlink($tempPath);

            Log::info("WearTheBadgeImageGenerator: Generated creative image file {$fileModel->id} for user {$user->id}");

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error('WearTheBadgeImageGenerator: Failed generating creative image: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get Libre Franklin font by weight name, downloading dynamically if missing.
     */
    private function getLibreFranklinFont(string $weight = 'regular'): string
    {
        $filename = match ($weight) {
            'bold' => 'LibreFranklin-Bold.ttf',
            'medium' => 'LibreFranklin-Medium.ttf',
            default => 'LibreFranklin-Regular.ttf',
        };

        $fontPath = public_path('fonts/'.$filename);
        if (! file_exists($fontPath)) {
            $url = 'https://github.com/impallari/Libre-Franklin/raw/master/fonts/TTF/'.$filename;
            try {
                @mkdir(dirname($fontPath), 0755, true);
                $response = Http::withoutVerifying()->timeout(15)->get($url);
                if ($response->successful()) {
                    file_put_contents($fontPath, $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("WearTheBadgeImageGenerator: Could not download font {$filename}: ".$e->getMessage());
            }
        }

        if (file_exists($fontPath)) {
            return $fontPath;
        }

        return $this->getFontPath($weight === 'bold' ? 'bold' : ($weight === 'medium' ? 'semibold' : 'regular'));
    }

    /**
     * Draw circular user avatar photo or fallback initials with strict coordinates.
     */
    private function drawNewUserAvatar($canvas, User $user, int $centerX, int $centerY, int $avatarSize, $navyColor): void
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

        if (! $avatarSource && $user->profile_photo_url) {
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
                            $tempFilePath = tempnam(sys_get_temp_dir(), 'avatar_wb_');
                            file_put_contents($tempFilePath, $response->body());
                            $avatarSource = $tempFilePath;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('WearTheBadgeImageGenerator: Could not download remote avatar: '.$e->getMessage());
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
                    $circularPhoto = $this->createCircularPhoto($avatarImg, $avatarSize);
                    if ($circularPhoto) {
                        $tx = $centerX - ($avatarSize / 2);
                        $ty = $centerY - ($avatarSize / 2);
                        imagecopy($canvas, $circularPhoto, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($circularPhoto);
                        $drawn = true;
                    }
                    imagedestroy($avatarImg);
                }
            } catch (\Throwable $e) {
                Log::warning('WearTheBadgeImageGenerator: Error cropping avatar: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        if (! $drawn) {
            $displayName = $user->display_name ?: $user->first_name ?: 'P';
            $initial = strtoupper(substr(trim($displayName), 0, 1));
            if (empty($initial)) {
                $initial = 'P';
            }

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            $radius = $avatarSize / 2;
            imagefilledellipse($avatarImg, (int) $radius, (int) $radius, $avatarSize, $avatarSize, $navyColor);

            $fontBold = $this->getLibreFranklinFont('bold');
            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
            $fontSize = 179.52;

            if (file_exists($fontBold)) {
                $this->drawCenteredBoldText($avatarImg, $fontSize, $radius, $radius, $whiteColor, $fontBold, $initial);
            } else {
                imagestring($avatarImg, 5, (int) ($radius - 15), (int) ($radius - 20), $initial, $whiteColor);
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
     * Wrap text into lines based on custom character wrap.
     */
    private function formatWithHyphenation(string $text, int $charLimit, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }

            if (strlen($word) > $charLimit) {
                if (! empty($currentLine)) {
                    $lines[] = $currentLine;
                    $currentLine = '';
                }

                $remainingWord = $word;
                while (strlen($remainingWord) > $charLimit) {
                    $part = substr($remainingWord, 0, $charLimit - 1).'-';
                    $lines[] = $part;
                    $remainingWord = substr($remainingWord, $charLimit - 1);
                }
                $currentLine = $remainingWord;

                continue;
            }

            $testLine = empty($currentLine) ? $word : $currentLine.' '.$word;
            if (strlen($testLine) > $charLimit) {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }

        if (! empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return array_slice($lines, 0, $maxLines);
    }

    /**
     * Draw pre-wrapped centered text lines and return bottom Y position.
     */
    private function drawWrappedLines($canvas, array $lines, float $fontSize, float $centerX, float $startY, $color, string $fontPath, float $lineHeightMultiplier = 1.15): float
    {
        $currentY = $startY;
        foreach ($lines as $line) {
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $line);
            if ($bbox) {
                $w = abs($bbox[4] - $bbox[0]);
                $x = $centerX - ($w / 2);
                $baselineY = $currentY + $fontSize * 0.85;

                imagettftext($canvas, $fontSize, 0, (int) $x, (int) $baselineY, $color, $fontPath, $line);
                $currentY += (int) ($fontSize * $lineHeightMultiplier);
            } else {
                imagestring($canvas, 5, (int) ($centerX - 10), (int) $currentY, $line, $color);
                $currentY += 20;
            }
        }

        return $currentY;
    }

    /**
     * Draw left and right gradient divider lines with a center dot.
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

        imagefilledellipse($canvas, (int) $centerX, (int) $y, 5, 5, $colorCrimson);

        for ($i = 0; $i < $lineWidth; $i++) {
            $ratio = $i / $lineWidth;
            $alpha = (int) (127 * $ratio);
            $color = imagecolorallocatealpha($canvas, 178, 34, 34, $alpha);
            imagesetpixel($canvas, (int) ($rightStart + $i), (int) $y, $color);
            imagesetpixel($canvas, (int) ($rightStart + $i), (int) ($y + 1), $color);
        }
    }

    /**
     * Draw company name using multiple colors based on final word rules.
     */
    private function drawCompanyText($canvas, array $lines, float $fontSize, float $centerX, float $startY, string $fontPath, $colorNavy, $colorCrimson, float $lineHeightMultiplier = 1.15): float
    {
        $currentY = $startY;
        $totalLines = count($lines);

        foreach ($lines as $index => $line) {
            $bbox = @imagettfbbox($fontSize, 0, $fontPath, $line);
            if (! $bbox) {
                imagestring($canvas, 5, (int) ($centerX - 10), (int) $currentY, $line, $colorNavy);
                $currentY += 20;

                continue;
            }

            $w = abs($bbox[4] - $bbox[0]);
            $startX = $centerX - ($w / 2);
            $baselineY = $currentY + $fontSize * 0.85;

            $isFinalLine = ($index === $totalLines - 1);
            $words = preg_split('/\s+/', trim($line));

            if ($isFinalLine && count($words) > 1) {
                $finalWord = array_pop($words);
                $firstPart = implode(' ', $words).' ';

                $bboxFirst = @imagettfbbox($fontSize, 0, $fontPath, $firstPart);
                $wFirst = $bboxFirst ? abs($bboxFirst[4] - $bboxFirst[0]) : 0;

                imagettftext($canvas, $fontSize, 0, (int) $startX, (int) $baselineY, $colorNavy, $fontPath, $firstPart);
                imagettftext($canvas, $fontSize, 0, (int) ($startX + $wFirst), (int) $baselineY, $colorCrimson, $fontPath, $finalWord);
            } elseif ($isFinalLine && count($words) === 1) {
                $isMultiWordCompany = ($totalLines > 1) || (count($words) > 1);
                $color = $isMultiWordCompany ? $colorCrimson : $colorNavy;

                imagettftext($canvas, $fontSize, 0, (int) $startX, (int) $baselineY, $color, $fontPath, $line);
            } else {
                imagettftext($canvas, $fontSize, 0, (int) $startX, (int) $baselineY, $colorNavy, $fontPath, $line);
            }

            $currentY += (int) ($fontSize * $lineHeightMultiplier);
        }

        return $currentY;
    }

    /**
     * Resolve circle name for user context.
     */
    private function resolveCircleName(User $user): string
    {
        if (Schema::hasTable('circle_members') && Schema::hasTable('circles')) {
            try {
                $circleName = DB::table('circle_members')
                    ->join('circles', 'circles.id', '=', 'circle_members.circle_id')
                    ->where('circle_members.user_id', (string) $user->id)
                    ->whereNull('circle_members.deleted_at')
                    ->whereNull('circle_members.left_at')
                    ->orderByDesc('circle_members.created_at')
                    ->value('circles.name');

                if (filled($circleName)) {
                    return trim((string) $circleName);
                }
            } catch (\Throwable $e) {
            }
        }

        $fallback = $user->active_circle_addon_name ?? $user->company_name ?? $user->city ?? '';

        return trim((string) $fallback);
    }
}
