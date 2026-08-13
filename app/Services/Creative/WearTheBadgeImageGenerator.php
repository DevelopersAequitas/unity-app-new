<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Media\FileUploadService;
use App\Traits\HasCreativeRendering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        if (! $forceRegenerate && filled($existingUrl)) {
            return (string) $existingUrl;
        }

        $fileModel = $this->generate($user);
        $imageUrl = url('/api/v1/files/'.$fileModel->id);

        try {
            $user->forceFill([
                'welcome_creative_url' => $imageUrl,
                'profile_card_image_url' => $imageUrl,
            ])->saveQuietly();
        } catch (\Throwable $e) {
            try {
                DB::table('users')->where('id', (string) $user->id)->update([
                    'welcome_creative_url' => $imageUrl,
                    'profile_card_image_url' => $imageUrl,
                ]);
            } catch (\Throwable $ex) {
                Log::error("WearTheBadgeImageGenerator: Could not persist creative URL to DB for user {$user->id}: {$ex->getMessage()}");
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
    public function generate(User $user): FileModel
    {
        try {
            $templatePath = public_path('images/wear-the-badge-template.png');
            if (! file_exists($templatePath)) {
                $templatePath = public_path('images/welcome-template.png');
            }

            $baseImg = null;
            if (file_exists($templatePath)) {
                $baseImg = @imagecreatefrompng($templatePath);
                if (! $baseImg) {
                    $baseImg = @imagecreatefromjpeg($templatePath);
                }
                if (! $baseImg) {
                    $baseImg = @imagecreatefromstring((string) file_get_contents($templatePath));
                }
            }

            $isTemplateLoaded = false;
            if ($baseImg) {
                $width = imagesx($baseImg);
                $height = imagesy($baseImg);
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);
                imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
                imagedestroy($baseImg);
                $isTemplateLoaded = true;
            } else {
                // Generate clean white canvas with blue & crimson red welcome theme matching Screenshot 2
                $width = 800;
                $height = 1000;
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);

                $whiteBg = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $whiteBg);

                // Top-Left Dot Matrix Decorative Accent Grid
                $dotColor = imagecolorallocate($canvas, 203, 213, 225); // #CBD5E1
                for ($row = 0; $row < 12; $row++) {
                    for ($col = 0; $col < 15; $col++) {
                        $dx = 20 + ($col * 16);
                        $dy = 20 + ($row * 16);
                        if (($col + $row) % 2 === 0 && $dx < 200 && $dy < 180) {
                            imagefilledellipse($canvas, $dx, $dy, 5, 5, $dotColor);
                        }
                    }
                }

                // Top-Right Confetti Circle Icon Accent
                $redAccent = imagecolorallocate($canvas, 200, 16, 46);
                imagefilledellipse($canvas, $width - 80, 80, 46, 46, $redAccent);
                $whiteAccent = imagecolorallocate($canvas, 255, 255, 255);
                imagesetthickness($canvas, 3);
                imageline($canvas, $width - 92, 80, $width - 68, 80, $whiteAccent);
                imagesetthickness($canvas, 1);

                // Bottom Curved Gradient Wave Banner (#0B20A8 -> #C8102E)
                for ($y = 820; $y <= $height; $y++) {
                    $ratio = ($y - 820) / ($height - 820);
                    $r = (int) (11 + ($ratio * (200 - 11)));
                    $g = (int) (32 + ($ratio * (16 - 32)));
                    $b = (int) (168 + ($ratio * (46 - 168)));
                    $waveColor = imagecolorallocate($canvas, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));

                    // Draw filled curve arc
                    $curveY = (int) ($y + (sin(($y - 820) / 40) * 10));
                    imagefilledrectangle($canvas, 0, $curveY, $width, $height, $waveColor);
                }
            }

            // Define Theme Colors
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $navyBlue = imagecolorallocate($canvas, 11, 32, 168); // #0B20A8
            $crimsonRed = imagecolorallocate($canvas, 200, 16, 46); // #C8102E
            $darkSlate = imagecolorallocate($canvas, 15, 23, 42); // #0F172A
            $subtleGray = imagecolorallocate($canvas, 71, 85, 105); // #475569

            $fontBold = $this->getFontPath('bold');
            $fontRegular = $this->getFontPath('regular');

            $centerX = (int) ($width / 2);

            if ($isTemplateLoaded) {
                // Calibrated coordinates for 1122x1402 base template image (Screenshot 2)
                $avatarSize = (int) ($width * 0.41); // ~460px
                $avatarCenterY = (int) ($height * 0.492); // ~690px

                // 1. Draw User Avatar Photo or Initials inside circle frame
                $this->drawUserAvatar($canvas, $user, $centerX, $avatarCenterY, $avatarSize, $navyBlue, true);

                // 2. Draw User Display Name
                $name = trim($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
                if ($name === '') {
                    $name = 'Valued Peer Member';
                }

                $nameStartY = (int) ($avatarCenterY + ($avatarSize / 2) + 55);
                $this->drawWrappedCenteredText(
                    $canvas,
                    strtoupper($name),
                    26,
                    $centerX,
                    $nameStartY,
                    $darkSlate,
                    $fontBold,
                    (int) ($width * 0.85)
                );

                // 3. Draw Designation / Company Subtitle
                $designation = trim((string) ($user->designation ?? ''));
                $company = trim((string) ($user->company_name ?? ''));
                $subtitle = implode(' • ', array_filter([$designation, $company]));
                if ($subtitle === '') {
                    $subtitle = 'Global Peer Community Member';
                }

                $subtitleStartY = $nameStartY + 45;
                $this->drawWrappedCenteredText(
                    $canvas,
                    $subtitle,
                    18,
                    $centerX,
                    $subtitleStartY,
                    $subtleGray,
                    $fontRegular,
                    (int) ($width * 0.85)
                );
            } else {
                $avatarSize = (int) ($width * 0.42); // ~336px
                $avatarCenterY = 480;

                // Pill Badge "WELCOME!"
                $pillWidth = 240;
                $pillHeight = 46;
                $pillX = $centerX - ($pillWidth / 2);
                $pillY = 70;

                for ($px = (int) $pillX; $px <= $pillX + $pillWidth; $px++) {
                    $pratio = ($px - $pillX) / $pillWidth;
                    $pr = (int) (11 + ($pratio * (200 - 11)));
                    $pg = (int) (32 + ($pratio * (16 - 32)));
                    $pb = (int) (168 + ($pratio * (46 - 168)));
                    $pcol = imagecolorallocate($canvas, max(0, min(255, $pr)), max(0, min(255, $pg)), max(0, min(255, $pb)));
                    imagefilledrectangle($canvas, $px, (int) $pillY, $px + 1, (int) ($pillY + $pillHeight), $pcol);
                }
                $this->drawWrappedCenteredText($canvas, 'WELCOME!', 18, $centerX, (int) ($pillY + 32), $white, $fontBold, $pillWidth);

                // Title Line 1: "NEW PEER"
                $this->drawWrappedCenteredText($canvas, 'NEW PEER', 30, $centerX, 165, $navyBlue, $fontBold, 700);

                // Title Line 2: "TO GLOBAL FAMILY."
                $this->drawWrappedCenteredText($canvas, 'TO GLOBAL FAMILY.', 30, $centerX, 220, $crimsonRed, $fontBold, 750);

                // 2. Draw User Avatar Photo or Initials inside dual gradient arc rings
                $this->drawUserAvatar($canvas, $user, $centerX, $avatarCenterY, $avatarSize, $navyBlue);

                // 3. Draw User Display Name
                $name = trim($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
                if ($name === '') {
                    $name = 'Valued Peer Member';
                }

                $nameStartY = (int) ($avatarCenterY + ($avatarSize / 2) + 45);
                $this->drawWrappedCenteredText(
                    $canvas,
                    strtoupper($name),
                    22,
                    $centerX,
                    $nameStartY,
                    $darkSlate,
                    $fontBold,
                    (int) ($width * 0.85)
                );

                // 4. Draw Designation / Company Subtitle
                $designation = trim((string) ($user->designation ?? ''));
                $company = trim((string) ($user->company_name ?? ''));
                $subtitle = implode(' • ', array_filter([$designation, $company]));
                if ($subtitle === '') {
                    $subtitle = 'Global Peer Community Member';
                }

                $subtitleStartY = $nameStartY + 38;
                $this->drawWrappedCenteredText(
                    $canvas,
                    $subtitle,
                    15,
                    $centerX,
                    $subtitleStartY,
                    $subtleGray,
                    $fontRegular,
                    (int) ($width * 0.85)
                );

                // 5. Draw Footer Tagline & Website on Bottom Banner
                $tagline = 'Peers are Partners in Business & Friends in Life.';
                $this->drawWrappedCenteredText($canvas, $tagline, 14, $centerX, 900, $white, $fontRegular, 700);

                $website = 'PeersGlobal.com';
                $this->drawWrappedCenteredText($canvas, $website, 15, $centerX, 945, $white, $fontBold, 700);
            }

            // 6. Save WebP & Register via FileUploadService
            $filename = 'welcome_creative_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'wc_img');

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
            $fileModel = $this->fileUploadService->store($uploadedFile, null, $disk);

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
     * Render avatar photo or fallback initials inside circular frame.
     */
    private function drawUserAvatar($canvas, User $user, int $centerX, int $centerY, int $avatarSize, $goldColor, bool $isTemplateLoaded = false): void
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
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'avatar_wb_');
                        file_put_contents($tempFilePath, $response->body());
                        $avatarSource = $tempFilePath;
                    }
                } catch (\Throwable $e) {
                    Log::warning('WearTheBadgeImageGenerator: Could not download remote avatar: '.$e->getMessage());
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

        // Draw fallback initials if no photo available
        if (! $drawn) {
            $displayName = $user->display_name ?: $user->first_name ?: 'Peer';
            $initial = strtoupper(substr($displayName, 0, 1));

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            $radius = $avatarSize / 2;
            $blueBg = imagecolorallocate($avatarImg, 30, 41, 59); // Slate dark blue
            imagefilledellipse($avatarImg, (int) $radius, (int) $radius, $avatarSize, $avatarSize, $blueBg);

            $fontBold = $this->getFontPath('bold');
            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
            $fontSize = (int) ($avatarSize * 0.4);

            if (file_exists($fontBold)) {
                $this->drawCenteredBoldText($avatarImg, $fontSize, $radius, $radius, $whiteColor, $fontBold, $initial);
            } else {
                imagestring($avatarImg, 5, (int) ($radius - 10), (int) ($radius - 10), $initial, $whiteColor);
            }

            $circularAvatar = $this->createCircularPhoto($avatarImg, $avatarSize);
            imagedestroy($avatarImg);

            $tx = $centerX - ($avatarSize / 2);
            $ty = $centerY - ($avatarSize / 2);
            imagecopy($canvas, $circularAvatar, (int) $tx, (int) $ty, 0, 0, $avatarSize, $avatarSize);
            imagedestroy($circularAvatar);
        }

        if (! $isTemplateLoaded) {
            // Draw dual gradient ring border (Blue & Crimson Red gradient arcs matching Screenshot 2)
            $navyBlue = imagecolorallocate($canvas, 11, 32, 168);
            $crimsonRed = imagecolorallocate($canvas, 200, 16, 46);

            imagesetthickness($canvas, 5);
            // Outer Blue Arc (Left)
            imagearc($canvas, $centerX, $centerY, $avatarSize + 16, $avatarSize + 16, 90, 270, $navyBlue);
            // Outer Red Arc (Right)
            imagearc($canvas, $centerX, $centerY, $avatarSize + 16, $avatarSize + 16, 270, 90, $crimsonRed);

            imagesetthickness($canvas, 3);
            // Inner Blue Arc (Left)
            imagearc($canvas, $centerX, $centerY, $avatarSize + 6, $avatarSize + 6, 80, 260, $navyBlue);
            // Inner Red Arc (Right)
            imagearc($canvas, $centerX, $centerY, $avatarSize + 6, $avatarSize + 6, 260, 80, $crimsonRed);

            imagesetthickness($canvas, 1);
        }
    }
}
