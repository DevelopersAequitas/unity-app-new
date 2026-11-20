<?php

declare(strict_types=1);

namespace App\Services\Celebrations;

use App\Models\AnniversaryTemplate;
use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CelebrationCreativeService
{
    use HasCreativeRendering;

    /**
     * Generate the approved birthday or separate anniversary creative image for a peer.
     * - Birthday: Uses the approved birthday creative template (`public/images/birthday_creative_template.jpg`),
     *   preserving original design, branding, text, and colors completely unchanged, inserting the peer's
     *   actual profile photo (or 2-letter initials gradient circle if no photo exists) inside the circular frame.
     * - Anniversary: NEVER uses the birthday template! Uses the active uploaded AnniversaryTemplate if present,
     *   or generates a dedicated separate Peers Global Wedding Anniversary creative.
     *
     * @return array{file: FileModel, url: string, template_used: string, used_initials: bool}
     */
    public function generate(User $user, string $eventType): array
    {
        if ($eventType === 'anniversary') {
            return $this->generateAnniversaryCreative($user);
        }

        return $this->generateBirthdayCreative($user);
    }

    /**
     * Persist an exact rendered creative image (base64 data URL) submitted from the Admin Panel preview
     * so Admin Preview and Mobile App Timeline display the exact same final creative image.
     *
     * @return array{file: FileModel, url: string, template_used: string, used_initials: bool}|null
     */
    public function storeRenderedCreativeDataUrl(User $user, string $eventType, string $dataUrl): ?array
    {
        $trimmed = trim($dataUrl);
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/is', $trimmed, $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        if ($binary === false || strlen($binary) < 1024) {
            return null;
        }

        $img = @imagecreatefromstring($binary);
        if (! $img) {
            return null;
        }

        $width = imagesx($img);
        $height = imagesy($img);
        if ($width < 200 || $height < 200) {
            imagedestroy($img);

            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $img, 0, 0, 0, 0, $width, $height);
        imagedestroy($img);

        $fileModel = $this->saveCanvasAsFile($canvas, $user, $eventType, $width, $height);

        return [
            'file' => $fileModel,
            'url' => url("/api/v1/files/{$fileModel->id}"),
            'template_used' => 'admin_rendered_preview',
            'used_initials' => false,
        ];
    }

    public function generateBirthdayCreative(User $user): array
    {
        $approvedTemplatePath = public_path('images/birthday_creative_template.jpg');
        $baseImg = null;
        $templateUsed = 'approved_birthday_template';

        if (file_exists($approvedTemplatePath)) {
            $raw = @file_get_contents($approvedTemplatePath);
            if ($raw !== false) {
                $baseImg = @imagecreatefromstring($raw);
            }
        }

        if (! $baseImg) {
            // Secondary fallback if approved template file is missing in test env
            $width = 1166;
            $height = 1262;
            $canvas = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($canvas, 248, 250, 252);
            imagefill($canvas, 0, 0, $bg);
            $templateUsed = 'fallback_birthday_canvas';
        } else {
            $origWidth = imagesx($baseImg);
            $origHeight = imagesy($baseImg);
            // Upscale cleanly to HD (~1166x1262) when source template is under 1000px wide
            // so the circular photo and TrueType text render razor-sharp while keeping the template unchanged.
            $scale = $origWidth < 1000 ? 2 : 1;
            $width = $origWidth * $scale;
            $height = $origHeight * $scale;
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $baseImg, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
            imagedestroy($baseImg);
        }

        // Exact circular frame coordinates matching the approved birthday_creative_template.jpg (583x631)
        $centerX = (int) round($width * 0.4940);
        $centerY = (int) round($height * 0.5745);
        $avatarDiameter = (int) round($width * 0.3560);

        $usedInitials = $this->drawProfilePhotoOrInitials(
            $canvas,
            $user,
            $centerX,
            $centerY,
            $avatarDiameter,
            'birthday'
        );

        // Dynamically render member's Full Name, Designation / Category, and Company Name below the circular frame
        $this->drawBirthdayMemberDetails($canvas, $user, $width, $height);

        $fileModel = $this->saveCanvasAsFile($canvas, $user, 'birthday', $width, $height);

        return [
            'file' => $fileModel,
            'url' => url("/api/v1/files/{$fileModel->id}"),
            'template_used' => $templateUsed,
            'used_initials' => $usedInitials,
        ];
    }

    public function generateAnniversaryCreative(User $user): array
    {
        $baseImg = null;
        $templateUsed = 'default_anniversary_creative';

        // 1. Check active admin-uploaded AnniversaryTemplate (never uses birthday template!)
        if (Schema::hasTable('anniversary_templates')) {
            $activeTemplate = AnniversaryTemplate::query()
                ->where('is_active', true)
                ->latest('updated_at')
                ->first();

            if ($activeTemplate && filled($activeTemplate->image_path)) {
                $disk = config('filesystems.default', 'public');
                $templateData = null;

                if (Storage::disk('public')->exists($activeTemplate->image_path)) {
                    $templateData = Storage::disk('public')->get($activeTemplate->image_path);
                } elseif (Storage::disk($disk)->exists($activeTemplate->image_path)) {
                    $templateData = Storage::disk($disk)->get($activeTemplate->image_path);
                }

                if ($templateData) {
                    $baseImg = @imagecreatefromstring($templateData);
                    if ($baseImg) {
                        $templateUsed = 'custom_anniversary_template:'.$activeTemplate->id;
                    }
                }
            }
        }

        if (! $baseImg) {
            $approvedAnniversaryPath = public_path('images/anniversary_creative_template.jpg');
            if (file_exists($approvedAnniversaryPath)) {
                $raw = @file_get_contents($approvedAnniversaryPath);
                if ($raw !== false) {
                    $baseImg = @imagecreatefromstring($raw);
                    if ($baseImg) {
                        $templateUsed = 'approved_anniversary_template';
                    }
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

            if ($templateUsed === 'approved_anniversary_template') {
                // Exact circular frame coordinates matching the approved anniversary_creative_template.jpg (935x1024)
                $centerX = (int) round($width * 0.4973);
                $centerY = (int) round($height * 0.5322);
                $avatarDiameter = (int) round($width * 0.3480);
            } else {
                // Standard circular frame center matching custom uploaded portrait / square templates
                $centerX = (int) round($width * 0.4989);
                $centerY = ($height > $width) ? (int) round($height * 0.5854) : (int) round($height * 0.48);
                $avatarDiameter = (int) round($width * 0.3634);
            }
        } else {
            // Dedicated separate Peers Global Wedding Anniversary creative (gold & navy theme)
            $width = 952;
            $height = 1194;
            $canvas = $this->buildDedicatedAnniversaryCanvas($width, $height);
            $centerX = (int) round($width * 0.5);
            $centerY = (int) round($height * 0.52);
            $avatarDiameter = (int) round($width * 0.36);
        }

        $usedInitials = $this->drawProfilePhotoOrInitials(
            $canvas,
            $user,
            $centerX,
            $centerY,
            $avatarDiameter,
            'anniversary'
        );

        // If using built-in dedicated anniversary template, draw the peer name cleanly below the ring
        if ($templateUsed === 'default_anniversary_creative') {
            $displayName = strtoupper($this->resolvePeerDisplayName($user));
            $goldColor = imagecolorallocate($canvas, 217, 119, 6);
            $navyColor = imagecolorallocate($canvas, 30, 58, 138);
            $fontBold = $this->getFontPath('bold');
            $nameY = (int) ($centerY + ($avatarDiameter / 2) + 75);

            if (file_exists($fontBold)) {
                $this->drawWrappedCenteredText($canvas, $displayName, 34, $centerX, $nameY, $navyColor, $fontBold, 820);
                $company = trim((string) ($user->company_name ?? ''));
                if ($company !== '') {
                    $this->drawWrappedCenteredText($canvas, $company, 22, $centerX, $nameY + 54, $goldColor, $this->getFontPath('semibold'), 820);
                }
            } else {
                $textW = strlen($displayName) * imagefontwidth(5);
                imagestring($canvas, 5, (int) ($centerX - $textW / 2), $nameY, $displayName, $navyColor);
            }
        }

        $fileModel = $this->saveCanvasAsFile($canvas, $user, 'anniversary', $width, $height);

        return [
            'file' => $fileModel,
            'url' => url("/api/v1/files/{$fileModel->id}"),
            'template_used' => $templateUsed,
            'used_initials' => $usedInitials,
        ];
    }

    /**
     * Build a distinct dedicated Wedding Anniversary canvas (amber/gold/navy — completely separate from birthday design).
     */
    private function buildDedicatedAnniversaryCanvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);

        // Warm cream background
        $bg = imagecolorallocate($canvas, 255, 251, 235);
        imagefill($canvas, 0, 0, $bg);

        // Gold outer border frame
        $gold = imagecolorallocate($canvas, 217, 119, 6);
        $deepGold = imagecolorallocate($canvas, 180, 83, 9);
        $navy = imagecolorallocate($canvas, 30, 58, 138);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        imagesetthickness($canvas, 8);
        imagerectangle($canvas, 24, 24, $width - 24, $height - 24, $gold);
        imagesetthickness($canvas, 2);
        imagerectangle($canvas, 38, 38, $width - 38, $height - 38, $deepGold);

        // Footer wave/banner
        imagefilledrectangle($canvas, 0, $height - 150, $width, $height, $navy);
        imagefilledrectangle($canvas, 0, $height - 156, $width, $height - 150, $gold);

        // Header typography
        $fontBold = $this->getFontPath('bold');
        $fontRegular = $this->getFontPath('regular');
        $centerX = (int) ($width / 2);

        if (file_exists($fontBold)) {
            $this->drawWrappedCenteredText($canvas, 'HAPPY WEDDING', 42, $centerX, 130, $navy, $fontBold, 820);
            $this->drawWrappedCenteredText($canvas, 'ANNIVERSARY!', 56, $centerX, 200, $gold, $fontBold, 820);
            $this->drawWrappedCenteredText(
                $canvas,
                'Wishing you a lifetime of love, partnership & happiness.',
                22,
                $centerX,
                268,
                $deepGold,
                file_exists($fontRegular) ? $fontRegular : $fontBold,
                820
            );
            $this->drawWrappedCenteredText(
                $canvas,
                'Peers are Partners in Business & Friends in Life.',
                20,
                $centerX,
                $height - 92,
                $white,
                file_exists($fontRegular) ? $fontRegular : $fontBold,
                820
            );
            $this->drawWrappedCenteredText(
                $canvas,
                'PeersGlobal.com',
                22,
                $centerX,
                $height - 46,
                $gold,
                $fontBold,
                820
            );
        } else {
            imagestring($canvas, 5, $centerX - 110, 110, 'HAPPY WEDDING ANNIVERSARY!', $navy);
            imagestring($canvas, 4, $centerX - 75, $height - 80, 'PeersGlobal.com', $white);
        }

        // Circular gold ornamental ring for avatar
        $ringY = (int) round($height * 0.52);
        $ringDiameter = (int) round($width * 0.36) + 22;
        imagefilledellipse($canvas, $centerX, $ringY, $ringDiameter + 8, $ringDiameter + 8, $gold);
        imagefilledellipse($canvas, $centerX, $ringY, $ringDiameter - 4, $ringDiameter - 4, $white);

        return $canvas;
    }

    /**
     * Draw user's profile photo inside the circular frame, or branded 2-letter initials fallback if no photo exists.
     *
     * @return bool True if initials fallback was used, false if actual profile photo was rendered.
     */
    private function drawProfilePhotoOrInitials(
        $canvas,
        User $user,
        int $centerX,
        int $centerY,
        int $avatarSize,
        string $eventType
    ): bool {
        $avatarSource = null;
        $tempFilePath = null;

        $profileFileId = $user->profile_photo_file_id ?? $user->profile_photo_id ?? null;
        if (! empty($profileFileId)) {
            $fileRecord = FileModel::find($profileFileId) ?? File::find($profileFileId);
            if ($fileRecord && filled($fileRecord->s3_key)) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk('public')->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk('public')->path($fileRecord->s3_key);
                } elseif (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk($disk)->path($fileRecord->s3_key);
                }
            }
        }

        if (! $avatarSource && ! empty($user->profile_photo_url)) {
            $url = trim((string) $user->profile_photo_url);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                try {
                    $response = Http::timeout(5)->get($url);
                    if ($response->successful() && strlen($response->body()) > 100) {
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'celebration_avatar_');
                        file_put_contents($tempFilePath, $response->body());
                        $avatarSource = $tempFilePath;
                    }
                } catch (\Throwable $e) {
                    Log::warning("[CelebrationCreativeService] Failed fetching remote avatar for user {$user->id}: ".$e->getMessage());
                }
            } elseif (file_exists($url)) {
                $avatarSource = $url;
            }
        }

        $photoDrawn = false;

        if ($avatarSource && file_exists($avatarSource)) {
            try {
                $rawBytes = @file_get_contents($avatarSource);
                $avatarImg = $rawBytes !== false ? @imagecreatefromstring($rawBytes) : false;

                if ($avatarImg) {
                    $circularPhoto = $this->createCircularPhoto($avatarImg, $avatarSize);
                    if ($circularPhoto) {
                        $tx = (int) round($centerX - ($avatarSize / 2));
                        $ty = (int) round($centerY - ($avatarSize / 2));
                        imagecopy($canvas, $circularPhoto, $tx, $ty, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($circularPhoto);
                        $photoDrawn = true;
                    }
                    imagedestroy($avatarImg);
                }
            } catch (\Throwable $e) {
                Log::warning("[CelebrationCreativeService] Failed rendering avatar for user {$user->id}: ".$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        if ($photoDrawn) {
            return false;
        }

        // Render 2-letter initials inside branded circular gradient (matching frontend CelebrationCreativeGraphic)
        $initials = $this->resolveInitials($user);

        $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
        imagealphablending($avatarImg, true);

        $startRgb = $eventType === 'birthday'
            ? ['r' => 37, 'g' => 99, 'b' => 235]   // #2563eb
            : ['r' => 217, 'g' => 119, 'b' => 6]; // #d97706
        $endRgb = $eventType === 'birthday'
            ? ['r' => 225, 'g' => 29, 'b' => 72]  // #e11d48
            : ['r' => 234, 'g' => 88, 'b' => 12]; // #ea580c

        for ($y = 0; $y < $avatarSize; $y++) {
            $ratio = $y / max(1, $avatarSize - 1);
            $r = (int) round($startRgb['r'] + ($endRgb['r'] - $startRgb['r']) * $ratio);
            $g = (int) round($startRgb['g'] + ($endRgb['g'] - $startRgb['g']) * $ratio);
            $b = (int) round($startRgb['b'] + ($endRgb['b'] - $startRgb['b']) * $ratio);
            $lineColor = imagecolorallocate($avatarImg, $r, $g, $b);
            imageline($avatarImg, 0, $y, $avatarSize, $y, $lineColor);
        }

        $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
        $radius = $avatarSize / 2;
        $fontPath = $this->getFontPath('bold');
        $fontSize = (int) round($avatarSize * (strlen($initials) > 1 ? 0.34 : 0.42));

        if (file_exists($fontPath)) {
            $this->drawCenteredBoldText($avatarImg, $fontSize, $radius, $radius, $whiteColor, $fontPath, $initials);
        } else {
            $textW = strlen($initials) * imagefontwidth(5);
            $textH = imagefontheight(5);
            imagestring($avatarImg, 5, (int) round($radius - $textW / 2), (int) round($radius - $textH / 2), $initials, $whiteColor);
        }

        $circularAvatar = $this->createCircularPhoto($avatarImg, $avatarSize);
        imagedestroy($avatarImg);

        $tx = (int) round($centerX - ($avatarSize / 2));
        $ty = (int) round($centerY - ($avatarSize / 2));
        imagecopy($canvas, $circularAvatar, $tx, $ty, 0, 0, $avatarSize, $avatarSize);
        imagedestroy($circularAvatar);

        return true;
    }

    private function saveCanvasAsFile($canvas, User $user, string $eventType, int $width, int $height): FileModel
    {
        $diskName = 'public';
        $folder = "uploads/celebrations/{$eventType}/".now('Asia/Kolkata')->format('Y/m/d');
        $fileName = Str::uuid().'.jpg';
        $relativePath = "{$folder}/{$fileName}";

        $tempPath = tempnam(sys_get_temp_dir(), 'celebration_creative_');
        imagejpeg($canvas, $tempPath, 92);
        imagedestroy($canvas);

        $bytes = file_get_contents($tempPath);
        Storage::disk($diskName)->put($relativePath, $bytes);
        $sizeBytes = strlen((string) $bytes);
        @unlink($tempPath);

        return FileModel::create([
            'uploader_user_id' => $user->id,
            's3_key' => $relativePath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => $sizeBytes,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function resolvePeerDisplayName(User $user): string
    {
        $name = trim((string) ($user->display_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $full = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));

        return $full !== '' ? $full : 'Global Peer';
    }

    /**
     * Resolve member's real category or designation from database fields (never dummy data).
     * Prefers real Business Category over generic default designations like "Member" or "Peer".
     */
    public function resolvePeerDesignationOrCategory(User $user): ?string
    {
        $designation = trim((string) ($user->designation ?? ''));
        $isGenericDesignation = $designation === ''
            || in_array(strtolower($designation), ['—', '-', 'member', 'peer', 'user', 'n/a', 'na'], true);

        try {
            $relations = [];
            foreach (['businessCategory', 'level4Category', 'mainBusinessCategory'] as $rel) {
                if (method_exists($user, $rel) && ! $user->relationLoaded($rel)) {
                    $relations[] = $rel;
                }
            }
            if ($relations !== []) {
                $user->loadMissing($relations);
            }
        } catch (\Throwable) {
            // Non-blocking relation load
        }

        $categoryCandidates = [
            $user->businessCategory?->name ?? null,
            $user->level4Category?->name ?? null,
            $user->mainBusinessCategory?->name ?? null,
            $user->business_sub_category ?? null,
        ];

        $resolvedCategory = null;
        foreach ($categoryCandidates as $candidate) {
            $clean = trim((string) ($candidate ?? ''));
            if ($clean !== '' && $clean !== '—' && $clean !== '-') {
                $resolvedCategory = $clean;
                break;
            }
        }

        if ($resolvedCategory !== null && $isGenericDesignation) {
            return $resolvedCategory;
        }

        if (! $isGenericDesignation) {
            return $designation;
        }

        if ($resolvedCategory !== null) {
            return $resolvedCategory;
        }

        return ($designation !== '' && $designation !== '—' && $designation !== '-') ? $designation : null;
    }

    /**
     * Draw member's Full Name, Company Name, decorative navy/red divider, and Category/Designation
     * in ALL UPPERCASE matching the official Peers Global Welcome Creative typography & colors.
     */
    private function drawBirthdayMemberDetails($canvas, User $user, int $width, int $height): void
    {
        $fullName = mb_strtoupper($this->resolvePeerDisplayName($user), 'UTF-8');
        $rawDesignationOrCategory = $this->resolvePeerDesignationOrCategory($user);
        $categoryText = $rawDesignationOrCategory !== null && $rawDesignationOrCategory !== ''
            ? mb_strtoupper($rawDesignationOrCategory, 'UTF-8')
            : null;
        $companyName = trim((string) ($user->company_name ?? ''));
        if ($companyName === '—' || $companyName === '-') {
            $companyName = '';
        } elseif ($companyName !== '') {
            $companyName = mb_strtoupper($companyName, 'UTF-8');
        }

        $fontBold = $this->getFontPath('bold');
        $fontSemiBold = $this->getFontPath('semibold');
        $hasTtf = file_exists($fontBold);

        // Exact colors matching the reference Welcome Creative
        $navyColor = imagecolorallocate($canvas, 0, 47, 108);      // #002F6C
        $grayColor = imagecolorallocate($canvas, 97, 97, 97);      // #616161
        $crimsonColor = imagecolorallocate($canvas, 178, 34, 34);  // #B22222

        $hScale = $height / 1262.0;
        $centerX = (int) round($width * 0.50);
        $zoneTop = (int) round($height * 0.748);
        $zoneBottom = (int) round($height * 0.868);
        $zoneHeight = max(40, $zoneBottom - $zoneTop);

        if (! $hasTtf) {
            $y = $zoneTop + 10;
            foreach (array_filter([$fullName, $companyName, $categoryText]) as $txt) {
                $tw = strlen($txt) * imagefontwidth(5);
                imagestring($canvas, 5, (int) round($centerX - ($tw / 2)), $y, $txt, $navyColor);
                $y += imagefontheight(5) + 6;
            }

            return;
        }

        $measureLine = function (string $text, string $font, int $startSize, int $minSize, int $maxWidth): array {
            $fontSize = $startSize;
            $bbox = @imagettfbbox($fontSize, 0, $font, $text);
            $textW = $bbox ? abs($bbox[4] - $bbox[0]) : 0;

            while ($textW > $maxWidth && $fontSize > $minSize) {
                $fontSize--;
                $bbox = @imagettfbbox($fontSize, 0, $font, $text);
                $textW = $bbox ? abs($bbox[4] - $bbox[0]) : 0;
            }

            if ($textW > $maxWidth && mb_strlen($text) > 12) {
                while ($textW > $maxWidth && mb_strlen($text) > 10) {
                    $text = rtrim(mb_substr($text, 0, -1));
                    $candidate = $text.'...';
                    $bbox = @imagettfbbox($fontSize, 0, $font, $candidate);
                    $textW = $bbox ? abs($bbox[4] - $bbox[0]) : 0;
                }
                $text = $text.'...';
            }

            $ascent = $bbox ? abs($bbox[7]) : $fontSize;
            $descent = $bbox ? max(0, $bbox[1]) : 4;

            return [
                'text' => $text,
                'font' => $font,
                'size' => $fontSize,
                'width' => $textW,
                'ascent' => $ascent,
                'height' => $ascent + $descent,
            ];
        };

        $nameItem = $measureLine(
            $fullName,
            $fontBold,
            max(16, (int) round(32 * $hScale)),
            max(12, (int) round(20 * $hScale)),
            (int) round($width * 0.76)
        );

        $companyItem = $companyName !== ''
            ? $measureLine(
                $companyName,
                file_exists($fontSemiBold) ? $fontSemiBold : $fontBold,
                max(11, (int) round(19 * $hScale)),
                max(9, (int) round(12 * $hScale)),
                (int) round($width * 0.70)
            )
            : null;

        $categoryItem = ($categoryText !== null && $categoryText !== '')
            ? $measureLine(
                $categoryText,
                $fontBold,
                max(10, (int) round(17 * $hScale)),
                max(8, (int) round(9 * $hScale)),
                (int) round($width * 0.56)
            )
            : null;

        $showDivider = $categoryItem !== null;
        $dividerH = $showDivider ? max(8, (int) round(12 * $hScale)) : 0;
        $gap = (int) round(8 * $hScale);

        $totalHeight = $nameItem['height'];
        if ($companyItem !== null) {
            $totalHeight += $gap + $companyItem['height'];
        }
        if ($showDivider) {
            $totalHeight += (int) round($gap * 0.7) + $dividerH + (int) round($gap * 0.7);
        }
        if ($categoryItem !== null) {
            $totalHeight += $categoryItem['height'];
        }

        $cursorY = $zoneTop + (int) round(max(0, ($zoneHeight - $totalHeight) / 2));

        // 1. Member Name (Deep Navy #002F6C, Bold, Uppercase)
        $x = (int) round($centerX - ($nameItem['width'] / 2));
        imagettftext($canvas, $nameItem['size'], 0, $x, $cursorY + $nameItem['ascent'], $navyColor, $nameItem['font'], $nameItem['text']);
        $cursorY += $nameItem['height'];

        // 2. Company Name (Cool Gray #616161, SemiBold, Uppercase)
        if ($companyItem !== null) {
            $cursorY += $gap;
            $cx = (int) round($centerX - ($companyItem['width'] / 2));
            imagettftext($canvas, $companyItem['size'], 0, $cx, $cursorY + $companyItem['ascent'], $grayColor, $companyItem['font'], $companyItem['text']);
            $cursorY += $companyItem['height'];
        }

        // 3. Decorative Divider (Navy line -- Crimson dot -- Crimson line)
        if ($showDivider) {
            $cursorY += (int) round($gap * 0.7);
            $divY = $cursorY + (int) round($dividerH / 2);
            $lineHalf = (int) round($width * 0.14);
            $dotGap = max(8, (int) round(14 * $hScale));
            $dotDiam = max(6, (int) round(9 * $hScale));

            imagesetthickness($canvas, max(1, (int) round(2 * $hScale)));
            imageline($canvas, $centerX - $lineHalf, $divY, $centerX - $dotGap, $divY, $navyColor);
            imageline($canvas, $centerX + $dotGap, $divY, $centerX + $lineHalf, $divY, $crimsonColor);
            imagesetthickness($canvas, 1);
            imagefilledellipse($canvas, $centerX, $divY, $dotDiam, $dotDiam, $crimsonColor);

            $cursorY += $dividerH + (int) round($gap * 0.7);
        }

        // 4. Category (Navy #002F6C -> Crimson #B22222 horizontal gradient, Bold, Uppercase)
        if ($categoryItem !== null) {
            $this->drawGradientTtfText(
                $canvas,
                $categoryItem['text'],
                $categoryItem['size'],
                $categoryItem['font'],
                (int) round($centerX - ($categoryItem['width'] / 2)),
                $cursorY + $categoryItem['ascent'],
                [0, 47, 108],
                [178, 34, 34]
            );
        }
    }

    /**
     * Render horizontal gradient TrueType text (e.g. #002F6C -> #B22222) cleanly onto a GD canvas.
     *
     * @param  array{0:int,1:int,2:int}  $startRgb
     * @param  array{0:int,1:int,2:int}  $endRgb
     */
    private function drawGradientTtfText($canvas, string $text, int $fontSize, string $fontPath, int $startX, int $baselineY, array $startRgb, array $endRgb): void
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars) || $chars === []) {
            return;
        }

        $totalChars = max(1, count($chars) - 1);
        $cursorX = (float) $startX;

        foreach ($chars as $idx => $ch) {
            $t = $idx / $totalChars;
            $r = (int) round($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $t);
            $g = (int) round($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $t);
            $b = (int) round($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $t);
            $col = imagecolorallocate($canvas, $r, $g, $b);

            imagettftext($canvas, $fontSize, 0, (int) round($cursorX), $baselineY, $col, $fontPath, $ch);

            $chBox = @imagettfbbox($fontSize, 0, $fontPath, $ch);
            $advance = $chBox ? abs($chBox[2] - $chBox[0]) : ($fontSize * 0.65);
            $cursorX += ($ch === ' ') ? max($advance, $fontSize * 0.36) : ($advance + max(1.0, $fontSize * 0.06));
        }
    }

    public function resolveInitials(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));

        if ($first !== '' && $last !== '') {
            return strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
        }

        $parts = preg_split('/\s+/', $this->resolvePeerDisplayName($user)) ?: [];
        $firstPart = $parts[0] ?? 'P';
        $lastPart = count($parts) > 1 ? (string) end($parts) : '';

        return strtoupper(mb_substr($firstPart, 0, 1).($lastPart !== '' ? mb_substr($lastPart, 0, 1) : ''));
    }
}
