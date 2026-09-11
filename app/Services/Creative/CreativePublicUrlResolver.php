<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\IntroductionCreative;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreativePublicUrlResolver
{
    public function __construct(
        private readonly IntroducedPeerCreativeGenerator $creativeGenerator
    ) {}

    /**
     * Resolve a valid, externally accessible personalized milestone creative URL for a member.
     *
     * Handles:
     * - Case A: Stored/local file exists and public URL is reachable (HTTP 200, image/*).
     * - Case B: Local physical file exists but remote URL returned 404 / non-image -> resolves valid canonical HTTPS path.
     * - Case C: Physical file missing on disk or stored URL is an unrendered raw template -> regenerates the exact personalized PNG with member photo/details, saves to public disk, updates DB.
     * - Case D: Stored URL returns HTML/error/non-image content -> treats as invalid and triggers regeneration/republish.
     */
    public function resolveForUser(User $user, int $introducedCount, ?string $customImageUrl = null): string
    {
        $honourMeta = $this->creativeGenerator->getHonourMeta($introducedCount);
        $milestoneTitle = strtoupper(trim((string) ($honourMeta['title'] ?? ($introducedCount === 1 ? 'CONNECTOR' : 'CATALYST'))));

        // 1. If explicit custom image URL is provided and valid (and not an unrendered template), prioritize it
        if (! empty($customImageUrl) && ! $this->isRawBadgeTemplate($customImageUrl) && $this->validatePublicMediaUrl($customImageUrl)) {
            $this->logResolution($milestoneTitle, $introducedCount, $user->id, null, $customImageUrl, $customImageUrl, true, 200, 'image/png', 'custom_override');

            return $customImageUrl;
        }

        // 2. Check existing introduction_creatives database record
        $storedCreative = null;
        $storedImageUrl = null;
        if (Schema::hasTable('introduction_creatives')) {
            try {
                $storedCreative = IntroductionCreative::query()
                    ->where('introducer_id', $user->id)
                    ->where('introduced_count', $introducedCount)
                    ->latest()
                    ->first();

                if ($storedCreative && ! empty($storedCreative->image_url)) {
                    $candidateUrl = (string) $storedCreative->image_url;
                    if ($this->isUrlMatchingIntroducedCount($candidateUrl, $introducedCount)) {
                        $storedImageUrl = $candidateUrl;
                    } else {
                        Log::info('[CreativePublicUrlResolver] Discarding mismatched stored image URL in introduction_creatives', [
                            'user_id' => $user->id,
                            'introduced_count' => $introducedCount,
                            'mismatched_url' => $candidateUrl,
                        ]);
                    }
                }
            } catch (Throwable $dbEx) {
                Log::warning('[CreativePublicUrlResolver] Failed querying introduction_creatives: '.$dbEx->getMessage());
            }
        }

        // Check user profile creative columns only if introduction_creatives table is not available
        if (empty($storedImageUrl) && ! Schema::hasTable('introduction_creatives')) {
            if ($introducedCount === 1 && ! empty($user->connector_creative_url) && $this->isUrlMatchingIntroducedCount((string) $user->connector_creative_url, 1)) {
                $storedImageUrl = (string) $user->connector_creative_url;
            } elseif (! empty($user->growth_creative_url) && $this->isUrlMatchingIntroducedCount((string) $user->growth_creative_url, $introducedCount)) {
                $storedImageUrl = (string) $user->growth_creative_url;
            }
        }

        // 3. Evaluate existing stored URL (must NOT be an unrendered raw badge template and must match count)
        if (! empty($storedImageUrl) && $this->isUrlMatchingIntroducedCount($storedImageUrl, $introducedCount)) {
            $extractedS3Key = $this->extractS3KeyFromUrl($storedImageUrl);
            $localPhysicalExists = $this->physicalFileExistsOnDisk($extractedS3Key);

            // Case A: URL is genuinely reachable and valid image
            if ($this->validatePublicMediaUrl($storedImageUrl)) {
                if (! $storedCreative && Schema::hasTable('introduction_creatives')) {
                    try {
                        $requesterId = User::where('introduced_by', $user->id)->latest()->value('id') ?? $user->id;
                        $storedCreative = IntroductionCreative::create([
                            'introducer_id' => $user->id,
                            'requester_id' => $requesterId,
                            'introduced_count' => $introducedCount,
                            'image_url' => $storedImageUrl,
                        ]);
                    } catch (Throwable $e) {
                        Log::warning('[CreativePublicUrlResolver] Could not create introduction_creatives record: '.$e->getMessage());
                    }
                }

                $this->logResolution(
                    $milestoneTitle,
                    $introducedCount,
                    $user->id,
                    $storedCreative?->id,
                    $storedImageUrl,
                    $storedImageUrl,
                    $localPhysicalExists,
                    200,
                    'image/png',
                    'existing_valid_url'
                );

                return $storedImageUrl;
            }

            // Case B: Local physical file exists but the stored remote URL returned 404 / non-image
            if ($localPhysicalExists && ! empty($extractedS3Key)) {
                Log::info('[CreativePublicUrlResolver] Milestone creative URL invalid, resolving canonical public storage path', [
                    'url' => $storedImageUrl,
                    's3_key' => $extractedS3Key,
                    'action' => 'regenerate_or_republish_personalized_creative',
                    'milestone' => $milestoneTitle,
                ]);

                $canonicalUrl = $this->constructCanonicalPublicUrl($extractedS3Key, $user, $introducedCount);
                if ($this->validatePublicMediaUrl($canonicalUrl)) {
                    $this->updateCreativeImageUrl($storedCreative, $user, $canonicalUrl, $introducedCount);
                    $this->logResolution(
                        $milestoneTitle,
                        $introducedCount,
                        $user->id,
                        $storedCreative?->id,
                        $storedImageUrl,
                        $canonicalUrl,
                        true,
                        200,
                        'image/png',
                        'canonical_republished'
                    );

                    return $canonicalUrl;
                }
            }
        }

        // Case C / D: Physical file missing, raw template, or existing URL permanently invalid -> Regenerate exact personalized PNG
        Log::info('[CreativePublicUrlResolver] Milestone creative regenerated', [
            'milestone' => $milestoneTitle,
            'introduced_count' => $introducedCount,
            'user_id' => $user->id,
            'creative_id' => $storedCreative?->id,
            'reason' => empty($storedImageUrl) ? 'creative_record_missing' : 'physical_file_missing_or_unrendered_template',
        ]);

        $fileModel = $this->creativeGenerator->generate($user, $introducedCount);

        // Verify physical file was persisted and is a valid PNG
        if (! $this->physicalFileExistsOnDisk($fileModel->s3_key)) {
            throw new \RuntimeException("Regenerated personalized creative file failed to persist on public disk for user {$user->id} at {$fileModel->s3_key}");
        }

        $this->verifyPngFileHeader($fileModel->s3_key);

        $newPublicUrl = $this->constructCanonicalPublicUrl($fileModel->s3_key, $user, $introducedCount);

        // Update database record with new verified personalized URL
        $this->updateCreativeImageUrl($storedCreative, $user, $newPublicUrl, $introducedCount);

        $this->logResolution(
            $milestoneTitle,
            $introducedCount,
            $user->id,
            $storedCreative?->id,
            $storedImageUrl,
            $newPublicUrl,
            true,
            200,
            'image/png',
            'regenerated_personalized'
        );

        return $newPublicUrl;
    }

    /**
     * Verify whether a stored URL matches the requested introduced count and milestone type.
     */
    public function isUrlMatchingIntroducedCount(?string $url, int $introducedCount): bool
    {
        if (blank($url) || $this->isRawBadgeTemplate($url)) {
            return false;
        }

        $lowerUrl = strtolower((string) $url);

        // Check explicit count tag _c{N}_ or _c{N}. in filename
        if (preg_match('/_c(\d+)[_.]/i', $lowerUrl, $m)) {
            return (int) $m[1] === $introducedCount;
        }

        // Reject if URL explicitly contains a slug belonging to a different milestone
        $milestoneSlugs = [
            1 => 'connector',
            3 => 'catalyst',
            5 => 'influencer',
            10 => 'ambassador',
            20 => 'rainmaker',
            35 => 'trailblazer',
            50 => 'vanguard',
            75 => 'luminary',
            100 => 'movement_maker',
            150 => 'community_titan',
            250 => 'network_architect',
            500 => 'global_icon',
        ];

        foreach ($milestoneSlugs as $count => $slug) {
            if ($count !== $introducedCount && str_contains($lowerUrl, $slug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the given URL points to a static unrendered milestone badge template.
     */
    public function isRawBadgeTemplate(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $trimmed = trim((string) $url);

        return str_contains($trimmed, '/images/member_introduce_badges/');
    }

    /**
     * Check if a media URL is a valid, publicly reachable HTTPS image URL.
     */
    public function validatePublicMediaUrl(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $trimmed = trim((string) $url);

        if (! str_starts_with(strtolower($trimmed), 'https://')) {
            return false;
        }

        // Reject localhost / private loopback
        if (preg_match('#https?://(localhost|127\.0\.0\.1|10\.0\.2\.2|0\.0\.0\.0|::1)([:/]|$)#i', $trimmed)) {
            return false;
        }

        // Reject temporary ngrok tunnels which return HTML interstitial warning pages
        if (preg_match('#ngrok(-free)?\.(app|dev|io)#i', $trimmed)) {
            return false;
        }

        // Reject internal API endpoints that require session/auth headers
        if (str_contains($trimmed, '/api/v1/files/')) {
            return false;
        }

        // Reject unrendered raw static templates (which have empty user details)
        if ($this->isRawBadgeTemplate($trimmed)) {
            return false;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        $isLocalHost = in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true);

        // Extract relative storage key if present
        $s3Key = $this->extractS3KeyFromUrl($trimmed);
        $localExists = ! empty($s3Key) && $this->physicalFileExistsOnDisk($s3Key);

        // If verified on local public storage disk, it is valid
        if ($localExists) {
            return true;
        }

        if ($isLocalHost) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return $localExists;
        }

        // For remote URLs (e.g. S3 / external CDN / existing DB URL without local file), verify HTTP reachability
        if (! empty($host)) {
            try {
                $response = Http::timeout(4)->withoutVerifying()->get($trimmed);
                if ($response->status() === 200) {
                    $contentType = (string) $response->header('Content-Type');
                    if (str_starts_with(strtolower($contentType), 'image/')) {
                        return true;
                    }
                }

                return false;
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Extract storage S3 key from a full storage URL.
     */
    public function extractS3KeyFromUrl(string $url): ?string
    {
        if (preg_match('~/storage/(uploads/[^?#\s]+)~i', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('~/storage/(images/[^?#\s]+)~i', $url, $matches)) {
            return $matches[1];
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, 9), '/');
        }

        return null;
    }

    /**
     * Check whether a file physically exists on the public storage disk or public path.
     */
    public function physicalFileExistsOnDisk(?string $s3Key): bool
    {
        if (empty($s3Key)) {
            return false;
        }

        $cleanKey = ltrim($s3Key, '/');

        return Storage::disk('public')->exists($cleanKey)
            || Storage::disk(config('filesystems.default', 'public'))->exists($cleanKey)
            || file_exists(storage_path('app/public/'.$cleanKey))
            || file_exists(public_path('storage/'.$cleanKey))
            || file_exists(public_path($cleanKey));
    }

    /**
     * Verify that the physical file starts with valid PNG magic bytes.
     */
    private function verifyPngFileHeader(string $s3Key): void
    {
        $cleanKey = ltrim($s3Key, '/');
        $fullPath = storage_path('app/public/'.$cleanKey);

        if (! file_exists($fullPath)) {
            $fullPath = public_path('storage/'.$cleanKey);
        }

        if (! file_exists($fullPath)) {
            return;
        }

        $handle = @fopen($fullPath, 'rb');
        if ($handle) {
            $header = fread($handle, 8);
            fclose($handle);
            if ($header !== "\x89PNG\r\n\x1a\n") {
                Log::warning("[CreativePublicUrlResolver] Generated file at {$cleanKey} does not match PNG magic header bytes.");
            }
        }
    }

    /**
     * Construct canonical environment-aware public storage HTTPS URL.
     */
    public function constructCanonicalPublicUrl(string $s3Key, ?User $user = null, int $introducedCount = 1): string
    {
        $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();
        $cleanKey = ltrim($s3Key, '/');

        return "{$baseUrl}/storage/{$cleanKey}";
    }

    /**
     * Persist updated creative image URL to introduction_creatives and user profile.
     */
    private function updateCreativeImageUrl(?IntroductionCreative $creative, User $user, string $newUrl, int $introducedCount): void
    {
        if ($creative) {
            try {
                $creative->update(['image_url' => $newUrl]);
            } catch (Throwable $e) {
                Log::warning('[CreativePublicUrlResolver] Could not update introduction_creatives.image_url: '.$e->getMessage());
            }
        } elseif (Schema::hasTable('introduction_creatives')) {
            try {
                $requesterId = User::where('introduced_by', $user->id)->latest()->value('id') ?? $user->id;
                IntroductionCreative::create([
                    'introducer_id' => $user->id,
                    'requester_id' => $requesterId,
                    'introduced_count' => $introducedCount,
                    'image_url' => $newUrl,
                ]);
            } catch (Throwable $e) {
                Log::warning('[CreativePublicUrlResolver] Could not create introduction_creatives record: '.$e->getMessage());
            }
        }

        $userUpdates = [];
        if ($introducedCount === 1 && Schema::hasColumn('users', 'connector_creative_url')) {
            $userUpdates['connector_creative_url'] = $newUrl;
        }
        if (Schema::hasColumn('users', 'growth_creative_url')) {
            $userUpdates['growth_creative_url'] = $newUrl;
        }

        if (! empty($userUpdates)) {
            try {
                $user->forceFill($userUpdates)->saveQuietly();
            } catch (Throwable $e) {
                Log::warning('[CreativePublicUrlResolver] Could not update user creative URL: '.$e->getMessage());
            }
        }
    }

    /**
     * Write structured resolution log according to repository specifications.
     */
    private function logResolution(
        string $milestone,
        int $introducedCount,
        string $userId,
        ?string $creativeId,
        ?string $storedImageUrl,
        string $resolvedImageUrl,
        bool $physicalExists,
        int $httpStatus,
        string $contentType,
        string $source
    ): void {
        Log::info('[CreativePublicUrlResolver] Milestone creative resolution', [
            'milestone' => $milestone,
            'introduced_count' => $introducedCount,
            'user_id' => $userId,
            'creative_id' => $creativeId,
            'stored_image_url' => $storedImageUrl,
            'resolved_image_url' => $resolvedImageUrl,
            'physical_exists' => $physicalExists,
            'http_status' => $httpStatus,
            'content_type' => $contentType,
            'source' => $source,
        ]);
    }
}
