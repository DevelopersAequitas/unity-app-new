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

            if ($baseImg) {
                $width = imagesx($baseImg);
                $height = imagesy($baseImg);
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);
                imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
                imagedestroy($baseImg);
            } else {
                // Generate sleek dark navy & gold template canvas app-side if image template missing
                $width = 800;
                $height = 1000;
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);

                $navyBg = imagecolorallocate($canvas, 15, 23, 42); // Deep slate navy (#0F172A)
                imagefill($canvas, 0, 0, $navyBg);

                // Gold Accent Card Border
                $goldBorder = imagecolorallocate($canvas, 193, 154, 88); // #C19A58
                imagesetthickness($canvas, 4);
                imagerectangle($canvas, 20, 20, $width - 20, $height - 20, $goldBorder);
                imagesetthickness($canvas, 1);
            }

            // Define Theme Colors
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $gold = imagecolorallocate($canvas, 193, 154, 88); // #C19A58
            $subtleGray = imagecolorallocate($canvas, 203, 213, 225); // #CBD5E1

            $centerX = (int) ($width / 2);
            $avatarSize = (int) ($width * 0.35); // ~280px
            $avatarCenterY = (int) ($height * 0.32);

            // 1. Draw User Avatar Photo or Initials inside circular frame
            $this->drawUserAvatar($canvas, $user, $centerX, $avatarCenterY, $avatarSize, $gold);

            // 2. Draw User Display Name
            $fontBold = $this->getFontPath('bold');
            $fontRegular = $this->getFontPath('regular');

            $name = trim($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
            if ($name === '') {
                $name = 'Valued Peer Member';
            }

            $nameStartY = (int) ($avatarCenterY + ($avatarSize / 2) + 50);
            $this->drawWrappedCenteredText(
                $canvas,
                strtoupper($name),
                24,
                $centerX,
                $nameStartY,
                $gold,
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

            $subtitleStartY = $nameStartY + 55;
            $this->drawWrappedCenteredText(
                $canvas,
                $subtitle,
                16,
                $centerX,
                $subtitleStartY,
                $subtleGray,
                $fontRegular,
                (int) ($width * 0.85)
            );

            // 4. Draw Welcome Header / Badge Title
            $badgeTitle = 'WEAR THE BADGE WITH PRIDE';
            $badgeTitleY = (int) ($height * 0.12);
            $this->drawWrappedCenteredText(
                $canvas,
                $badgeTitle,
                22,
                $centerX,
                $badgeTitleY,
                $white,
                $fontBold,
                (int) ($width * 0.85)
            );

            // 5. Save WebP & Register via FileUploadService
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
    private function drawUserAvatar($canvas, User $user, int $centerX, int $centerY, int $avatarSize, $goldColor): void
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

        // Draw premium gold ring border around the avatar
        $whiteColor = imagecolorallocate($canvas, 255, 255, 255);
        $this->drawPremiumGoldFrame($canvas, $centerX, $centerY, $avatarSize, $whiteColor, '#C19A58');
    }
}
