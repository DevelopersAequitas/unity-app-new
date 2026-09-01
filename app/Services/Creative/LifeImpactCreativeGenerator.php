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

class LifeImpactCreativeGenerator
{
    use HasCreativeRendering;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Get all 12 Life Impact Recognition level definitions (Canva Pages 14-25).
     *
     * @return array<int, array{title: string, required_count: int, compliment: string, caption_template: string, hashtag: string, badge_image: string, quote: string}>
     */
    public function getAllRecognitionLevels(): array
    {
        return [
            25 => [
                'title' => 'IMPACT CREATOR',
                'required_count' => 25,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **IMPACT CREATOR** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #ImpactCreator #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'ImpactCreator',
                'badge_image' => 'images/life_impact_badges/Impact Creator.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            50 => [
                'title' => 'CHANGE MAKER',
                'required_count' => 50,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **CHANGE MAKER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #ChangeMaker #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'ChangeMaker',
                'badge_image' => 'images/life_impact_badges/Change Maker.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            100 => [
                'title' => 'LIFE CHANGER',
                'required_count' => 100,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **LIFE CHANGER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #LifeChanger #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'LifeChanger',
                'badge_image' => 'images/life_impact_badges/Life Changer.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            250 => [
                'title' => 'IMPACT BUILDER',
                'required_count' => 250,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **IMPACT BUILDER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #ImpactBuilder #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'ImpactBuilder',
                'badge_image' => 'images/life_impact_badges/Impact Builder.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            500 => [
                'title' => 'ECOSYSTEM BUILDER',
                'required_count' => 500,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **ECOSYSTEM BUILDER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #EcosystemBuilder #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'EcosystemBuilder',
                'badge_image' => 'images/life_impact_badges/Ecosystem Builder.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            1000 => [
                'title' => 'IMPACT ARCHITECT',
                'required_count' => 1000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **IMPACT ARCHITECT** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #ImpactArchitect #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'ImpactArchitect',
                'badge_image' => 'images/life_impact_badges/Impact Architect.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            2500 => [
                'title' => 'LEGACY MAKER',
                'required_count' => 2500,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **LEGACY MAKER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #LegacyMaker #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'LegacyMaker',
                'badge_image' => 'images/life_impact_badges/Legacy Maker.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            5000 => [
                'title' => 'TORCHBEARER',
                'required_count' => 5000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **TORCHBEARER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #Torchbearer #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'Torchbearer',
                'badge_image' => 'images/life_impact_badges/Torchbearer.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            10000 => [
                'title' => 'WORLD CHANGER',
                'required_count' => 10000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **WORLD CHANGER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #WorldChanger #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'WorldChanger',
                'badge_image' => 'images/life_impact_badges/World Changer.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            25000 => [
                'title' => 'HUMANITARIAN',
                'required_count' => 25000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **HUMANITARIAN** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #Humanitarian #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'Humanitarian',
                'badge_image' => 'images/life_impact_badges/Humanitarian.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            50000 => [
                'title' => 'HISTORY MAKER',
                'required_count' => 50000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **HISTORY MAKER** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #HistoryMaker #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'HistoryMaker',
                'badge_image' => 'images/life_impact_badges/History Maker.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
            100000 => [
                'title' => 'PEERS GLOBAL LEGEND',
                'required_count' => 100000,
                'compliment' => 'A Visionary Leader, A Force of Impact. A Legacy in the Making.',
                'caption_template' => "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{name}** on becoming a **PEERS GLOBAL LEGEND** for impacting **{count} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #PeersGlobalLegend #ImpactLife #1MillionEntrepreneurs",
                'hashtag' => 'PeersGlobalLegend',
                'badge_image' => 'images/life_impact_badges/Peers Global Legend.png',
                'quote' => 'Every action creates ripples. You create impact.',
            ],
        ];
    }

    /**
     * Get Life Impact recognition metadata based on impacted lives count.
     *
     * @return array{title: string, required_count: int, compliment: string, caption_template: string, hashtag: string, badge_image: string, quote: string}
     */
    public function getRecognitionMeta(int $lifeImpactedCount): array
    {
        $levels = $this->getAllRecognitionLevels();

        $selected = $levels[25];
        foreach ($levels as $threshold => $meta) {
            if ($lifeImpactedCount >= $threshold) {
                $selected = $meta;
            }
        }

        return $selected;
    }

    /**
     * Generate social caption text for Life Impact Recognition.
     */
    public function formatCaption(User $user, int $lifeImpactedCount, ?array $overrideMeta = null): string
    {
        $meta = $overrideMeta ?? $this->getRecognitionMeta($lifeImpactedCount);

        $name = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if (empty($name)) {
            $name = $user->name ?: 'Peer Member';
        }

        $formattedCount = number_format($lifeImpactedCount);
        if ($lifeImpactedCount <= 0) {
            $formattedCount = number_format($meta['required_count']);
        }

        return "🎉 **BIG CONGRATULATIONS!**\n\nCongratulations to **{$name}** on becoming a **{$meta['title']}** for impacting **{$formattedCount} lives**.\n\nYour contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**\n\n**1 Action = 1 Life Impacted.** 🌍\n\n#PeersGlobal #{$meta['hashtag']} #ImpactLife #1MillionEntrepreneurs";
    }

    /**
     * Generate the Life Impact Recognition Creative image.
     */
    public function generate(User $user, int $lifeImpactedCount = 0, ?int $overrideThreshold = null, ?FileModel $targetFileRecord = null): FileModel
    {
        try {
            if ($lifeImpactedCount <= 0) {
                $lifeImpactedCount = (int) ($user->life_impacted_count ?? 0);
            }

            $effectiveThreshold = $overrideThreshold && $overrideThreshold > 0
                ? $overrideThreshold
                : ($lifeImpactedCount >= 25 ? $lifeImpactedCount : 25);

            $meta = $this->getRecognitionMeta($effectiveThreshold);

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
            if (is_array($company)) {
                $company = $company['name'] ?? '';
            }
            $company = trim((string) $company);
            if (in_array(strtolower($company), ['null', 'none', 'no company', 'peers global'], true)) {
                $company = '';
            }
            if (empty($company)) {
                $c = $user->relationLoaded('activeCircle') ? $user->getRelation('activeCircle') : ($user->activeCircle ?? null);
                if (! $c && $user->relationLoaded('circleMembers')) {
                    $c = $user->getRelation('circleMembers')?->first()?->circle;
                }
                if ($c && ! empty($c->name)) {
                    $company = $c->name;
                }
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
            $subInfoLine = implode('  •  ', $subInfoParts);
            if (empty($subInfoLine)) {
                $subInfoLine = 'Peers Global Member';
            }

            // Category Level 4 or Designation
            $level4Name = '';
            if ($user->relationLoaded('level4Category')) {
                $level4Name = $user->getRelation('level4Category')?->name ?? '';
            } elseif (! empty($user->business_category_id)) {
                $level4Name = CircleCategoryLevel4::find($user->business_category_id)?->name ?? '';
            }
            if (empty($level4Name)) {
                $level4Name = $user->business_sub_category ?? $user->category_name ?? $user->designation ?? $user->job_title ?? '';
            }
            if (is_array($level4Name)) {
                $level4Name = $level4Name['name'] ?? $level4Name['label'] ?? '';
            }
            $level4Name = trim((string) $level4Name);
            if (empty($level4Name) || in_array(strtolower($level4Name), ['null', 'none'], true)) {
                $level4Name = $user->membership_status ?? 'Peers Global Member';
            }

            if ($isCanvaTemplate) {
                $canvas = imagecreatefrompng($templatePath);
                if (function_exists('imagepalettetotruecolor')) {
                    imagepalettetotruecolor($canvas);
                }
                $width = imagesx($canvas);
                $height = imagesy($canvas);
                imagealphablending($canvas, true);

                $colorGold = imagecolorallocate($canvas, 252, 226, 138);
                $colorDarkNavy = imagecolorallocate($canvas, 10, 25, 50);
                $colorSlate = imagecolorallocate($canvas, 71, 85, 105);
                $darkCircleBg = imagecolorallocate($canvas, 10, 37, 64);

                // 1. Profile Avatar: Center X = 540, Center Y = 662, Diameter = 225
                $targetDiameter = 242;
                $circleCenterX = 534;
                $circleCenterY = 660;

                $this->drawAvatarOrInitial($canvas, $user, $circleCenterX, $circleCenterY, $targetDiameter, $darkCircleBg);

                // 2. Cut / restore the name banner plate & gold ribbon border from the original template over the avatar photo
                $templateOverlay = @imagecreatefrompng($templatePath);
                if ($templateOverlay) {
                    imagecopy($canvas, $templateOverlay, 280, 765, 280, 765, 520, 75);
                    imagedestroy($templateOverlay);
                }

                // Center aligned text helper
                $drawCenterText = function ($img, int $fontSize, int $y, $color, string $font, string $text, int $maxWidth = 900) use ($width) {
                    if (empty($text)) {
                        return;
                    }
                    $size = $fontSize;
                    $bbox = @imagettfbbox($size, 0, $font, $text);
                    while ($bbox && abs($bbox[4] - $bbox[0]) > $maxWidth && $size > 9) {
                        $size -= 1;
                        $bbox = @imagettfbbox($size, 0, $font, $text);
                    }
                    if ($bbox) {
                        $textWidth = abs($bbox[4] - $bbox[0]);
                        $x = ($width - $textWidth) / 2;
                        imagettftext($img, $size, 0, (int) $x, $y, $color, $font, $text);
                    }
                };

                // 2. Line 1: Member Name inside ribbon label plate (Y = 796, Gold, ExtraBold, Size = 19)
                $displayName = strtoupper(trim($name ?: 'PEER MEMBER'));
                $drawCenterText($canvas, 19, 796, $colorGold, $fontExtraBold, $displayName, 290);

                // 3. Line 2: Company & Location Row (Y = 845, Dark Navy, SemiBold, Size = 17)
                if (! empty($subInfoLine)) {
                    $drawCenterText($canvas, 17, 845, $colorDarkNavy, $fontSemiBold, $subInfoLine, 720);
                }

                // 4. Line 3: Level 4 Category / Subcategory (Y = 871, Slate, SemiBold, Size = 15)
                if (! empty($level4Name)) {
                    $drawCenterText($canvas, 15, 871, $colorSlate, $fontSemiBold, (string) $level4Name, 720);
                }
            } else {
                // Fallback rendering
                $width = 1080;
                $height = 1350;

                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, true);

                $bgNavy = imagecolorallocate($canvas, 7, 13, 26);
                imagefill($canvas, 0, 0, $bgNavy);

                $white = imagecolorallocate($canvas, 255, 255, 255);
                $gold = imagecolorallocate($canvas, 223, 177, 72);
                $subTitleSlate = imagecolorallocate($canvas, 226, 232, 240);
                $softSlate = imagecolorallocate($canvas, 203, 213, 225);
                $footerGray = imagecolorallocate($canvas, 100, 116, 139);
                $darkCircleBg = imagecolorallocate($canvas, 22, 36, 71);
                $boxBg = imagecolorallocate($canvas, 9, 17, 34);

                // 1. Top Header
                $topY = 125;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    ['CONGRATULATIONS FOR BECOMING'],
                    24,
                    (int) ($width / 2),
                    $topY,
                    $gold,
                    $fontExtraBold
                );

                // 2. Award Level Title
                $titleY = 215;
                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$meta['title']],
                    50,
                    (int) ($width / 2),
                    $titleY,
                    $white,
                    $fontExtraBold
                );

                $this->drawGoldSeparator($canvas, (int) ($width / 2), 285, $gold);

                // 3. User Avatar
                $avatarCenterX = 540;
                $avatarCenterY = 460;
                $avatarSize = 250;

                $this->drawAvatarOrInitial($canvas, $user, $avatarCenterX, $avatarCenterY, $avatarSize, $darkCircleBg);

                imagesetthickness($canvas, 3);
                imageellipse($canvas, $avatarCenterX, $avatarCenterY, $avatarSize + 4, $avatarSize + 4, $gold);
                imagesetthickness($canvas, 1);

                // 4. Peer Name
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

                // 5. Business Name & City Line
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

                // 6. Compliment Text
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

                // 7. Outlined Count Box
                $countDisplay = number_format($effectiveThreshold);
                $countText = "WHEN IMPACTING {$countDisplay} LIVES";
                $boxY = 900;
                $boxHeight = 84;
                $boxWidth = 760;
                $boxX = (int) (($width / 2) - ($boxWidth / 2));

                imagefilledrectangle($canvas, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $boxBg);
                imagesetthickness($canvas, 2);
                imagerectangle($canvas, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $gold);
                imagesetthickness($canvas, 1);

                $this->drawPreWrappedCenteredText(
                    $canvas,
                    [$countText],
                    24,
                    (int) ($width / 2),
                    $boxY + 28,
                    $white,
                    $fontExtraBold
                );

                // 8. Taglines
                $tagline1 = '1 ACTION = 1 LIFE IMPACTED.';
                $tagline2 = 'YOU ARE A 1 MILLION MISSION CONTRIBUTOR.';
                $this->drawPreWrappedCenteredText($canvas, [$tagline1], 17, (int) ($width / 2), 1055, $gold, $fontExtraBold);
                $this->drawPreWrappedCenteredText($canvas, [$tagline2], 17, (int) ($width / 2), 1095, $white, $fontExtraBold);

                // 9. Footer
                $footerText = "PEERS GLOBAL  \u{2022}  World's First Community of Collaboration";
                $this->drawPreWrappedCenteredText($canvas, [$footerText], 16, (int) ($width / 2), 1250, $footerGray, $fontSemiBold);
            }

