<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\ActivityCreative;
use App\Models\City;
use App\Models\FileModel;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WelcomeCreativeService
{
    public function __construct(
        private readonly WelcomeCreativeImageGenerator $generator
    ) {}

    /**
     * Generate welcome creative image, publish automated post to timeline, and register ActivityCreative.
     */
    public function generateAndPostWelcomeCreative(User $user): ?ActivityCreative
    {
        try {
            if ($user->isSystemUser()) {
                return null;
            }

            if (! $this->isEligibleForWelcomeCreative($user)) {
                Log::info("WelcomeCreativeService: User {$user->id} is not yet eligible for welcome creative (missing required details or valid profile photo).");

                return null;
            }

            $existingCreative = null;
            if (Schema::hasTable('activity_creatives')) {
                try {
                    $existingCreative = ActivityCreative::query()
                        ->where('user_id', $user->id)
                        ->where('activity_type', 'welcome')
                        ->latest('created_at')
                        ->first();
                } catch (\Throwable $e) {
                    Log::warning("WelcomeCreativeService: Could not query activity_creatives: {$e->getMessage()}");
                }
            }

            $existingPost = null;
            if (Schema::hasTable('posts')) {
                if ($existingCreative && ! empty($existingCreative->post_id)) {
                    $existingPost = Post::find($existingCreative->post_id);
                }

                if (! $existingPost) {
                    $postQuery = Post::query()
                        ->where('source_type', 'welcome')
                        ->where('source_id', (string) $user->id);

                    if (Schema::hasColumn('posts', 'is_deleted')) {
                        $postQuery->where('is_deleted', false);
                    }

                    $existingPost = $postQuery->first();
                }
            }

            if ($existingPost && $existingCreative && ! empty($existingCreative->creative_url)) {
                if (empty($user->welcome_creative_url) && Schema::hasColumn('users', 'welcome_creative_url')) {
                    try {
                        $user->forceFill(['welcome_creative_url' => $existingCreative->creative_url])->saveQuietly();
                    } catch (\Throwable) {
                    }
                }

                Log::info("WelcomeCreativeService: Existing welcome creative and timeline post found for user {$user->id}");

                return $existingCreative;
            }

            // 1. Generate welcome creative image file (with multi-layer fallback)
            $fileModel = null;
            $creativeUrl = null;

            try {
                $fileModel = $this->generator->generate($user);
                $creativeUrl = url('/api/v1/files/'.$fileModel->id);
            } catch (\Throwable $genEx) {
                Log::warning("WelcomeCreativeService: Primary WelcomeCreativeImageGenerator failed for user {$user->id}, trying fallback generator: {$genEx->getMessage()}");

                try {
                    $fileModel = app(WearTheBadgeImageGenerator::class)->generate($user);
                    $creativeUrl = url('/api/v1/files/'.$fileModel->id);
                } catch (\Throwable $fallbackEx) {
                    Log::warning("WelcomeCreativeService: Fallback WearTheBadgeImageGenerator failed for user {$user->id}: {$fallbackEx->getMessage()}");
                }
            }

            if (! $creativeUrl) {
                $creativeUrl = ! empty($user->welcome_creative_url)
                    ? (string) $user->welcome_creative_url
                    : (! empty($existingCreative?->creative_url)
                        ? (string) $existingCreative->creative_url
                        : url('/images/welcome-template.png'));
            }

            $fileId = $fileModel?->id;
            if (! $fileId && preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', (string) $creativeUrl, $matches)) {
                $fileId = $matches[1];
            }
            if (! $fileId) {
                $fileId = (string) Str::uuid();
            }

            // 2. Persist URL to user model
            if (Schema::hasTable('users')) {
                $userUpdates = [];
                if (Schema::hasColumn('users', 'welcome_creative_url')) {
                    $userUpdates['welcome_creative_url'] = $creativeUrl;
                }
                if (Schema::hasColumn('users', 'profile_card_image_url') && empty($user->profile_card_image_url)) {
                    $userUpdates['profile_card_image_url'] = $creativeUrl;
                }
                if (! empty($userUpdates)) {
                    try {
                        $user->forceFill($userUpdates)->saveQuietly();
                    } catch (\Throwable $e) {
                        Log::warning("WelcomeCreativeService: Could not update user creative URL: {$e->getMessage()}");
                    }
                }
            }

            // 3. Prepare timeline post details
            $systemUser = User::getSystemUser();
            $authorUserId = (string) $systemUser->id;

            $userName = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
            if ($userName === '') {
                $userName = 'New Peer';
            }

            $company = trim((string) ($user->company_name ?? $user->company ?? ''));
            $cityModel = null;
            try {
                $cityModel = $user->relationLoaded('city') ? $user->getRelation('city') : ($user->cityRelation ?? null);
                if (! $cityModel && ! empty($user->city_id) && Schema::hasTable('cities')) {
                    $cityModel = City::find($user->city_id);
                }
            } catch (\Throwable) {
                $cityModel = null;
            }
            $cityName = $cityModel?->name ?? $user->city_name ?? $user->city ?? $user->business_city ?? '';
            if (is_array($cityName)) {
                $cityName = $cityName['name'] ?? $cityName['label'] ?? '';
            }
            if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
                $decoded = json_decode(trim($cityName), true);
                $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
            }
            $cityName = trim((string) $cityName);
            if (in_array(strtolower($cityName), ['null', 'none', 'n/a'], true)) {
                $cityName = '';
            }

            $bioParts = array_filter([$company, $cityName]);
            $bioStr = ! empty($bioParts) ? implode(', ', $bioParts) : 'Peers Global Community';

            $title = "Welcome {$userName} to Peers Global! 🎉";
            $contentText = "Please join us in welcoming {$userName} ({$bioStr}) to our Peers Global family! 🚀✨";

            // 4. Create timeline post (isolated from ActivityCreative so post creation never rolls back)
            if (! $existingPost && Schema::hasTable('posts')) {
                $candidatePostData = [
                    'user_id' => $authorUserId,
                    'circle_id' => $user->active_circle_id ?? null,
                    'content_text' => $contentText,
                    'media' => [
                        [
                            'id' => $fileId,
                            'type' => 'image',
                            'url' => $creativeUrl,
                        ],
                    ],
                    'tags' => ['welcome', 'newpeer', 'peersglobal', (string) $user->id],
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'sponsored' => false,
                    'is_deleted' => false,
                    'source_type' => 'welcome',
                    'source_id' => (string) $user->id,
                    'source_event' => 'registration',
                    'post_type' => 'welcome',
                    'title' => $title,
                    'description' => $contentText,
                    'image' => $creativeUrl,
                    'status' => 'active',
                    'active' => true,
                ];

                $postColumns = Schema::getColumnListing('posts');
                $filteredPostData = [];
                foreach ($candidatePostData as $col => $val) {
                    if (in_array($col, $postColumns, true)) {
                        $filteredPostData[$col] = $val;
                    }
                }

                $existingPost = Post::create($filteredPostData);

                Log::info("WelcomeCreativeService: Created timeline post {$existingPost->id} for user {$user->id}");
            }

            if ($existingPost && Schema::hasTable('post_mentions')) {
                try {
                    PostMention::firstOrCreate([
                        'post_id' => $existingPost->id,
                        'peer_id' => $user->id,
                    ]);
                } catch (\Throwable) {
                    try {
                        \Illuminate\Support\Facades\DB::table('post_mentions')->updateOrInsert(
                            [
                                'post_id' => $existingPost->id,
                                'peer_id' => $user->id,
                            ],
                            [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    } catch (\Throwable $mentionEx) {
                        Log::warning("WelcomeCreativeService: Could not create post mention: {$mentionEx->getMessage()}");
                    }
                }
            }

            // 5. Create or update ActivityCreative record safely
            if (Schema::hasTable('activity_creatives') && $existingPost) {
                try {
                    $payload = [
                        'user_id' => $user->id,
                        'post_id' => $existingPost->id,
                        'activity_type' => 'welcome',
                        'activity_id' => (string) $user->id,
                        'title' => $title,
                        'description' => $contentText,
                        'creative_file_id' => $fileModel instanceof FileModel ? $fileModel->id : null,
                        'creative_url' => $creativeUrl,
                        'status' => 'active',
                        'meta' => [
                            'member_name' => $userName,
                            'company' => $company,
                            'city' => $cityName,
                            'template' => 'welcome-template.png',
                        ],
                        'created_by' => $authorUserId,
                    ];

                    if ($existingCreative) {
                        $existingCreative->fill($payload)->save();

                        return $existingCreative->refresh();
                    }

                    $creative = ActivityCreative::create($payload);

                    Log::info("WelcomeCreativeService: Stored welcome creative {$creative->id} for user {$user->id}");

                    return $creative;
                } catch (\Throwable $acEx) {
                    Log::warning("WelcomeCreativeService: Timeline post {$existingPost->id} created, but ActivityCreative save failed: {$acEx->getMessage()}");

                    $fallbackCreative = new ActivityCreative([
                        'user_id' => $user->id,
                        'post_id' => $existingPost->id,
                        'activity_type' => 'welcome',
                        'activity_id' => (string) $user->id,
                        'title' => $title,
                        'description' => $contentText,
                        'creative_url' => $creativeUrl,
                        'status' => 'active',
                    ]);
                    $fallbackCreative->id = (string) Str::uuid();

                    return $fallbackCreative;
                }
            }

            return $existingCreative;
        } catch (\Throwable $e) {
            Log::error('WelcomeCreativeService: Failed generating/posting welcome creative: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Determine if a user has all mandatory details needed to generate and post a welcome creative:
     * 1. Valid user name
     * 2. Verified/completed phone number
     * 3. Company / business name
     * 4. Real profile photo (verified by Gemini Vision to not be a letter/initials placeholder)
     */
    public function isEligibleForWelcomeCreative(User $user): bool
    {
        if ($user->isSystemUser()) {
            return false;
        }

        // 1. Must have a real name (not blank, User, or New Peer)
        $userName = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($userName === '' || strcasecmp($userName, 'User') === 0 || strcasecmp($userName, 'New Peer') === 0) {
            return false;
        }

        // 2. Must have completed phone registration
        if (blank($user->phone)) {
            return false;
        }

        // 3. Must have company / business name
        $company = trim((string) ($user->company_name ?? $user->company ?? $user->business_name ?? ''));
        if ($company === '') {
            return false;
        }

        // 4. Must have a valid profile avatar
        $avatarUrl = $this->resolveUserAvatarUrl($user);
        if (blank($avatarUrl)) {
            return false;
        }

        // 5. Verify that avatar is an actual photo of a person (not Google letter/initials avatar)
        try {
            $inspection = app(\App\Services\Ai\GeminiService::class)->inspectProfilePhoto($avatarUrl);
            if (! empty($inspection['is_initials_or_placeholder']) || empty($inspection['is_real_photo'])) {
                Log::info("WelcomeCreativeService: User {$user->id} avatar was flagged by Gemini as initials/placeholder ({$inspection['reason']}). Skipping welcome creative until real photo is uploaded.");

                return false;
            }
        } catch (\Throwable $e) {
            Log::warning("WelcomeCreativeService: Avatar AI inspection check failed: {$e->getMessage()}");
        }

        return true;
    }

    /**
     * Resolve the active avatar URL for a user.
     */
    public function resolveUserAvatarUrl(User $user): ?string
    {
        if (! empty($user->profile_photo_url)) {
            return (string) $user->profile_photo_url;
        }

        if (! empty($user->profile_photo_file_id)) {
            return url('/api/v1/files/'.$user->profile_photo_file_id);
        }

        if (! empty($user->social_avatar_url)) {
            return (string) $user->social_avatar_url;
        }

        return null;
    }
}
