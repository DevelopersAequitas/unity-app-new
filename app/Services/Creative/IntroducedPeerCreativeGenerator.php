<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\CircleCategoryLevel4;
use App\Models\City;
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

class IntroducedPeerCreativeGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Get all 12 Track 1 Growth Honour level definitions.
     *
     * @return array<int, array{title: string, required_count: int, compliment: string, caption_template: string, hashtag: string}>
     */
    public function getAllHonours(): array
    {
        return [
            1 => [
                'title' => 'CONNECTOR',
                'required_count' => 1,
                'compliment' => 'Every movement begins with one connection.',
                'caption_template' => 'Congratulations to {name}, {company}, on being recognised as a Peers Global CONNECTOR. Proud to have you contributing to the Peers Global mission of impacting 1 Million Entrepreneurs.',
                'hashtag' => '#Connector',
                'badge_image' => 'images/member_introduce_badges/Connector.png',
            ],
            3 => [
                'title' => 'CATALYST',
                'required_count' => 3,
                'compliment' => '3 entrepreneurs introduced. 3 new connections. And the beginning of something bigger.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global CATALYST. 3 entrepreneurs introduced. 3 new connections. And the beginning of something bigger. Your contribution is helping build a stronger entrepreneurial community.',
                'hashtag' => '#Catalyst',
                'badge_image' => 'images/member_introduce_badges/Catalyst.png',
            ],
            5 => [
                'title' => 'INFLUENCER',
                'required_count' => 5,
                'compliment' => 'When people trust your recommendation, your influence can create real opportunities for others.',
                'caption_template' => 'Congratulations to {name}, {company}, on being recognised as a Peers Global INFLUENCER. When people trust your recommendation, your influence can create real opportunities for others. Thank you for using your influence to grow the Peers Global community.',
                'hashtag' => '#Influencer',
                'badge_image' => 'images/member_introduce_badges/Influencer.png',
            ],
            10 => [
                'title' => 'AMBASSADOR',
                'required_count' => 10,
                'compliment' => 'You are carrying the Peers Global spirit wherever you go.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global AMBASSADOR. 10 entrepreneurs introduced. One strong contribution to a much bigger mission. You are helping take the Peers Global spirit to more entrepreneurs and more opportunities.',
                'hashtag' => '#Ambassador',
                'badge_image' => 'images/member_introduce_badges/Ambassador.png',
            ],
            20 => [
                'title' => 'RAINMAKER',
                'required_count' => 20,
                'compliment' => 'You don\'t wait for opportunities. You help create them for others.',
                'caption_template' => 'Congratulations to {name}, {company}, on earning the Peers Global RAINMAKER honour. 20 entrepreneurs introduced. You don\'t wait for opportunities. You help create them for others. Your contribution is making the community stronger, one connection at a time.',
                'hashtag' => '#Rainmaker',
                'badge_image' => 'images/member_introduce_badges/Rainmaker.png',
            ],
            35 => [
                'title' => 'TRAILBLAZER',
                'required_count' => 35,
                'compliment' => 'You went first, created the path and brought others along.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global TRAILBLAZER. 35 entrepreneurs introduced. You went first, created the path and brought others along. This is what leadership through contribution looks like.',
                'hashtag' => '#Trailblazer',
                'badge_image' => 'images/member_introduce_badges/Trailblazer.png',
            ],
            50 => [
                'title' => 'VANGUARD',
                'required_count' => 50,
                'compliment' => 'You are leading from the front and building a stronger entrepreneurial community.',
                'caption_template' => 'Congratulations to {name}, {company}, on earning the Peers Global VANGUARD honour. 50 entrepreneurs introduced. Your contribution is helping shape a stronger entrepreneurial community. Build the community you want to belong to.',
                'hashtag' => '#Vanguard',
                'badge_image' => 'images/member_introduce_badges/Vanguard.png',
            ],
            75 => [
                'title' => 'LUMINARY',
                'required_count' => 75,
                'compliment' => 'Your name is becoming a reference point for entrepreneurs in your city.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global LUMINARY. 75 entrepreneurs introduced. Your name is becoming a reference point for entrepreneurs in your city. Let your influence light the way for others.',
                'hashtag' => '#Luminary',
                'badge_image' => 'images/member_introduce_badges/Luminary.png',
            ],
            100 => [
                'title' => 'MOVEMENT MAKER',
                'required_count' => 100,
                'compliment' => 'One connection can become a movement.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global MOVEMENT MAKER. 100 entrepreneurs introduced. You have moved beyond networking. You are helping build a movement of entrepreneurs who believe in growing by helping others grow.',
                'hashtag' => '#MovementMaker',
                'badge_image' => 'images/member_introduce_badges/Movement Maker.png',
            ],
            150 => [
                'title' => 'COMMUNITY TITAN',
                'required_count' => 150,
                'compliment' => 'Your contribution has helped shape the community we are building together.',
                'caption_template' => 'Congratulations to {name}, {company}, on earning the Peers Global COMMUNITY TITAN honour. 150 entrepreneurs introduced. Great communities are built by people who contribute.',
                'hashtag' => '#CommunityTitan',
                'badge_image' => 'images/member_introduce_badges/Community Titan.png',
            ],
            250 => [
                'title' => 'NETWORK ARCHITECT',
                'required_count' => 250,
                'compliment' => 'You are not simply growing the network. You are helping build its foundation for the future.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global NETWORK ARCHITECT. 250 entrepreneurs introduced. Your contribution will continue to create connections long after the introduction is made.',
                'hashtag' => '#NetworkArchitect',
                'badge_image' => 'images/member_introduce_badges/Network Architect.png',
            ],
            500 => [
                'title' => 'GLOBAL ICON',
                'required_count' => 500,
                'compliment' => 'Your contribution has helped carry the Peers Global vision across the entrepreneurial world.',
                'caption_template' => 'Congratulations to {name}, {company}, on becoming a Peers Global GLOBAL ICON. 500 entrepreneurs introduced. This is more than a recognition. It is a legacy of contribution.',
                'hashtag' => '#GlobalIcon',
                'badge_image' => 'images/member_introduce_badges/Global Icon.png',
            ],
        ];
    }

    /**
     * Get Growth Honour metadata based on introduced count.
     *
     * @return array{title: string, required_count: int, compliment: string, caption_template: string, hashtag: string}
     */
    public function getHonourMeta(int $introducedCount): array
    {
        $levels = $this->getAllHonours();

        $selected = $levels[1];
        foreach ($levels as $threshold => $meta) {
            if ($introducedCount >= $threshold) {
                $selected = $meta;
            }
        }

        return $selected;
    }

    /**
     * Generate social caption text for an introducer.
     */
    public function formatCaption(User $user, int $introducedCount): string
    {
        $meta = $this->getHonourMeta($introducedCount);
        $name = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if (empty($name)) {
            $name = 'Peer Member';
        }

        $company = $user->company_name ?? $user->company ?? $user->business_name ?? 'Peers Global';
        if (in_array(strtolower(trim((string) $company)), ['', 'none', 'null', 'no company'], true)) {
            $company = 'Peers Global';
        }

        $captionTemplate = $meta['caption_template'];
        // Replace static milestone count with actual introduced count
        $captionTemplate = preg_replace('/\b\d+\s+entrepreneurs introduced\b/i', $introducedCount.' entrepreneurs introduced', $captionTemplate);

        $text = str_replace(
            ['{name}', '{company}', '{count}'],
            [$name, $company, (string) $introducedCount],
            $captionTemplate
        );

        return $text."\n\n#PeersGlobal {$meta['hashtag']} #CommunityOfCollaboration #1MillionEntrepreneurs";
    }

    /**
     * Generate the Growth Honour / Introduced Peer Creative image.
     */
    public function generate(User $user, int $introducedCount = 0, ?FileModel $targetFileRecord = null): FileModel
    {
        try {
            if ($introducedCount <= 0) {
                $introducedCount = (int) ($user->members_introduced_count ?? 0);
                if ($introducedCount === 0) {
                    $introducedCount = User::query()->where('introduced_by', $user->id)->count();
                }
            }
            if ($introducedCount === 0) {
                $introducedCount = 1;
            }

            $meta = $this->getHonourMeta($introducedCount);

            $templatePath = ! empty($meta['badge_image']) ? public_path($meta['badge_image']) : null;
            if (! $templatePath || ! file_exists($templatePath)) {
                $storageTemplate = ! empty($meta['badge_image']) ? storage_path('app/public/'.$meta['badge_image']) : null;
                if ($storageTemplate && file_exists($storageTemplate)) {
                    $templatePath = $storageTemplate;
                }
            }
            $isCanvaTemplate = $templatePath && file_exists($templatePath);

            // Fonts
            $fontBold = public_path('fonts/Montserrat-Bold.ttf');
            $fontExtraBold = public_path('fonts/Montserrat-ExtraBold.ttf');
            if (! file_exists($fontExtraBold)) {
                $fontExtraBold = $fontBold;
            }
            $fontSemiBold = public_path('fonts/Montserrat-SemiBold.ttf');
            $fontRegular = public_path('fonts/Montserrat-Regular.ttf');

            // Member Info Preparation
            $name = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if (empty($name)) {
                $name = $user->name ?: 'Peer Member';
            }

            $company = $user->company_name ?? $user->company ?? $user->business_name ?? '';
            $cityModel = $user->relationLoaded('city') ? $user->getRelation('city') : ($user->cityRelation ?? null);
            $cityName = $cityModel->name ?? $user->city ?? '';
            if (is_array($cityName)) {
                $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
            }

            $subInfoParts = array_filter([$company, $cityName]);
            $subInfoLine = implode('  •  ', $subInfoParts);
            if (empty($subInfoLine)) {
                $subInfoLine = 'Peers Global Member';
            }

            if ($isCanvaTemplate) {
                // Load high-resolution Canva graphic template
                $canvas = imagecreatefrompng($templatePath);
                if (function_exists('imagepalettetotruecolor')) {
                    imagepalettetotruecolor($canvas);
                }
                $width = imagesx($canvas);
                $height = imagesy($canvas);
                imagealphablending($canvas, true);

                // Colors for white Canva template (Exact Specification)
                $colorGold = imagecolorallocate($canvas, 212, 136, 6);   // #D48806
                $colorDarkNavy = imagecolorallocate($canvas, 30, 41, 59); // #1E293B
                $colorGray = imagecolorallocate($canvas, 100, 116, 139); // #64748B
                $darkCircleBg = imagecolorallocate($canvas, 10, 37, 64); // #0A2540 Dark Blue

                // 1. Profile Avatar: Diameter = 305px, Center X = 538px, Center Y = 513px
                $targetDiameter = 305;
                $circleCenterX = 538;
                $circleCenterY = 513;

                $this->drawAvatarOrInitial($canvas, $user, $circleCenterX, $circleCenterY, $targetDiameter, $darkCircleBg);

                // Helper to draw center-aligned text at precise baseline Y with auto-scaling
                $drawCenterText = function ($img, int $fontSize, int $y, $color, string $font, string $text, int $maxWidth = 920) use ($width) {
                    if (empty($text)) {
                        return;
                    }
                    $size = $fontSize;
                    $bbox = @imagettfbbox($size, 0, $font, $text);
                    while ($bbox && abs($bbox[4] - $bbox[0]) > $maxWidth && $size > 12) {
                        $size -= 1;
                        $bbox = @imagettfbbox($size, 0, $font, $text);
                    }
                    if ($bbox) {
                        $textWidth = abs($bbox[4] - $bbox[0]);
                        $x = ($width - $textWidth) / 2;
                        imagettftext($img, $size, 0, (int) $x, $y, $color, $font, $text);
                    }
                };

                // 2. Line 1: User Full Name (Uppercase, Gold, Bold, Y = 735)
                $displayName = strtoupper(trim($name ?: 'PEER MEMBER'));
                $drawCenterText($canvas, 30, 735, $colorGold, $fontBold, $displayName, 900);

                // 3. Line 2: Business & Location Row (Dark Charcoal Slate, Medium, Y = 766)
                $company = $user->company_name ?? $user->company ?? $user->business_name ?? '';
                if (is_array($company)) {
                    $company = $company['name'] ?? '';
                }
                $company = trim((string) $company);
                if (in_array(strtolower($company), ['null', 'none', 'no company'], true)) {
                    $company = '';
                }

                $cityModel = $user->relationLoaded('city') ? $user->getRelation('city') : ($user->cityRelation ?? null);
                if (! $cityModel && ! empty($user->city_id)) {
                    $cityModel = City::find($user->city_id);
                }
                $cityName = $cityModel->name ?? $user->city ?? $user->business_city ?? '';
                if (is_array($cityName)) {
                    $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
                }
                $cityName = trim((string) $cityName);

                $subInfoParts = array_filter([$company, $cityName]);
                $line2Text = implode('  •  ', $subInfoParts);
                if (empty($line2Text)) {
                    $line2Text = 'Peers Global Member';
                }
                $country = $cityModel->country_code ?? $cityModel->country ?? $user->country ?? $user->business_country ?? 'IND';
                if (is_array($country)) {
                    $country = $country['code'] ?? $country['name'] ?? 'IND';
                }
                $country = strtoupper(trim((string) $country));
                if (in_array(strtolower($country), ['india', 'in', 'ind'], true)) {
                    $country = 'IND';
                } elseif (empty($country) || in_array(strtolower($country), ['null', 'none'], true)) {
                    $country = 'IND';
                }

                $locationParts = [];
                if (! empty($cityName)) {
                    $locationParts[] = $cityName;
                }
                if (! empty($country) && strtolower($country) !== strtolower($cityName)) {
                    $locationParts[] = $country;
                }
                $locationStr = implode(', ', $locationParts);

                $line2Parts = [];
                if (! empty($company)) {
                    $line2Parts[] = $company;
                }
                if (! empty($locationStr)) {
                    $line2Parts[] = $locationStr;
                }
                $line2Text = implode(' • ', $line2Parts);

                if (! empty($line2Text)) {
                    $drawCenterText($canvas, 19, 766, $colorDarkNavy, $fontSemiBold, $line2Text, 920);
                }

                // 4. Line 3: Level 4 Category / Subcategory (Slate Gray, Medium, Y = 794)
                $level4Name = '';
                if ($user->relationLoaded('level4Category')) {
                    $level4Name = $user->getRelation('level4Category')?->name ?? '';
                } elseif (! empty($user->business_category_id)) {
                    $level4Name = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
                }

                if (empty($level4Name)) {
                    $level4Name = $user->business_sub_category ?? $user->category_name ?? $user->designation ?? $user->job_title ?? '';
                }
                if (empty($level4Name) && isset($user->businessCategory)) {
                    $level4Name = $user->businessCategory->name ?? '';
                }
                if (empty($level4Name) && isset($user->category)) {
                    $level4Name = $user->category->name ?? '';
                }
                if (is_array($level4Name)) {
                    $level4Name = $level4Name['name'] ?? $level4Name['label'] ?? '';
                }
                $level4Name = trim((string) $level4Name);
                if (empty($level4Name) || in_array(strtolower($level4Name), ['null', 'none'], true)) {
                    $level4Name = $user->membership_status ?? 'Peers Global Member';
                }

                if (! empty($level4Name)) {
                    $drawCenterText($canvas, 17, 794, $colorGray, $fontSemiBold, (string) $level4Name, 920);
                }
            } else {
                // Canvas Dimensions (Vertical 1080x1350 fallback)
                $width = 1080;
                $height = 1350;

                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);

                // Dark Navy Background (#070D1A)
                $bgNavy = imagecolorallocate($canvas, 7, 13, 26);
                imagefill($canvas, 0, 0, $bgNavy);

                // Colors matching dark Canva design
                $white = imagecolorallocate($canvas, 255, 255, 255);
                $gold = imagecolorallocate($canvas, 223, 177, 72); // #DFB148 Vibrant Gold
                $subTitleSlate = imagecolorallocate($canvas, 226, 232, 240); // #E2E8F0
                $softSlate = imagecolorallocate($canvas, 203, 213, 225); // #CBD5E1
                $footerGray = imagecolorallocate($canvas, 100, 116, 139); // #64748B
                $darkCircleBg = imagecolorallocate($canvas, 22, 36, 71); // #162447
                $boxBg = imagecolorallocate($canvas, 9, 17, 34); // #091122

                // 1. Top Header: BIG CONGRATULATIONS
                $topY = 125;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    ['BIG CONGRATULATIONS'],
                    26,
                    (int) ($width / 2),
                    $topY,
                    $gold,
                    $fontExtraBold
                );

                // 2. Award Level Title (e.g., CONNECTOR, CATALYST, TRAILBLAZER)
                $titleY = 215;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$meta['title']],
                    52,
                    (int) ($width / 2),
                    $titleY,
                    $white,
                    $fontExtraBold
                );

                // Gold Separator Line with Center Diamond
                $this->drawGoldSeparator($canvas, (int) ($width / 2), 285, $gold);

                // 3. User Avatar / Initial with Gold Border Ring (Center X = 540, Y = 460, Radius = 125)
                $avatarCenterX = 540;
                $avatarCenterY = 460;
                $avatarSize = 250;

                $this->drawAvatarOrInitial($canvas, $user, $avatarCenterX, $avatarCenterY, $avatarSize, $darkCircleBg);

                // 3px Gold Ring around Avatar
                imagesetthickness($canvas, 3);
                imageellipse($canvas, $avatarCenterX, $avatarCenterY, $avatarSize + 4, $avatarSize + 4, $gold);
                imagesetthickness($canvas, 1);

                // 4. Peer Name (e.g. Chirag Mali)
                $nameY = 665;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$name],
                    38,
                    (int) ($width / 2),
                    $nameY,
                    $gold,
                    $fontExtraBold
                );

                // 5. Business Name & City Line (e.g. TaskMate AI  •  Ahmedabad)
                $subInfoY = 735;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$subInfoLine],
                    22,
                    (int) ($width / 2),
                    $subInfoY,
                    $subTitleSlate,
                    $fontSemiBold
                );

                // 6. One-Line Compliment Text
                $complimentY = 815;
                $lines = $this->wrapTextToLines($meta['compliment'], 22, $fontRegular, 900);
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    $lines,
                    22,
                    (int) ($width / 2),
                    $complimentY,
                    $softSlate,
                    $fontRegular
                );

                // 7. Outlined Count Box (Y = 900 to 984, Height = 84px)
                $countText = "{$introducedCount} ".($introducedCount === 1 ? 'Entrepreneur Introduced' : 'Entrepreneurs Introduced').' to Peers Global';
                $boxY = 900;
                $boxHeight = 84;
                $boxWidth = 760;
                $boxX = (int) (($width / 2) - ($boxWidth / 2));

                // Fill Box
                imagefilledrectangle($canvas, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $boxBg);

                // 2px Gold Border Outline
                imagesetthickness($canvas, 2);
                imagerectangle($canvas, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $gold);
                imagesetthickness($canvas, 1);

                // Text inside box
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$countText],
                    24,
                    (int) ($width / 2),
                    $boxY + 28,
                    $white,
                    $fontExtraBold
                );

                // 8. Mission Tagline Section
                $tagline1 = 'EVERY PEER YOU INTRODUCE, IMPACTS MORE LIVES.';
                $tagline2 = 'YOU ARE A 1 MILLION MISSION CONTRIBUTOR.';
                $tagline1Y = 1055;
                $tagline2Y = 1095;

                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$tagline1],
                    17,
                    (int) ($width / 2),
                    $tagline1Y,
                    $gold,
                    $fontExtraBold
                );

                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$tagline2],
                    17,
                    (int) ($width / 2),
                    $tagline2Y,
                    $white,
                    $fontExtraBold
                );

                // 9. Footer Text
                $footerY = 1250;
                $footerText = "PEERS GLOBAL  \u{2022}  World's First Community of Collaboration";
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$footerText],
                    16,
                    (int) ($width / 2),
                    $footerY,
                    $footerGray,
                    $fontSemiBold
                );
            }

            // Save WebP File & Create FileModel
            $filename = 'introduced_creative_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'growth_creative');

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

            if ($targetFileRecord) {
                $finalPath = $targetFileRecord->s3_key;
                $stream = fopen($tempPath, 'r');
                Storage::disk($disk)->put($finalPath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $fileModel = $targetFileRecord;
            } else {
                $fileModel = $this->fileUploadService->store($uploadedFile, auth('admin')->user(), $disk);
            }

            if ($disk !== 'public') {
                try {
                    $fileContent = Storage::disk($disk)->get($fileModel->s3_key);
                    Storage::disk('public')->put($fileModel->s3_key, $fileContent);
                } catch (\Throwable $e) {
                    Log::error('IntroducedPeerCreativeGenerator: Failed to copy creative to public disk: '.$e->getMessage());
                }
            }

            @unlink($tempPath);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error('Failed to generate introduced peer creative: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Draw user avatar or initials fallback inside circle.
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
                    Log::warning('Could not download user avatar: '.$e->getMessage());
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
                Log::warning('Could not process user avatar for introduced creative: '.$e->getMessage());
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