            // Save WebP File & Create FileModel
            $filename = 'life_impact_creative_'.Str::uuid().'.webp';
            $tempPath = tempnam(sys_get_temp_dir(), 'impact_creative');

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
                    Log::error('LifeImpactCreativeGenerator: Failed to copy creative to public disk: '.$e->getMessage());
                }
            }

            @unlink($tempPath);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error('Failed to generate life impact creative: '.$e->getMessage(), [
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
                Log::warning('Could not process user avatar for life impact creative: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        if (! $drawnSuccessfully) {
            $displayName = $user->display_name ?: $user->first_name ?: 'User';
            $initial = strtoupper(substr($displayName, 0, 2));

            $avatarImg = imagecreatetruecolor($avatarSize, $avatarSize);
            imagealphablending($avatarImg, false);
            imagesavealpha($avatarImg, true);
            $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
            imagefill($avatarImg, 0, 0, $transparent);

            $avatarRadius = $avatarSize / 2;
            imagefilledellipse($avatarImg, (int) $avatarRadius, (int) $avatarRadius, $avatarSize, $avatarSize, $fallbackBgColor);

            $fontPath = $this->getFontPath('bold');
            if (! file_exists($fontPath)) {
                $fontPath = public_path('fonts/Montserrat-ExtraBold.ttf');
            }
            if (! file_exists($fontPath)) {
                $fontPath = public_path('fonts/Montserrat-Bold.ttf');
            }

            $whiteColor = imagecolorallocate($avatarImg, 255, 255, 255);
            $fontSizeInit = (int) ($avatarSize * 0.38);

            if (file_exists($fontPath)) {
                $bbox = imagettfbbox($fontSizeInit, 0, $fontPath, $initial);
                if ($bbox) {
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    $textHeight = abs($bbox[5] - $bbox[1]);
                    $ix = ($avatarSize / 2) - ($textWidth / 2) - $bbox[0];
                    $iy = ($avatarSize / 2) + ($textHeight / 2);
                    imagettftext($avatarImg, $fontSizeInit, 0, (int) $ix, (int) $iy, $whiteColor, $fontPath, $initial);
                } else {
                    $this->drawCenteredBoldText($avatarImg, $fontSizeInit, $avatarRadius, $avatarRadius, $whiteColor, $fontPath, $initial);
                }
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
