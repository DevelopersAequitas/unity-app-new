<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\FileModel;
use App\Models\Leadership\LeadershipNomination;
use App\Models\User;
use App\Traits\HasCreativeRendering;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeadershipNominationCreativeService
{
    use HasCreativeRendering;

    /**
     * Generate dynamic official announcement creative for an approved nomination.
     *
     * @return array{file_path: string, relative_path: string, url: string}
     */
    public function generate(LeadershipNomination $nomination): array
    {
        try {
            $nomination->loadMissing(['campaign.role', 'scope', 'user']);
        } catch (\Throwable $e) {
            // Safe fallback if database connection or relations are not reachable
        }

        $templatePath = public_path('images/leadership-nomination-template.png');
        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Leadership nomination template not found at {$templatePath}");
        }

        $canvas = @imagecreatefrompng($templatePath);
        if (! $canvas) {
            throw new \RuntimeException('Failed to load leadership nomination template image.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($canvas);
        }

        $width = imagesx($canvas);
        $height = imagesy($canvas);
        imagealphablending($canvas, true);

        // Fonts
        $fontBold = realpath(public_path('fonts/Montserrat-ExtraBold.ttf')) ?: realpath(public_path('fonts/Montserrat-Bold.ttf'));
        $fontSemiBold = realpath(public_path('fonts/Montserrat-SemiBold.ttf'));
        if (! $fontBold || ! file_exists($fontBold)) {
            $fontBold = public_path('fonts/Montserrat-Bold.ttf');
        }

        // Colors
        $blueGradient = [2, 6, 228];    // Royal Blue #0206E4
        $redGradient = [216, 19, 19];   // Vivid Red #D81313
        $redColor = imagecolorallocate($canvas, 216, 19, 19);
        $blueColor = imagecolorallocate($canvas, 2, 6, 228);
        $nameColor = imagecolorallocate($canvas, 11, 36, 118); // #0B2476 Deep Royal Blue
        $subSlate = imagecolorallocate($canvas, 51, 65, 85);   // #334155 Slate

        // 1. DYNAMIC ROLE TITLE (Center top below "LEADERSHIP OPPORTUNITY" pill)
        $roleName = (string) ($nomination->applied_role_name ?? $nomination->campaign?->role?->name ?? 'LEADERSHIP ROLE');
        $cleanRole = strtoupper(trim((string) preg_replace('/\s*\([^)]*\)/', '', $roleName)));

        $words = explode(' ', $cleanRole);
        $lines = [];
        if (count($words) <= 2 && strlen($cleanRole) <= 15) {
            $lines = [$cleanRole];
        } elseif (count($words) >= 3) {
            $lines[] = implode(' ', array_slice($words, 0, count($words) - 1));
            $lines[] = end($words);
        } else {
            $lines = [$words[0], $words[1]];
        }

        $roleFontSize = 33;
        if (count($lines) === 1) {
            $bbox = imagettfbbox($roleFontSize, 0, $fontBold, $lines[0]);
            $tw = abs($bbox[4] - $bbox[0]);
            while ($tw > 620 && $roleFontSize > 18) {
                $roleFontSize -= 1;
                $bbox = imagettfbbox($roleFontSize, 0, $fontBold, $lines[0]);
                $tw = abs($bbox[4] - $bbox[0]);
            }
            $barLength = 60;
            $totalW = $tw + 20 + $barLength;
            $x = (int) (($width - $totalW) / 2);
            $y = 230;
            $this->drawGradientText($canvas, $lines[0], $fontBold, $roleFontSize, $x, $y, $blueGradient, $redGradient);

            imagesetthickness($canvas, 4);
            imageline($canvas, $x + $tw + 18, $y - 10, $x + $totalW, $y - 10, $redColor);
            imagesetthickness($canvas, 1);
        } else {
            // Line 1
            $bbox1 = imagettfbbox($roleFontSize, 0, $fontBold, $lines[0]);
            $w1 = abs($bbox1[4] - $bbox1[0]);
            while ($w1 > 620 && $roleFontSize > 18) {
                $roleFontSize -= 1;
                $bbox1 = imagettfbbox($roleFontSize, 0, $fontBold, $lines[0]);
                $w1 = abs($bbox1[4] - $bbox1[0]);
            }
            $x1 = (int) (($width - $w1) / 2);
            $this->drawGradientText($canvas, $lines[0], $fontBold, $roleFontSize, $x1, 215, $blueGradient, $redGradient);

            // Line 2 with trailing red bar
            $bbox2 = imagettfbbox($roleFontSize, 0, $fontBold, $lines[1]);
            $w2 = abs($bbox2[4] - $bbox2[0]);
            $barLength = 70;
            $totalLine2W = $w2 + 20 + $barLength;
            $x2 = (int) (($width - $totalLine2W) / 2);
            $this->drawGradientText($canvas, $lines[1], $fontBold, $roleFontSize, $x2, 265, $blueGradient, $redGradient);

            imagesetthickness($canvas, 4);
            imageline($canvas, $x2 + $w2 + 18, 252, $x2 + $totalLine2W, 252, $redColor);
            imagesetthickness($canvas, 1);
        }

        // 2. DYNAMIC CANDIDATE CIRCULAR PHOTO & DUAL-COLOR FRAME (Center X = 409, Center Y = 475)
        $cx = 409;
        $cy = 475;
        $targetDiameter = 320;
        $radius = (int) ($targetDiameter / 2);

        // Draw outer accent arcs (diameter = 360, radius = 180)
        $outerD = 360;
        imagesetthickness($canvas, 2);
        // Left blue accent arc (from 135 deg to 225 deg)
        imagearc($canvas, $cx, $cy, $outerD, $outerD, 135, 225, $blueColor);
        // Right red accent arc (from 315 deg to 45 deg)
        imagearc($canvas, $cx, $cy, $outerD, $outerD, 315, 45, $redColor);
        imagesetthickness($canvas, 1);

        // Prepare candidate avatar image
        $avatarImg = $this->resolveAvatarImage($nomination);

        $circleImg = imagecreatetruecolor($targetDiameter, $targetDiameter);
        imagealphablending($circleImg, false);
        imagesavealpha($circleImg, true);
        $transparent = imagecolorallocatealpha($circleImg, 0, 0, 0, 127);
        imagefill($circleImg, 0, 0, $transparent);

        if ($avatarImg) {
            $srcW = imagesx($avatarImg);
            $srcH = imagesy($avatarImg);
            $minSide = min($srcW, $srcH);
            $srcX = (int) (($srcW - $minSide) / 2);
            $srcY = (int) (($srcH - $minSide) / 2);

            $resized = imagecreatetruecolor($targetDiameter, $targetDiameter);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, $transparent);
            imagecopyresampled($resized, $avatarImg, 0, 0, $srcX, $srcY, $targetDiameter, $targetDiameter, $minSide, $minSide);
            imagedestroy($avatarImg);

            for ($x = 0; $x < $targetDiameter; $x++) {
                for ($y = 0; $y < $targetDiameter; $y++) {
                    $dx = $x - $radius;
                    $dy = $y - $radius;
                    if (($dx * $dx + $dy * $dy) <= ($radius * $radius)) {
                        $color = imagecolorat($resized, $x, $y);
                        imagesetpixel($circleImg, $x, $y, $color);
                    }
                }
            }
            imagedestroy($resized);
        } else {
            // High-trust Navy Initials Badge
            $navyBg = imagecolorallocate($circleImg, 15, 23, 42); // #0F172A
            for ($x = 0; $x < $targetDiameter; $x++) {
                for ($y = 0; $y < $targetDiameter; $y++) {
                    $dx = $x - $radius;
                    $dy = $y - $radius;
                    if (($dx * $dx + $dy * $dy) <= ($radius * $radius)) {
                        imagesetpixel($circleImg, $x, $y, $navyBg);
                    }
                }
            }

            // Subtle inner ring
            $innerRing = imagecolorallocate($circleImg, 30, 41, 59);
            imageellipse($circleImg, $radius, $radius, $targetDiameter - 16, $targetDiameter - 16, $innerRing);

            $candidateName = (string) ($nomination->candidate_name ?: $nomination->full_name ?: 'PG');
            $parts = explode(' ', trim($candidateName));
            $initials = strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
            $white = imagecolorallocate($circleImg, 255, 255, 255);
            $initSize = 64;
            $ibbox = imagettfbbox($initSize, 0, $fontBold, $initials);
            $iw = abs($ibbox[4] - $ibbox[0]);
            $ih = abs($ibbox[5]);
            imagettftext($circleImg, $initSize, 0, (int) ($radius - $iw / 2), (int) ($radius + $ih / 2), $white, $fontBold, $initials);
        }

        // Composite avatar onto clean canvas
        imagealphablending($canvas, true);
        imagecopy($canvas, $circleImg, (int) ($cx - $radius), (int) ($cy - $radius), 0, 0, $targetDiameter, $targetDiameter);
        imagedestroy($circleImg);

        // Draw main dual-color border ring OVER the avatar (clean 4px border)
        $borderD = $targetDiameter + 4;
        imagesetthickness($canvas, 4);
        // Left half (Royal Blue): from 90 deg to 270 deg
        imagearc($canvas, $cx, $cy, $borderD, $borderD, 90, 270, $blueColor);
        // Right half (Vivid Red): from 270 deg to 90 deg
        imagearc($canvas, $cx, $cy, $borderD, $borderD, 270, 90, $redColor);
        imagesetthickness($canvas, 1);

        // 3. DYNAMIC CANDIDATE NAME (Centered below circle, Royal Blue)
        $name = strtoupper(trim((string) ($nomination->candidate_name ?: $nomination->full_name ?: 'LEADERSHIP CANDIDATE')));
        $nameSize = 30;
        $nameBbox = imagettfbbox($nameSize, 0, $fontBold, $name);
        $nameW = abs($nameBbox[4] - $nameBbox[0]);
        while ($nameW > 620 && $nameSize > 18) {
            $nameSize -= 1;
            $nameBbox = imagettfbbox($nameSize, 0, $fontBold, $name);
            $nameW = abs($nameBbox[4] - $nameBbox[0]);
        }
        $nameX = (int) (($width - $nameW) / 2);
        imagettftext($canvas, $nameSize, 0, $nameX, 705, $nameColor, $fontBold, $name);

        // 4. DYNAMIC INDUSTRY • CITY (Flanked with lines and red center dot)
        $industry = $this->resolveIndustry($nomination);
        $city = $this->resolveCity($nomination);

        $subSize = 13;
        $indBbox = imagettfbbox($subSize, 0, $fontBold, $industry);
        $cityBbox = imagettfbbox($subSize, 0, $fontBold, $city);
        $indW = abs($indBbox[4] - $indBbox[0]);
        $cityW = abs($cityBbox[4] - $cityBbox[0]);

        $dotGap = 30;
        $totalTextW = $indW + $dotGap + $cityW;
        while ($totalTextW > 580 && $subSize > 9) {
            $subSize -= 1;
            $indBbox = imagettfbbox($subSize, 0, $fontBold, $industry);
            $cityBbox = imagettfbbox($subSize, 0, $fontBold, $city);
            $indW = abs($indBbox[4] - $indBbox[0]);
            $cityW = abs($cityBbox[4] - $cityBbox[0]);
            $totalTextW = $indW + $dotGap + $cityW;
        }

        $subStartX = (int) (($width - $totalTextW) / 2);
        $subY = 745;
        $lineY = $subY - 5;
        $flankLen = 60;

        // Flanking horizontal lines
        imageline($canvas, $subStartX - $flankLen - 14, $lineY, $subStartX - 14, $lineY, $redColor);
        imageline($canvas, $subStartX + $totalTextW + 14, $lineY, $subStartX + $totalTextW + $flankLen + 14, $lineY, $redColor);

        // Industry text
        imagettftext($canvas, $subSize, 0, $subStartX, $subY, $subSlate, $fontBold, $industry);

        // Center Red Dot
        $dotX = $subStartX + $indW + 15;
        imagefilledellipse($canvas, $dotX, $lineY, 8, 8, $redColor);

        // City text
        $cityStartX = $dotX + 15;
        imagettftext($canvas, $subSize, 0, $cityStartX, $subY, $subSlate, $fontBold, $city);

        // 5. SAVE AND PERSIST CREATIVE
        $disk = config('filesystems.default', 'public');
        $relativePath = "creatives/leadership/nomination_{$nomination->id}.png";

        $tempPath = tempnam(sys_get_temp_dir(), 'lead_creative_');
        if ($tempPath === false) {
            $tempPath = storage_path('framework/cache/'.(string) Str::uuid().'.tmp');
        }

        imagepng($canvas, $tempPath, 9);
        imagedestroy($canvas);

        $stream = fopen($tempPath, 'r');
        Storage::disk($disk)->put($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($disk !== 'public') {
            try {
                $fileContent = Storage::disk($disk)->get($relativePath);
                Storage::disk('public')->put($relativePath, $fileContent);
            } catch (\Throwable $e) {
                Log::warning('Failed replicating creative to public disk: '.$e->getMessage());
            }
        }

        // Also ensure copy exists in public/storage
        $publicStoragePath = public_path('storage/'.$relativePath);
        $publicDir = dirname($publicStoragePath);
        if (! is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        @copy($tempPath, $publicStoragePath);

        $finalUrl = Storage::disk('public')->url($relativePath);
        if (! str_starts_with($finalUrl, 'http://') && ! str_starts_with($finalUrl, 'https://')) {
            $appUrl = rtrim((string) config('app.url', 'https://peersunity.com'), '/');
            $finalUrl = $appUrl.'/'.ltrim($finalUrl, '/');
        }

        // Persist on nomination profile snapshot
        try {
            $snapshot = is_array($nomination->profile_snapshot) ? $nomination->profile_snapshot : [];
            $snapshot['creative_url'] = $finalUrl;
            $snapshot['creative_path'] = $relativePath;
            if ($nomination->exists) {
                $nomination->update(['profile_snapshot' => $snapshot]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not persist creative_url to nomination record: '.$e->getMessage());
        }

        @unlink($tempPath);

        return [
            'file_path' => $publicStoragePath,
            'relative_path' => $relativePath,
            'url' => $finalUrl,
        ];
    }

    /**
     * Draw text with a smooth horizontal linear gradient.
     *
     * @param  resource|\GdImage  $canvas
     * @param  array<int, int>  $startRgb
     * @param  array<int, int>  $endRgb
     */
    protected function drawGradientText(
        $canvas,
        string $text,
        string $fontPath,
        int $fontSize,
        int $x,
        int $y,
        array $startRgb,
        array $endRgb
    ): int {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textW = abs($bbox[4] - $bbox[0]);
        $textH = abs($bbox[5] - $bbox[1]);
        $offsetY = abs($bbox[5]);

        $textImg = imagecreatetruecolor($textW + 10, $textH + 10);
        imagealphablending($textImg, false);
        imagesavealpha($textImg, true);
        $transparent = imagecolorallocatealpha($textImg, 0, 0, 0, 127);
        imagefill($textImg, 0, 0, $transparent);

        $white = imagecolorallocate($textImg, 255, 255, 255);
        imagettftext($textImg, $fontSize, 0, 0, $offsetY, $white, $fontPath, $text);

        for ($px = 0; $px < $textW + 10; $px++) {
            $ratio = min(1.0, max(0.0, $px / max(1, $textW)));
            $r = (int) ($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $ratio);
            $g = (int) ($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $ratio);
            $b = (int) ($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $ratio);

            for ($py = 0; $py < $textH + 10; $py++) {
                $colorAt = imagecolorat($textImg, $px, $py);
                $alpha = ($colorAt >> 24) & 0x7F;
                if ($alpha < 127) {
                    $gradColor = imagecolorallocatealpha($textImg, $r, $g, $b, $alpha);
                    imagesetpixel($textImg, $px, $py, $gradColor);
                }
            }
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $textImg, $x, $y - $offsetY, 0, 0, $textW + 10, $textH + 10);
        imagedestroy($textImg);

        return $textW;
    }

    /**
     * Resolve candidate avatar GD image resource from nomination snapshot, documents, or Unity user.
     *
     * @return resource|\GdImage|null
     */
    protected function resolveAvatarImage(LeadershipNomination $nomination)
    {
        // 1. Gather all potential photo locations in priority order
        $candidates = [];

        // Direct photo_url attribute
        if (! empty($nomination->photo_url)) {
            $candidates[] = $nomination->photo_url;
        }

        $snapshot = $nomination->profile_snapshot;
        if (is_array($snapshot)) {
            if (isset($snapshot['profile']) && is_array($snapshot['profile'])) {
                foreach (['photo_url', 'photo', 'avatar_url', 'image_url', 'avatar'] as $key) {
                    if (! empty($snapshot['profile'][$key]) && is_string($snapshot['profile'][$key])) {
                        $candidates[] = $snapshot['profile'][$key];
                    }
                }
            }
            foreach (['photo_url', 'profile_photo_url', 'avatar_url', 'image_url', 'profile_photo', 'photo', 'avatar'] as $key) {
                if (! empty($snapshot[$key]) && is_string($snapshot[$key])) {
                    $candidates[] = $snapshot[$key];
                }
            }
        }

        if (is_array($nomination->edited_profile)) {
            foreach (['photo_url', 'profile_photo_url', 'avatar_url', 'image_url'] as $key) {
                if (! empty($nomination->edited_profile[$key]) && is_string($nomination->edited_profile[$key])) {
                    $candidates[] = $nomination->edited_profile[$key];
                }
            }
        }

        // Check documents for photo/id proof
        try {
            $docs = $nomination->relationLoaded('documents') ? $nomination->documents : $nomination->documents()->get();
            if ($docs && $docs->isNotEmpty()) {
                foreach ($docs as $doc) {
                    $type = strtolower((string) ($doc->document_type ?? ''));
                    if (str_contains($type, 'photo') || str_contains($type, 'avatar') || str_contains($type, 'image') || str_contains($type, 'proof')) {
                        if (! empty($doc->file_url)) {
                            $candidates[] = $doc->file_url;
                        }
                        if (! empty($doc->file_path)) {
                            $candidates[] = $doc->file_path;
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        // Check connected user
        if ($nomination->user) {
            if (! empty($nomination->user->profile_photo_url)) {
                $candidates[] = $nomination->user->profile_photo_url;
            }
            if (! empty($nomination->user->avatar_file_id)) {
                $file = FileModel::find($nomination->user->avatar_file_id);
                if ($file && $file->s3_key) {
                    $candidates[] = $file->s3_key;
                }
            }
        }

        // 2. Iterate and attempt to load the first valid image resource
        foreach ($candidates as $candidate) {
            if (empty($candidate) || ! is_string($candidate)) {
                continue;
            }

            // Case A: Full URL
            if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
                // If it points to local storage path, check disk first
                $pathPart = parse_url($candidate, PHP_URL_PATH);
                if ($pathPart && str_contains($pathPart, '/storage/')) {
                    $relStorage = preg_replace('#^.*?/storage/#', '', $pathPart);
                    $localCheck = public_path('storage/'.$relStorage);
                    if (file_exists($localCheck)) {
                        $img = @imagecreatefromstring((string) file_get_contents($localCheck));
                        if ($img) {
                            return $img;
                        }
                    }
                }

                // Remote download with timeout and SSL fallback
                try {
                    $resp = Http::timeout(8)->withoutVerifying()->get($candidate);
                    if ($resp->successful() && ! empty($resp->body())) {
                        $img = @imagecreatefromstring($resp->body());
                        if ($img) {
                            return $img;
                        }
                    }
                } catch (\Throwable $e) {
                    // Try stream context with disabled peer verification
                    try {
                        $ctx = stream_context_create([
                            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                            'http' => ['timeout' => 8],
                        ]);
                        $data = @file_get_contents($candidate, false, $ctx);
                        if ($data) {
                            $img = @imagecreatefromstring($data);
                            if ($img) {
                                return $img;
                            }
                        }
                    } catch (\Throwable) {
                    }
                }
            }

            // Case B: Local absolute file path
            if (file_exists($candidate)) {
                $img = @imagecreatefromstring((string) file_get_contents($candidate));
                if ($img) {
                    return $img;
                }
            }

            // Case C: Relative storage/public path
            $rel = ltrim($candidate, '/');
            $rel = preg_replace('#^(storage/|public/)#', '', $rel);

            $pathsToCheck = [
                public_path('storage/'.$rel),
                public_path($rel),
                storage_path('app/public/'.$rel),
                storage_path('app/'.$rel),
            ];

            foreach ($pathsToCheck as $check) {
                if (file_exists($check)) {
                    $img = @imagecreatefromstring((string) file_get_contents($check));
                    if ($img) {
                        return $img;
                    }
                }
            }

            // Case D: Storage disk check
            try {
                if (Storage::disk('public')->exists($rel)) {
                    $data = Storage::disk('public')->get($rel);
                    if ($data) {
                        $img = @imagecreatefromstring($data);
                        if ($img) {
                            return $img;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Resolve formatted industry name.
     */
    protected function resolveIndustry(LeadershipNomination $nomination): string
    {
        $snapshot = $nomination->profile_snapshot;
        if (is_array($snapshot)) {
            $val = $snapshot['industry']
                ?? $snapshot['industry_name']
                ?? $snapshot['business_industry']
                ?? $snapshot['company_name']
                ?? null;

            if (! empty($val) && is_string($val)) {
                return strtoupper(trim($val));
            }
        }

        if (! empty($nomination->company)) {
            return strtoupper(trim($nomination->company));
        }

        return 'BUSINESS & INDUSTRY';
    }

    /**
     * Resolve formatted city name.
     */
    protected function resolveCity(LeadershipNomination $nomination): string
    {
        $snapshot = $nomination->profile_snapshot;
        if (is_array($snapshot)) {
            $val = $snapshot['city_name']
                ?? $snapshot['city']
                ?? $snapshot['belonging_name']
                ?? null;

            if (! empty($val) && is_string($val)) {
                return strtoupper(trim($val));
            }
        }

        if (! empty($nomination->scope_name)) {
            return strtoupper(trim($nomination->scope_name));
        }

        return 'AHMEDABAD';
    }
}
