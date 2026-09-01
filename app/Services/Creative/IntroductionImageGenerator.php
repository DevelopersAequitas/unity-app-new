<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel4;
use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Media\FileUploadService;
use App\Traits\HasCreativeRendering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IntroductionImageGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    public function generate(User $referrer, User $newMember): FileModel
    {
        try {
            $templatePath = public_path('images/introduction-template.png');
            if (! file_exists($templatePath)) {
                throw new \RuntimeException('Introduction template image not found at: '.$templatePath);
            }

            $baseImg = @imagecreatefrompng($templatePath);
            if (! $baseImg) {
                throw new \RuntimeException('Failed to load introduction template PNG.');
            }

            $width = imagesx($baseImg);
            $height = imagesy($baseImg);
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, true);
            imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
            imagedestroy($baseImg);

            // Define colors
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $gold = imagecolorallocate($canvas, 193, 154, 88); // #C19A58
            $blueColor = imagecolorallocate($canvas, 22, 63, 115); // Navy Blue for Referrer fallback
            $redColor = imagecolorallocate($canvas, 197, 48, 48); // Red for New Member fallback

            // Calibrated Coordinates (for 819x1024 template)
            $leftCenterX = 228;
            $rightCenterX = 583;
            $centerY = 396;
            $avatarSize = 252;
            $textStartY = 675;

            // 1. Draw Referrer Avatar or Initial (Left Circle)
            $this->drawAvatarOrInitial($canvas, $referrer, $leftCenterX, $centerY, $avatarSize, $blueColor);

            // 2. Draw New Member Avatar or Initial (Right Circle)
            $this->drawAvatarOrInitial($canvas, $newMember, $rightCenterX, $centerY, $avatarSize, $redColor);

            // 3. Draw Referrer and New Member Info (Name, Business Name, Category)
            $fontBold = $this->getFontPath('bold');
            $fontSemiBold = $this->getFontPath('semibold');
            $fontRegular = $this->getFontPath('regular');
            $lightGray = imagecolorallocate($canvas, 203, 213, 225); // #CBD5E1 Slate 300

            $this->drawUserDetails($canvas, $referrer, $leftCenterX, $gold, $white, $lightGray, $fontBold, $fontSemiBold, $fontRegular);
            $this->drawUserDetails($canvas, $newMember, $rightCenterX, $gold, $white, $lightGray, $fontBold, $fontSemiBold, $fontRegular);

            // 4. Draw Congratulations Paragraph Text in White (Y = 675)
            $referrerName = $referrer->display_name ?: trim(($referrer->first_name ?? '').' '.($referrer->last_name ?? ''));
            if (empty($referrerName)) {
                $referrerName = 'Peer Member';
            }

            $newMemberName = $newMember->display_name ?: trim(($newMember->first_name ?? '').' '.($newMember->last_name ?? ''));
            if (empty($newMemberName)) {
                $newMemberName = 'New Member';
            }

            $paragraph = "Congratulations to {$referrerName} for introducing {$newMemberName} to the Peers Global Community of Collaboration. Wishing you both a successful journey filled with meaningful connections, collaboration, and endless opportunities.";

            $lines = $this->wrapTextToLines($paragraph, 15, $fontRegular, 680); // 680 width for clean margins
            $currentY = $textStartY;
            $lineHeight = 28;

            foreach ($lines as $line) {
                $bbox = imagettfbbox(15, 0, $fontRegular, $line);
                $w = abs($bbox[4] - $bbox[0]);
                $x = ($width / 2) - ($w / 2);
                imagettftext($canvas, 15, 0, (int) $x, $currentY, $white, $fontRegular, $line);
                $currentY += $lineHeight;
            }

            // Save high-quality WebP & Register via FileUploadService
            $filename = 'introduction_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'intro');

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

            // Copy to public disk if needed
            if ($disk !== 'public') {
                try {
                    $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                    Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                } catch (\Throwable $e) {
                    Log::error('IntroductionImageGenerator: Failed to copy creative to public disk: '.$e->getMessage());
                }
            }

            @unlink($tempPath);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error('Failed to generate introduction creative: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Draw user details (Name, Business Name, Category) under their avatar circle.
     */
    private function drawUserDetails(
        $canvas,
        User $user,
        int $centerX,
        int $nameColor,
        int $companyColor,
        int $categoryColor,
        string $fontBold,
        string $fontSemiBold,
        string $fontRegular
    ): void {
        $name = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if (empty($name)) {
            $name = 'Peer Member';
        }

        $company = $this->resolveCompanyName($user);
        $category = $this->resolveCategoryName($user);

        $hasCompany = ! empty($company);
        $hasCategory = ! empty($category);

        if ($hasCompany && $hasCategory) {
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 558, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $company, 13, $centerX, 582, $companyColor, $fontSemiBold, 330);
            $this->drawCenteredFittedText($canvas, $category, 12, $centerX, 604, $categoryColor, $fontRegular, 330);
        } elseif ($hasCompany) {
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 562, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $company, 13, $centerX, 586, $companyColor, $fontSemiBold, 330);
        } elseif ($hasCategory) {
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 562, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $category, 12, $centerX, 586, $categoryColor, $fontRegular, 330);
        } else {
            $this->drawCenteredFittedText($canvas, $name, 18, $centerX, 565, $nameColor, $fontBold, 330);
        }
    }

    /**
     * Resolve the business/company name of a user.
     */
    public function resolveCompanyName(User $user): string
    {
        $company = $user->company_name ?? $user->company ?? $user->business_name ?? '';

        return trim((string) $company);
    }

    /**
     * Resolve the business category name of a user from DB relations/attributes.
     */
    public function resolveCategoryName(User $user): string
    {
        $category = '';

        // 1. Level 4 Subcategory
        if ($user->relationLoaded('level4Category')) {
            $category = $user->getRelation('level4Category')?->name ?? '';
        } elseif (! empty($user->business_category_id)) {
            $category = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
        }

        // 2. Business Sub-category attribute
        if (empty($category) && ! empty($user->business_sub_category)) {
            $category = $user->business_sub_category;
        }

        // 3. Business Category (Level 1 / primary)
        if (empty($category)) {
            if ($user->relationLoaded('businessCategory')) {
                $category = $user->getRelation('businessCategory')?->name ?? '';
            } elseif (! empty($user->business_category_id)) {
                $category = CircleCategory::find($user->business_category_id)?->name ?? '';
            }
        }

        // 4. Main Business Category
        if (empty($category)) {
            if ($user->relationLoaded('mainBusinessCategory')) {
                $category = $user->getRelation('mainBusinessCategory')?->name ?? '';
            } elseif (! empty($user->main_business_category_id)) {
                $category = CircleCategory::find($user->main_business_category_id)?->name ?? '';
            }
        }

        // 5. Fallbacks
        if (empty($category)) {
            $category = $user->category_name ?? $user->industry ?? $user->business_type ?? '';
        }

        if (is_array($category)) {
            $category = $category['name'] ?? $category['label'] ?? '';
        }

        $category = trim((string) $category);

        if (in_array(strtolower($category), ['null', 'none', 'n/a'], true)) {
            $category = '';
        }

        return $category;
    }

    /**
     * Draw text centered at X, fitted within maxWidth by shrinking or truncating with ellipsis.
     */
    private function drawCenteredFittedText(
        $canvas,
        string $text,
        int $fontSize,
        int $centerX,
        int $y,
        int $color,
        string $fontPath,
        int $maxWidth = 330
    ): void {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($bbox) {
            $w = abs($bbox[4] - $bbox[0]);
            if ($w <= $maxWidth) {
                $x = $centerX - ($w / 2);
                imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y, $color, $fontPath, $text);

                return;
            }

            // Try slightly smaller font
            $reducedSize = max(10, $fontSize - 2);
            $bboxReduced = @imagettfbbox($reducedSize, 0, $fontPath, $text);
            if ($bboxReduced) {
                $wReduced = abs($bboxReduced[4] - $bboxReduced[0]);
                if ($wReduced <= $maxWidth) {
                    $x = $centerX - ($wReduced / 2);
                    imagettftext($canvas, $reducedSize, 0, (int) $x, (int) $y, $color, $fontPath, $text);

                    return;
                }
            }

            // Truncate with ellipsis if still too long
            $truncated = $text;
            while (mb_strlen($truncated) > 3) {
                $truncated = mb_substr($truncated, 0, -1);
                $testStr = trim($truncated).'…';
                $bboxTrunc = @imagettfbbox($reducedSize, 0, $fontPath, $testStr);
                if ($bboxTrunc && abs($bboxTrunc[4] - $bboxTrunc[0]) <= $maxWidth) {
                    $x = $centerX - (abs($bboxTrunc[4] - $bboxTrunc[0]) / 2);
                    imagettftext($canvas, $reducedSize, 0, (int) $x, (int) $y, $color, $fontPath, $testStr);

                    return;
                }
            }
        }

        // Fallback if TTF failed
        imagestring($canvas, 4, (int) ($centerX - 50), (int) ($y - 10), $text, $color);
    }

    /**
     * Draw Avatar or Initial.
     */
    private function drawAvatarOrInitial($canvas, User $user, int $centerX, int $centerY, int $avatarSize, $fallbackBgColor): void
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
                Log::warning('Could not process user avatar for introduction creative: '.$e->getMessage());
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

            $avatarRadius = $avatarSize / 2;
            imagefilledellipse($avatarImg, (int) $avatarRadius, (int) $avatarRadius, $avatarSize, $avatarSize, $fallbackBgColor);

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
}
