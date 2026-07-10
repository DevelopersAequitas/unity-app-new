<?php

namespace App\Services\Media;

use App\Models\BirthdayCreativeConfig;
use App\Models\FileModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Log;

class BirthdayCreativeImageService
{
    /**
     * Generate birthday creative image for a user.
     */
    public function generate(User $user): FileModel
    {
        try {
            $config = BirthdayCreativeConfig::first();
            if (! $config) {
                // Default config if none exists
                $config = new BirthdayCreativeConfig([
                    'is_enabled' => true,
                    'background_gradient_start' => '#8E2DE2',
                    'background_gradient_end' => '#4A00E0',
                    'text_color' => '#FFFFFF',
                ]);
            }

            $width = 1080;
            $height = 1080;

            // Resolve base background template
            $staticTemplatePath = public_path('images/birthday-template.png');
            $img = null;

            if (file_exists($staticTemplatePath)) {
                // 1. Use the pre-designed template in public/images/
                $img = Image::make($staticTemplatePath)->resize($width, $height);
            } else {
                // Check if there is an uploaded template in database configuration
                $uploadedTemplatePath = null;
                if ($config->template_file_id) {
                    $templateFile = FileModel::find($config->template_file_id);
                    if ($templateFile && $templateFile->s3_key) {
                        $disk = config('filesystems.default', 'public');
                        if (Storage::disk($disk)->exists($templateFile->s3_key)) {
                            $uploadedTemplatePath = Storage::disk($disk)->path($templateFile->s3_key);
                        } elseif (Storage::disk('public')->exists($templateFile->s3_key)) {
                            $uploadedTemplatePath = Storage::disk('public')->path($templateFile->s3_key);
                        }
                    }
                }

                if ($uploadedTemplatePath) {
                    // 2. Use the database config uploaded template
                    $img = Image::make($uploadedTemplatePath)->resize($width, $height);
                } else {
                    // 3. Fallback to gradient background if no template exists
                    $img = $this->createGradientImage(
                        $width,
                        $height,
                        $config->background_gradient_start,
                        $config->background_gradient_end
                    );
                }
            }

            // Cover the pre-printed "USER NAME" and "BUSINESS NAME / INDUSTRY" placeholders on the template completely.
            // Using a single centered white rectangle that does not touch any waves on the left/right.
            $img->rectangle(310, 700, 770, 850, function ($draw) {
                $draw->background('#FFFFFF');
            });

            // Draw profile photo or initials
            $this->drawAvatarOrInitial($img, $user, $width, $height, $config);

            // Add dynamic user name and designation
            $this->drawTextAndDetails($img, $user, $width, $height, $config);

            // Save image to storage
            $diskName = 'public';
            $folder = 'uploads/birthday/'.now()->format('Y/m/d');
            if (! Storage::disk($diskName)->exists($folder)) {
                Storage::disk($diskName)->makeDirectory($folder);
            }

            $fileName = Str::uuid().'.jpg';
            $relativeFilePath = $folder.'/'.$fileName;
            $absolutePath = Storage::disk($diskName)->path($relativeFilePath);

            // Ensure directories exist
            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            $img->save($absolutePath, 90, 'jpg');

            // Create File record in database
            $fileModel = FileModel::create([
                'uploader_user_id' => $user->id,
                's3_key' => $relativeFilePath,
                'mime_type' => 'image/jpeg',
                'size_bytes' => filesize($absolutePath),
                'width' => $width,
                'height' => $height,
            ]);

            return $fileModel;
        } catch (\Throwable $e) {
            Log::error("Failed to generate birthday creative for user {$user->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Create vertical gradient image fallback.
     */
    private function createGradientImage(int $width, int $height, string $startColor, string $endColor)
    {
        $img = Image::canvas(1, 2);

        $start = $this->hexToRgb($startColor);
        $end = $this->hexToRgb($endColor);

        $colorStart = sprintf('rgb(%d, %d, %d)', $start['r'], $start['g'], $start['b']);
        $colorEnd = sprintf('rgb(%d, %d, %d)', $end['r'], $end['g'], $end['b']);

        $img->pixel($colorStart, 0, 0);
        $img->pixel($colorEnd, 0, 1);

        $img->resize($width, $height);

        return $img;
    }

    /**
     * Helper to parse hex to rgb.
     */
    private function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    /**
     * Draw Avatar or Name Initial.
     */
    private function drawAvatarOrInitial($img, User $user, int $width, int $height, $config): void
    {
        $avatarSize = 260;

        // 1. Wipe out the pre-printed double-line circle ring from the template completely (Restore white rectangle mask)
        // Center white rectangle mask from Y = 320 to Y = 710, X = 320 to X = 760
        $img->rectangle(320, 320, 760, 710, function ($draw) {
            $draw->background('#FFFFFF');
        });

        // Dynamic center coordinates for the actual profile photo / letter
        $centerX = (int) ($width / 2);
        $centerY = (int) ($height / 2);

        $insertX = (int) (($width - $avatarSize) / 2);
        $insertY = (int) (($height - $avatarSize) / 2);

        // 2. Resolve avatar image source
        $avatarSource = null;
        $tempFilePath = null;
        if (! empty($user->profile_photo_file_id)) {
            $fileRecord = FileModel::find($user->profile_photo_file_id);
            if ($fileRecord && $fileRecord->s3_key) {
                $disk = config('filesystems.default', 'public');
                if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk($disk)->path($fileRecord->s3_key);
                } elseif (Storage::disk('public')->exists($fileRecord->s3_key)) {
                    $avatarSource = Storage::disk('public')->path($fileRecord->s3_key);
                }
            }
        }

        if (! $avatarSource && ! empty($user->profile_photo_url)) {
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
            } else {
                $avatarSource = $user->profile_photo_url;
            }
        }

        $drawnSuccessfully = false;

        if ($avatarSource) {
            try {
                // Fetch, fit to size
                $avatar = Image::make($avatarSource)->fit($avatarSize, $avatarSize);

                // Create mask circle
                $mask = Image::canvas($avatarSize, $avatarSize);
                $mask->circle($avatarSize, (int) ($avatarSize / 2), (int) ($avatarSize / 2), function ($draw) {
                    $draw->background('#ffffff');
                });

                // Apply mask
                $avatar->mask($mask, true);

                // Insert avatar
                $img->insert($avatar, 'top-left', $insertX, $insertY);
                $drawnSuccessfully = true;
            } catch (\Throwable $e) {
                Log::warning('Could not process user avatar for birthday creative: '.$e->getMessage());
            } finally {
                if ($tempFilePath && file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
            }
        }

        // Draw initial if avatar failed or is missing
        if (! $drawnSuccessfully) {
            $displayName = $user->display_name ?: $user->first_name ?: 'User';
            $initial = strtoupper(substr($displayName, 0, 1));

            // Fill the avatar circle with deep blue for initials
            $img->circle($avatarSize, $centerX, $centerY, function ($draw) {
                $draw->background('#00238C');
            });

            // Draw initial letter
            $fontPath = $this->getFontPath(true);
            $img->text($initial, $centerX, $centerY, function ($font) use ($fontPath) {
                $font->file($fontPath);
                $font->size(110);
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('middle');
            });
        }
    }

    /**
     * Draw dynamic user name and designation.
     */
    private function drawTextAndDetails($img, User $user, int $width, int $height, $config): void
    {
        $fontPathBold = $this->getFontPath(true);
        $fontPathRegular = $this->getFontPath(false);

        // 1. User Name (Upper Case, Bold Deep Blue) - Positioned at Y = 745
        $displayName = strtoupper($user->display_name ?: ($user->first_name.' '.$user->last_name));
        $img->text($displayName, 540, 745, function ($font) use ($fontPathBold) {
            $font->file($fontPathBold);
            $font->size(52);
            $font->color('#00238C');
            $font->align('center');
            $font->valign('middle');
        });

        // 2. Designation / Role (Purple/Red) - Positioned at Y = 805
        $designation = $user->designation ?: 'Global Peer';
        $img->text($designation, 540, 805, function ($font) use ($fontPathRegular) {
            $font->file($fontPathRegular);
            $font->size(24);
            $font->color('#A81D34');
            $font->align('center');
            $font->valign('middle');
        });
    }

    /**
     * Get clean professional font path (dynamic fallback to open_sans if Montserrat is missing).
     */
    private function getFontPath(bool $bold = false): string
    {
        $fontName = $bold ? 'Montserrat-Bold.ttf' : 'Montserrat-Regular.ttf';
        $fontPath = public_path('fonts/'.$fontName);
        if (! file_exists($fontPath)) {
            $fontPath = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');
        }

        return $fontPath;
    }
}
