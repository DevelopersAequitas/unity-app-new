<?php

declare(strict_types=1);

namespace App\Services\Creative;

use App\Models\ActivityCreative;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            $existingCreative = ActivityCreative::query()
                ->where('user_id', $user->id)
                ->where('activity_type', 'welcome')
                ->latest('created_at')
                ->first();

            if ($existingCreative && ! empty($existingCreative->creative_url) && ! empty($existingCreative->post_id)) {
                Log::info("WelcomeCreativeService: Existing welcome creative found for user {$user->id}");

                return $existingCreative;
            }

            // 1. Generate welcome creative image file
            $fileModel = $this->generator->generate($user);
            $creativeUrl = url('/api/v1/files/'.$fileModel->id);

            // 2. Persist URL to user model
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

            // 3. Prepare timeline post details
            $systemUser = User::getSystemUser();
            $authorUserId = $systemUser->id;

            $userName = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
            if ($userName === '') {
                $userName = 'New Peer';
            }

            $company = trim((string) ($user->company_name ?? $user->company ?? ''));
            $cityName = '';
            if ($user->relationLoaded('city') && $user->city) {
                $cityName = $user->city->name ?? '';
            } elseif (! empty($user->city)) {
                $cityName = is_string($user->city) ? $user->city : ($user->city['name'] ?? '');
            }
            $cityName = trim((string) $cityName);

            $bioParts = array_filter([$company, $cityName]);
            $bioStr = ! empty($bioParts) ? implode(', ', $bioParts) : 'Peers Global Community';

            $title = "Welcome {$userName} to Peers Global! 🎉";
            $contentText = "Please join us in welcoming {$userName} ({$bioStr}) to our Peers Global family! 🚀✨";

            return DB::transaction(function () use ($user, $authorUserId, $fileModel, $creativeUrl, $title, $contentText, $userName, $company, $cityName, $existingCreative) {
                // 4. Create timeline post
                $existingPost = null;
                if ($existingCreative && $existingCreative->post_id) {
                    $existingPost = Post::find($existingCreative->post_id);
                }

                if (! $existingPost) {
                    $existingPost = Post::query()
                        ->where('source_type', 'welcome')
                        ->where('source_id', (string) $user->id)
                        ->where('is_deleted', false)
                        ->first();
                }

                if (! $existingPost) {
                    $existingPost = Post::create([
                        'user_id' => $authorUserId,
                        'circle_id' => $user->active_circle_id ?? null,
                        'content_text' => $contentText,
                        'media' => [
                            [
                                'id' => $fileModel->id,
                                'type' => 'image',
                                'url' => $creativeUrl,
                            ],
                        ],
                        'tags' => ['welcome', 'newpeer', 'peersglobal'],
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
                    ]);

                    Log::info("WelcomeCreativeService: Created timeline post {$existingPost->id} for user {$user->id}");
                }

                // 5. Create or update ActivityCreative record
                $payload = [
                    'user_id' => $user->id,
                    'post_id' => $existingPost->id,
                    'activity_type' => 'welcome',
                    'activity_id' => (string) $user->id,
                    'title' => $title,
                    'description' => $contentText,
                    'creative_file_id' => $fileModel->id,
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
            });
        } catch (\Throwable $e) {
            Log::error('WelcomeCreativeService: Failed generating/posting welcome creative: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
