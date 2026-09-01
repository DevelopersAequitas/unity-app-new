<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\FileModel;
use App\Models\User;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GlobalPeerCertificateImageGenerator
{
    use HasCreativeRendering;

    /**
     * Paid membership statuses that qualify for a certificate.
     */
    public const PAID_STATUSES = [
        'Only Unity Peer',
        'Circle Peer',
        'Multi Circle Peer',
        'Charter Peer',
        'Industry Advisor',
        'Charter Investor',
        'Circle Founder',
        'Circle Director',
        'Board Advisor',
    ];

    /**
     * Generate a Global Peer Certificate for the given user.
     */
    public function generate(User $user): FileModel
    {
        // ── 1. Load blank certificate template ──────────────────────────────
        $templatePath = public_path('images/global-peer-certificate.png');

        $baseImg = null;

        if (file_exists($templatePath)) {
            $baseImg = @\imagecreatefrompng($templatePath);
            if (! $baseImg) {
                $baseImg = @\imagecreatefromjpeg($templatePath);
            }
            if (! $baseImg) {
                $baseImg = @\imagecreatefromstring((string) file_get_contents($templatePath));
            }
        }

        if (! $baseImg) {
            throw new \RuntimeException(
                'Global Peer Certificate template not found at public/images/global-peer-certificate.png.'
            );
        }

        $width = \imagesx($baseImg);
        $height = \imagesy($baseImg);

        // ── 2. Create high-quality working canvas ───────────────────────────
        $canvas = \imagecreatetruecolor($width, $height);
        \imagealphablending($canvas, true);
        \imagesavealpha($canvas, true);
        \imagecopy($canvas, $baseImg, 0, 0, 0, 0, $width, $height);
        \imagedestroy($baseImg);

        // ── 3. Draw peer's name on the blank space ─────────────────────────
        $displayName = mb_strtoupper(trim($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($displayName === '') {
            $displayName = 'GLOBAL PEER';
        }

        $this->drawCertificateName($canvas, $displayName, $width, $height);

        // ── 3b. Draw peer's Member ID on the top-right ──────────────────────
        $this->drawMemberId($canvas, $user, $width, $height);

        // ── 4. Save to public disk ───────────────────────────────────────────
        $diskName = 'public';
        $folder = 'uploads/certificates/'.now()->format('Y/m/d');

        if (! Storage::disk($diskName)->exists($folder)) {
            Storage::disk($diskName)->makeDirectory($folder);
        }

        $fileName = Str::uuid().'.jpg';
        $relativeFilePath = $folder.'/'.$fileName;
        $absolutePath = Storage::disk($diskName)->path($relativeFilePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'gpc');
        \imagejpeg($canvas, $tempPath, 95);
        \imagedestroy($canvas);

        rename($tempPath, $absolutePath);

        // ── 5. Create FileModel record ────────────────────────────────────────
        $fileModel = FileModel::create([
            'uploader_user_id' => $user->id,
            's3_key' => $relativeFilePath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => filesize($absolutePath),
            'width' => $width,
            'height' => $height,
        ]);

        Log::info("GlobalPeerCertificateImageGenerator: Generated certificate {$fileModel->id} for user {$user->id}");

        return $fileModel;
    }

    /**
     * Overlay the peer's name centered precisely in the blank name area.
     *
     * Template layout:
     * - "This is to certify that" ends around Y = 305 px
     * - Gold divider ornament line is at Y = 435 px
     * - Center of blank name area: Y = 365 px (0.356 * height)
     */
    private function drawCertificateName($canvas, string $displayName, int $width, int $height): void
    {
        $fontPath = $this->getFontPath('semibold');
        $centerX = (int) ($width / 2);

        // Name area vertical center (35.6% of template height)
        $nameAreaCenterY = (int) ($height * 0.356);

        // Deep Gold (#C5860A) matching the certificate's decorative accents
        $nameColor = \imagecolorallocate($canvas, 197, 134, 10);

        // Maximum allowable width for name text
        $maxWidth = 500;
        $fontSize = 40; // Balanced font size
        $minFontSize = 20;

        // Auto-shrink font if long name exceeds maximum width
        while ($fontSize >= $minFontSize) {
            $bbox = @\imagettfbbox($fontSize, 0, $fontPath, $displayName);
            if (! $bbox) {
                break;
            }
            if (abs($bbox[4] - $bbox[0]) <= $maxWidth) {
                break;
            }
            $fontSize -= 2;
        }

        $bbox = @\imagettfbbox($fontSize, 0, $fontPath, $displayName);
        if (! $bbox) {
            \imagestring($canvas, 5, $centerX - 50, $nameAreaCenterY, $displayName, $nameColor);

            return;
        }

        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);

        $x = $centerX - (int) ($textWidth / 2);
        $y = $nameAreaCenterY + (int) ($textHeight / 2);

        // Crisp rendering with subtle 1px stroke
        \imagettftext($canvas, $fontSize, 0, $x - 1, $y, $nameColor, $fontPath, $displayName);
        \imagettftext($canvas, $fontSize, 0, $x + 1, $y, $nameColor, $fontPath, $displayName);
        \imagettftext($canvas, $fontSize, 0, $x, $y - 1, $nameColor, $fontPath, $displayName);
        \imagettftext($canvas, $fontSize, 0, $x, $y + 1, $nameColor, $fontPath, $displayName);
        \imagettftext($canvas, $fontSize, 0, $x, $y, $nameColor, $fontPath, $displayName);
    }

    /**
     * Draw the user's member ID on the top-right corner, covering the placeholder pg2025xxxx.
     */
    private function drawMemberId($canvas, User $user, int $width, int $height): void
    {
        $memberId = trim((string) ($user->peer_id ?? ''));
        if ($memberId === '') {
            return;
        }

        $white = \imagecolorallocate($canvas, 255, 255, 255);
        $blue = \imagecolorallocate($canvas, 31, 88, 163); // Matching theme blue

        // 1. Cover the pg2025xxxx placeholder
        \imagefilledrectangle($canvas, 510, 60, 650, 80, $white);

        // 2. Load Montserrat-Regular font
        $fontPath = $this->getFontPath('regular');

        $fontSize = 11;
        $bbox = @\imagettfbbox($fontSize, 0, $fontPath, $memberId);
        if (! $bbox) {
            // Fallback to standard GD text if font is not found
            \imagestring($canvas, 2, 550, 68, $memberId, $blue);

            return;
        }

        $textWidth = abs($bbox[2] - $bbox[0]);
        // Center the text precisely around X = 580
        $x = (int) (580 - ($textWidth / 2));
        $y = 77;

        \imagettftext($canvas, $fontSize, 0, $x, $y, $blue, $fontPath, $memberId);
    }
}
