<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel4;
use App\Models\File;
use App\Models\FileModel;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use App\Services\Media\FileUploadService;
use App\Traits\HasCreativeRendering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SpeedNetworkingP2pCreativeGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Generate composite creative for completed Speed Networking 1-on-1 match.
     */
    public function generate(
        User $userA,
        User $userB,
        SpeedNetworkingEvent $event,
        SpeedNetworkingMatch $match
    ): FileModel {
        try {
            $templatePath = public_path('images/introduction-template.png');
            $useTemplate = file_exists($templatePath);

            $width = 819;
            $height = 1024;

            if ($useTemplate) {
                $baseImg = @imagecreatefrompng($templatePath);
                if ($baseImg) {
                    $width = imagesx($baseImg);
                    $height = imagesy($baseImg);
                    $canvas = imagecreatetruecolor($width, $height);
                    imagealphablending($canvas, true);
                    imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
                    imagedestroy($baseImg);
                } else {
                    $canvas = $this->createFallbackBackground($width, $height);
                }
            } else {
                $canvas = $this->createFallbackBackground($width, $height);
            }

            // Colors
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $gold = imagecolorallocate($canvas, 193, 154, 88); // #C19A58
            $blueColor = imagecolorallocate($canvas, 22, 63, 115);
            $redColor = imagecolorallocate($canvas, 197, 48, 48);
            $lightGray = imagecolorallocate($canvas, 203, 213, 225); // Slate 300
            $badgeBg = imagecolorallocatealpha($canvas, 15, 23, 42, 40); // Subtle badge backing

            // Coordinates for 819x1024 layout
            $leftCenterX = (int) ($width * 0.278); // 228
            $rightCenterX = (int) ($width * 0.712); // 583
            $centerY = (int) ($height * 0.395); // 405
            $avatarSize = 256;
            $textStartY = 675;

            // 1. Draw Avatars with Gold Ring Borders
            $this->drawAvatarOrInitial($canvas, $userA, $leftCenterX, $centerY, $avatarSize, $blueColor);
            $this->drawAvatarOrInitial($canvas, $userB, $rightCenterX, $centerY, $avatarSize, $redColor);

            // 2. Draw Fonts & Text
            $fontBold = $this->getFontPath('bold');
            $fontSemiBold = $this->getFontPath('semibold');
            $fontRegular = $this->getFontPath('regular');

            // Draw Peer Details
            $this->drawUserDetails($canvas, $userA, $leftCenterX, $gold, $white, $lightGray, $fontBold, $fontSemiBold, $fontRegular);
            $this->drawUserDetails($canvas, $userB, $rightCenterX, $gold, $white, $lightGray, $fontBold, $fontSemiBold, $fontRegular);

            // 3. Draw Subheader: "P2P SPEED NETWORKING • ROUND X"
            $roundNumber = $match->round_number;
            $eventTitle = trim($event->title ?: 'Speed Networking');
            $subHeader = "P2P SPEED NETWORKING • ROUND {$roundNumber}";
            $this->drawCenteredFittedText($canvas, $subHeader, 16, (int) ($width / 2), 655, $gold, $fontBold, 700);

            // 4. Congratulations / Networking Statement
            $userAName = $userA->display_name ?: trim(($userA->first_name ?? '').' '.($userA->last_name ?? ''));
            if (empty($userAName)) {
                $userAName = 'Peer';
            }

            $userBName = $userB->display_name ?: trim(($userB->first_name ?? '').' '.($userB->last_name ?? ''));
            if (empty($userBName)) {
                $userBName = 'Peer';
            }

            $paragraph = "Congratulations to {$userAName} and {$userBName} on successfully completing your 1-on-1 Speed Networking meeting. Wishing you both powerful collaboration and mutual business growth across the Peers Global Community.";

            $lines = $this->wrapTextToLines($paragraph, 15, $fontRegular, 680);
            $currentY = $textStartY + 15;
            $lineHeight = 28;

            foreach ($lines as $line) {
                $bbox = @imagettfbbox(15, 0, $fontRegular, $line);
                if ($bbox) {
                    $w = abs($bbox[4] - $bbox[0]);
                    $x = ($width / 2) - ($w / 2);
                    imagettftext($canvas, 15, 0, (int) $x, $currentY, $white, $fontRegular, $line);
                } else {
                    imagestring($canvas, 4, (int) (($width / 2) - 200), $currentY, $line, $white);
                }
                $currentY += $lineHeight;
            }

            // Draw Event tag at bottom
            if (! empty($eventTitle)) {
                $eventTag = "Event: {$eventTitle}";
                $this->drawCenteredFittedText($canvas, $eventTag, 13, (int) ($width / 2), $currentY + 20, $lightGray, $fontSemiBold, 650);
            }

            // 5. Save as WebP via FileUploadService
            $filename = 'speed_networking_p2p_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'snp2p');

            imagewebp($canvas, $tempPath, 95);
            imagedestroy($canvas);

            $uploadedFile = new UploadedFile(
                $tempPath,
                $filename,
                'image/webp',
                null,
                true
            );

            $disk = config('filesystems.default', 'public');

            try {
                $fileModel = $this->fileUploadService->store($uploadedFile, null, $disk);

                if ($disk !== 'public') {
                    try {
                        $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                        Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                    } catch (Throwable $syncEx) {
                        Log::warning('SpeedNetworkingP2pCreativeGenerator: Could not mirror to public disk: '.$syncEx->getMessage());
                    }
                }
            } catch (Throwable $uploadEx) {
                Log::warning('SpeedNetworkingP2pCreativeGenerator: FileUploadService store failed, saving directly to public disk: '.$uploadEx->getMessage());

                $s3Key = 'speed_networking/'.$filename;
                Storage::disk('public')->put($s3Key, (string) file_get_contents($tempPath));

                $fileModel = FileModel::create([
                    'id' => (string) Str::uuid(),
                    'uploader_user_id' => $userA->id,
                    's3_key' => $s3Key,
                    'mime_type' => 'image/webp',
                    'size_bytes' => file_exists($tempPath) ? filesize($tempPath) : 0,
                    'width' => $width,
                    'height' => $height,
                ]);
            }

            @unlink($tempPath);

            return $fileModel;
        } catch (Throwable $e) {
            Log::error('Failed to generate speed networking P2P creative: '.$e->getMessage(), [
                'user_a_id' => $userA->id,
                'user_b_id' => $userB->id,
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create an elegant dark gradient canvas fallback if template is missing.
     */
    private function createFallbackBackground(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        // Dark gradient from #0B1B3D to #060D20
        for ($y = 0; $y < $height; $y++) {
            $factor = $y / $height;
            $r = (int) (11 * (1 - $factor) + 6 * $factor);
            $g = (int) (27 * (1 - $factor) + 13 * $factor);
            $b = (int) (61 * (1 - $factor) + 32 * $factor);
            $lineColor = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, 0, $y, $width, $y, $lineColor);
        }

        return $canvas;
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
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 567, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $company, 13, $centerX, 591, $companyColor, $fontSemiBold, 330);
            $this->drawCenteredFittedText($canvas, $category, 12, $centerX, 613, $categoryColor, $fontRegular, 330);
        } elseif ($hasCompany) {
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 571, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $company, 13, $centerX, 595, $companyColor, $fontSemiBold, 330);
        } elseif ($hasCategory) {
            $this->drawCenteredFittedText($canvas, $name, 17, $centerX, 571, $nameColor, $fontBold, 330);
            $this->drawCenteredFittedText($canvas, $category, 12, $centerX, 595, $categoryColor, $fontRegular, 330);
        } else {
            $this->drawCenteredFittedText($canvas, $name, 18, $centerX, 574, $nameColor, $fontBold, 330);
        }
    }

    public function resolveCompanyName(User $user): string
    {
        $company = $user->company_name ?? $user->company ?? $user->business_name ?? '';

        return trim((string) $company);
    }

    public function resolveCategoryName(User $user): string
    {
        $category = '';

        if ($user->relationLoaded('level4Category')) {
            $category = $user->getRelation('level4Category')?->name ?? '';
        } elseif (! empty($user->business_category_id)) {
            $category = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
        }

        if (empty($category) && ! empty($user->business_sub_category)) {
            $category = $user->business_sub_category;
        }

        if (empty($category)) {
            if ($user->relationLoaded('businessCategory')) {
                $category = $user->getRelation('businessCategory')?->name ?? '';
            } elseif (! empty($user->business_category_id)) {
                $category = CircleCategory::find($user->business_category_id)?->name ?? '';
            }
        }

        if (empty($category)) {
            if ($user->relationLoaded('mainBusinessCategory')) {
                $category = $user->getRelation('mainBusinessCategory')?->name ?? '';
            } elseif (! empty($user->main_business_category_id)) {
                $category = CircleCategory::find($user->main_business_category_id)?->name ?? '';
            }
        }

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

        imagestring($canvas, 4, (int) ($centerX - 50), (int) ($y - 10), $text, $color);
    }

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
                } catch (Throwable $e) {
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
            } catch (Throwable $e) {
                Log::warning('Could not process user avatar for speed networking creative: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

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

            $fontPath = $this->getFontPath('bold');
            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
            $fontSizeInit = (int) ($avatarSize * 0.42);
            if (file_exists($fontPath)) {
                $this->drawCenteredBoldText($avatarImg, $fontSizeInit, (int) $avatarRadius, (int) $avatarRadius, $whiteColor, $fontPath, $initial);
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

    private function drawCenteredBoldText(
        $canvas,
        int $fontSize,
        int $centerX,
        int $centerY,
        int $color,
        string $fontPath,
        string $text
    ): void {
        $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($bbox) {
            $textWidth = abs($bbox[4] - $bbox[0]);
            $textHeight = abs($bbox[5] - $bbox[1]);
            $x = $centerX - ($textWidth / 2);
            $y = $centerY + ($textHeight / 2) - 4;
            imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y, $color, $fontPath, $text);
        }
    }
}
