<?php

declare(strict_types=1);

namespace App\Services\Certifications;

use App\Models\CertificationSubmission;
use App\Models\FileModel;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificationImageGenerator
{
    /**
     * Generate dynamic certificate image for entrepreneur or leadership in HD format (2x scale).
     */
    public function generate(CertificationSubmission $submission): array
    {
        // Get user name from users table display_name
        $user = $submission->user_id
            ? User::find($submission->user_id)
            : User::where('email', $submission->email)->first();

        $displayName = $user?->display_name ?: $submission->full_name;
        if (empty($displayName)) {
            $displayName = $submission->full_name;
        }

        $type = $submission->certification_type; // 'entrepreneur' or 'leadership'

        if ($type === CertificationSubmission::TYPE_ENTREPRENEUR) {
            $templatePath = public_path('images/entrepreneur-blank.png');
            $nameY = 820; // 410 * 2
            $dateY = 1330; // 665 * 2
        } else {
            $templatePath = public_path('images/leadership-blank.png');
            $nameY = 1050; // 525 * 2
            $dateY = 1480; // 740 * 2
        }

        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template image not found: {$templatePath}");
        }

        $baseImg = @imagecreatefrompng($templatePath);
        if (! $baseImg) {
            throw new \RuntimeException('Failed to load template image.');
        }

        $origW = imagesx($baseImg);
        $origH = imagesy($baseImg);

        // Upscale 2x for HD format
        $hdW = $origW * 2;
        $hdH = $origH * 2;
        $canvas = imagecreatetruecolor($hdW, $hdH);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        // Resize template to 2x canvas
        imagecopyresampled($canvas, $baseImg, 0, 0, 0, 0, $hdW, $hdH, $origW, $origH);
        imagedestroy($baseImg);

        $fontName = public_path('fonts/PinyonScript-Regular.ttf');
        $fontDate = public_path('fonts/Montserrat-Regular.ttf');

        // Color definitions
        $goldColor = imagecolorallocate($canvas, 182, 125, 33); // #b67d21
        $blackColor = imagecolorallocate($canvas, 0, 0, 0);

        // Render name (2x scale)
        $name = trim($displayName);
        $fontSizeName = 140; // 70 * 2
        $minFontSize = 60;   // 30 * 2

        // Auto-shrink font name if it exceeds a width of 1100px (550px * 2)
        $maxWidth = 1100;
        while ($fontSizeName >= $minFontSize) {
            $bbox = @imagettfbbox($fontSizeName, 0, $fontName, $name);
            if (! $bbox) {
                break;
            }
            $w = abs($bbox[4] - $bbox[0]);
            if ($w <= $maxWidth) {
                break;
            }
            $fontSizeName -= 8;
        }

        $bbox = @imagettfbbox($fontSizeName, 0, $fontName, $name);
        if ($bbox) {
            $w = abs($bbox[4] - $bbox[0]);
            $x = (int) ($hdW / 2 - $w / 2);
            imagettftext($canvas, $fontSizeName, 0, $x, $nameY, $goldColor, $fontName, $name);
        } else {
            imagestring($canvas, 5, (int) ($hdW / 2 - 100), $nameY, $name, $goldColor);
        }

        // Render date (2x scale)
        $dateObj = $submission->approved_at ?: now();
        $dateText = $dateObj->format('d F Y');

        $fontSizeDate = 26; // 13 * 2
        $bboxDate = @imagettfbbox($fontSizeDate, 0, $fontDate, $dateText);
        if ($bboxDate) {
            $wDate = abs($bboxDate[4] - $bboxDate[0]);
            $xDate = (int) ($hdW / 2 - $wDate / 2);
            imagettftext($canvas, $fontSizeDate, 0, $xDate, $dateY, $blackColor, $fontDate, $dateText);
        } else {
            imagestring($canvas, 4, (int) ($hdW / 2 - 80), $dateY, $dateText, $blackColor);
        }

        // Save to storage
        $diskName = 'public';
        $folder = 'uploads/certificates/'.now()->format('Y/m/d');

        if (! Storage::disk($diskName)->exists($folder)) {
            Storage::disk($diskName)->makeDirectory($folder);
        }

        $fileName = Str::uuid().'.png';
        $relativeFilePath = $folder.'/'.$fileName;
        $absolutePath = Storage::disk($diskName)->path($relativeFilePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        imagepng($canvas, $absolutePath);
        imagedestroy($canvas);

        // Register in FileModel
        $fileModel = FileModel::create([
            'uploader_user_id' => $user?->id,
            's3_key' => $relativeFilePath,
            'mime_type' => 'image/png',
            'size_bytes' => filesize($absolutePath),
            'width' => $hdW,
            'height' => $hdH,
        ]);

        return [
            'url' => Storage::disk($diskName)->url($relativeFilePath),
            'file_id' => $fileModel->id,
        ];
    }
}
